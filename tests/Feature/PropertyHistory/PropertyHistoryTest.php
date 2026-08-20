<?php

namespace Tests\Feature\PropertyHistory;

use App\Auth\Models\Usuario;
use App\PropertyHistory\Area;
use App\PropertyHistory\Asset;
use App\PropertyHistory\ConfiabilidadeIntervention;
use App\PropertyHistory\Intervention;
use App\PropertyHistory\OrigemIntervention;
use App\PropertyHistory\Property;
use App\PropertyHistory\RecordIntervention;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUsuario(Usuario $usuario): self
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($usuario);

        return $this;
    }

    private function guest(): self
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    public function test_history_requires_authentication(): void
    {
        $propertyId = (string) Str::uuid();

        $this->guest()
            ->getJson("/api/v1/properties/{$propertyId}/history")
            ->assertUnauthorized();
    }

    public function test_history_forbids_non_owner(): void
    {
        $owner = Usuario::factory()->create();
        $outsider = Usuario::factory()->create();
        $property = Property::factory()->ownedBy($owner)->create();

        $this->actingAsUsuario($outsider)
            ->getJson("/api/v1/properties/{$property->id}/history")
            ->assertForbidden();
    }

    public function test_history_returns_404_when_property_does_not_exist(): void
    {
        $usuario = Usuario::factory()->create();

        $this->actingAsUsuario($usuario)
            ->getJson('/api/v1/properties/'.Str::uuid().'/history')
            ->assertNotFound();
    }

    public function test_history_returns_nested_tree_ordered_by_date(): void
    {
        $usuario = Usuario::factory()->create();
        $property = Property::factory()->ownedBy($usuario)->create();
        $propertyId = $property->id;
        $record = app(RecordIntervention::class);

        $older = $record(
            propertyId: $propertyId,
            origem: OrigemIntervention::Plataforma,
            categoria: 'hidraulica',
            resumo: 'Troca da torneira antiga',
            data: now()->subDays(10),
            servicoId: (string) Str::uuid(),
            areaNome: 'Cozinha',
            assetNome: 'Torneira',
            assetTipo: 'HIDRAULICA',
        );

        $newer = $record(
            propertyId: $propertyId,
            origem: OrigemIntervention::Importado,
            categoria: 'hidraulica',
            resumo: 'Reparo do sifão',
            data: now()->subDays(2),
            areaNome: 'Cozinha',
            assetNome: 'Torneira',
        );

        $record(
            propertyId: $propertyId,
            origem: OrigemIntervention::Manual,
            categoria: 'eletrica',
            resumo: 'Troca do disjuntor',
            data: now()->subDays(5),
            areaNome: 'Banheiro',
            assetNome: 'Disjuntor',
            assetTipo: 'ELETRICA',
        );

        $response = $this->actingAsUsuario($usuario)
            ->getJson("/api/v1/properties/{$propertyId}/history");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.property_id', $propertyId);

        $areas = $response->json('data.areas');
        $this->assertCount(2, $areas);

        $areasByNome = [];
        foreach ($areas as $area) {
            $areasByNome[$area['nome']] = $area;
        }

        $this->assertArrayHasKey('Banheiro', $areasByNome);
        $this->assertArrayHasKey('Cozinha', $areasByNome);

        $torneira = $areasByNome['Cozinha']['assets'][0];
        $this->assertSame('Torneira', $torneira['nome']);
        $this->assertSame('HIDRAULICA', $torneira['tipo']);
        $this->assertCount(2, $torneira['interventions']);
        $this->assertSame($older->id, $torneira['interventions'][0]['id']);
        $this->assertSame($newer->id, $torneira['interventions'][1]['id']);
        $this->assertTrue($torneira['interventions'][0]['data'] < $torneira['interventions'][1]['data']);
        $this->assertSame('PLATAFORMA', $torneira['interventions'][0]['origem']);
        $this->assertSame('ALTA', $torneira['interventions'][0]['confiabilidade']);
        $this->assertSame($older->asset_id, $torneira['interventions'][0]['asset_id']);
        $this->assertSame('IMPORTADO', $torneira['interventions'][1]['origem']);
        $this->assertSame('MEDIA', $torneira['interventions'][1]['confiabilidade']);
        $this->assertNull($torneira['interventions'][1]['servico_id']);

        $disjuntor = $areasByNome['Banheiro']['assets'][0];
        $this->assertSame('MANUAL', $disjuntor['interventions'][0]['origem']);
        $this->assertSame('BAIXA', $disjuntor['interventions'][0]['confiabilidade']);
        $this->assertNotNull($disjuntor['interventions'][0]['origem']);
    }

    public function test_history_is_empty_when_property_has_no_interventions(): void
    {
        $usuario = Usuario::factory()->create();
        $property = Property::factory()->ownedBy($usuario)->create();
        $propertyId = $property->id;

        $this->actingAsUsuario($usuario)
            ->getJson("/api/v1/properties/{$propertyId}/history")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.property_id', $propertyId)
            ->assertJsonPath('data.areas', []);
    }

    public function test_unspecified_fallback_is_used_when_granularity_is_missing(): void
    {
        $usuario = Usuario::factory()->create();
        $property = Property::factory()->ownedBy($usuario)->create();
        $propertyId = $property->id;

        $intervention = app(RecordIntervention::class)(
            propertyId: $propertyId,
            origem: OrigemIntervention::Plataforma,
            categoria: 'pintura',
            resumo: 'Pintura geral sem ambiente informado',
            data: now(),
            servicoId: (string) Str::uuid(),
        );

        $asset = $intervention->asset;
        $this->assertNotNull($asset);
        $this->assertSame(Asset::FALLBACK_NAME, $asset->nome);
        $this->assertSame(Area::FALLBACK_NAME, $asset->area->nome);
        $this->assertSame($propertyId, $asset->area->property_id);

        $this->actingAsUsuario($usuario)
            ->getJson("/api/v1/properties/{$propertyId}/history")
            ->assertOk()
            ->assertJsonPath('data.areas.0.nome', Area::FALLBACK_NAME)
            ->assertJsonPath('data.areas.0.assets.0.nome', Asset::FALLBACK_NAME);
    }

    public function test_unspecified_fallback_applies_only_to_missing_asset_when_area_is_known(): void
    {
        $propertyId = (string) Str::uuid();

        $intervention = app(RecordIntervention::class)(
            propertyId: $propertyId,
            origem: OrigemIntervention::Manual,
            categoria: 'hidraulica',
            resumo: 'Vazamento na cozinha, item não informado',
            data: now(),
            areaNome: 'Cozinha',
        );

        $this->assertSame('Cozinha', $intervention->asset->area->nome);
        $this->assertSame(Asset::FALLBACK_NAME, $intervention->asset->nome);
    }

    public function test_every_intervention_references_an_asset_and_has_origem(): void
    {
        $intervention = Intervention::factory()->plataforma()->create();

        $this->assertNotNull($intervention->asset_id);
        $this->assertTrue($intervention->asset()->exists());
        $this->assertTrue($intervention->asset->area()->exists());
        $this->assertSame(OrigemIntervention::Plataforma, $intervention->origem);
        $this->assertSame(ConfiabilidadeIntervention::Alta, $intervention->confiabilidade);
        $this->assertNotNull($intervention->servico_id);

        $this->expectException(QueryException::class);

        Intervention::query()->create([
            'asset_id' => null,
            'data' => now(),
            'categoria' => 'hidraulica',
            'resumo' => 'Intervenção solta, proibida por INV-061',
            'origem' => OrigemIntervention::Manual,
        ]);
    }

    public function test_manual_origem_clears_servico_id(): void
    {
        $asset = Asset::factory()->create();

        $intervention = Intervention::query()->create([
            'asset_id' => $asset->id,
            'servico_id' => (string) Str::uuid(),
            'data' => now(),
            'categoria' => 'montagem',
            'resumo' => 'Anotação do proprietário',
            'origem' => OrigemIntervention::Manual,
        ]);

        $this->assertNull($intervention->servico_id);
        $this->assertSame(OrigemIntervention::Manual, $intervention->origem);
        $this->assertSame(ConfiabilidadeIntervention::Baixa, $intervention->confiabilidade);
    }

    public function test_confiabilidade_cannot_be_set_manually(): void
    {
        $intervention = Intervention::factory()->plataforma()->create([
            'confiabilidade' => ConfiabilidadeIntervention::Baixa,
        ]);

        $this->assertSame(ConfiabilidadeIntervention::Alta, $intervention->confiabilidade);
    }
}
