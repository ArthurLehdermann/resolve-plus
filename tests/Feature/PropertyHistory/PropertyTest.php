<?php

namespace Tests\Feature\PropertyHistory;

use App\Auth\Models\Usuario;
use App\PropertyHistory\Property;
use App\PropertyHistory\PropertyOwnership;
use App\PropertyHistory\PropertyOwnershipTransfer;
use App\PropertyHistory\StatusPropertyOwnershipTransfer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cep' => '01310-200',
            'logradouro' => 'Avenida Paulista',
            'numero' => '100',
            'complemento' => 'Apto 101',
            'bairro' => 'Bela Vista',
            'cidade' => 'São Paulo',
            'estado' => 'SP',
            'apelido' => 'Ap Paulista',
        ], $overrides);
    }

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

    public function test_post_properties_creates_property_and_current_ownership(): void
    {
        $usuario = Usuario::factory()->create();

        $response = $this->actingAsUsuario($usuario)
            ->postJson('/api/v1/properties', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.chave_endereco', '01310200|100|APTO101')
            ->assertJsonPath('data.cidade', 'São Paulo')
            ->assertJsonPath('data.apelido', 'Ap Paulista');

        $propertyId = $response->json('data.id');
        $this->assertNotNull($propertyId);

        $this->assertDatabaseHas('property_ownerships', [
            'property_id' => $propertyId,
            'cliente_id' => $usuario->id,
            'ate' => null,
        ]);
    }

    public function test_inv_063_duplicate_chave_endereco_returns_409_with_existing_id(): void
    {
        $alice = Usuario::factory()->create(['email' => 'a@example.com']);
        $bob = Usuario::factory()->create(['email' => 'b@example.com']);

        $created = $this->actingAsUsuario($alice)
            ->postJson('/api/v1/properties', $this->payload())
            ->assertCreated();

        $existingId = $created->json('data.id');

        $this->actingAsUsuario($bob)
            ->postJson('/api/v1/properties', $this->payload([
                'cep' => '01310.200',
                'numero' => '100',
                'complemento' => 'apto 101',
                'logradouro' => 'Av. Paulista',
                'apelido' => 'Outro cadastro',
            ]))
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.property_id', $existingId);

        $this->assertSame(1, Property::query()->count());
    }

    public function test_unique_index_prevents_duplicate_chave_endereco(): void
    {
        Property::factory()->create([
            'cep' => '01310-200',
            'numero' => '100',
            'complemento' => 'Apto 101',
        ]);

        $this->expectException(QueryException::class);

        Property::factory()->create([
            'cep' => '01310200',
            'numero' => '100',
            'complemento' => 'APTO101',
        ]);
    }

    public function test_get_properties_lists_only_current_owner_properties(): void
    {
        $alice = Usuario::factory()->create(['email' => 'alice@example.com']);
        $bob = Usuario::factory()->create(['email' => 'bob@example.com']);

        $daAlice = $this->actingAsUsuario($alice)
            ->postJson('/api/v1/properties', $this->payload())
            ->assertCreated()
            ->json('data.id');

        $doBob = $this->actingAsUsuario($bob)
            ->postJson('/api/v1/properties', $this->payload([
                'numero' => '200',
                'complemento' => null,
            ]))
            ->assertCreated()
            ->json('data.id');

        $this->actingAsUsuario($alice)
            ->getJson('/api/v1/properties')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $daAlice);

        $this->actingAsUsuario($bob)
            ->getJson('/api/v1/properties')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $doBob);
    }

    public function test_put_properties_requires_current_owner_and_revalidates_chave(): void
    {
        $alice = Usuario::factory()->create(['email' => 'alice@example.com']);
        $bob = Usuario::factory()->create(['email' => 'bob@example.com']);

        $propertyId = $this->actingAsUsuario($alice)
            ->postJson('/api/v1/properties', $this->payload())
            ->json('data.id');

        $otherId = $this->actingAsUsuario($bob)
            ->postJson('/api/v1/properties', $this->payload([
                'numero' => '200',
                'complemento' => null,
            ]))
            ->json('data.id');

        $this->actingAsUsuario($bob)
            ->putJson('/api/v1/properties/'.$propertyId, ['apelido' => 'Hack'])
            ->assertForbidden();

        $this->actingAsUsuario($alice)
            ->putJson('/api/v1/properties/'.$propertyId, ['apelido' => 'Casa nova'])
            ->assertOk()
            ->assertJsonPath('data.apelido', 'Casa nova')
            ->assertJsonPath('data.chave_endereco', '01310200|100|APTO101');

        $this->actingAsUsuario($alice)
            ->putJson('/api/v1/properties/'.$propertyId, [
                'numero' => '200',
                'complemento' => null,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.property_id', $otherId);
    }

    public function test_inv_064_initiate_does_not_change_ownership_until_accept(): void
    {
        $alice = Usuario::factory()->create(['email' => 'alice@example.com']);
        $bob = Usuario::factory()->create(['email' => 'bob@example.com']);

        $propertyId = $this->actingAsUsuario($alice)
            ->postJson('/api/v1/properties', $this->payload())
            ->json('data.id');

        $transfer = $this->actingAsUsuario($alice)
            ->postJson('/api/v1/properties/'.$propertyId.'/transfer', [
                'para_cliente_id' => $bob->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'PENDENTE')
            ->assertJsonPath('data.para_cliente_id', $bob->id);

        $this->assertDatabaseHas('property_ownerships', [
            'property_id' => $propertyId,
            'cliente_id' => $alice->id,
            'ate' => null,
        ]);
        $this->assertSame(1, PropertyOwnership::query()->where('property_id', $propertyId)->count());

        $this->actingAsUsuario($alice)
            ->getJson('/api/v1/properties')
            ->assertJsonCount(1, 'data');

        $this->actingAsUsuario($bob)
            ->getJson('/api/v1/properties')
            ->assertJsonCount(0, 'data');

        $this->actingAsUsuario($bob)
            ->getJson('/api/v1/property-transfers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $transfer->json('data.id'));
    }

    public function test_inv_064_non_destination_cannot_accept_and_ownership_stays(): void
    {
        $alice = Usuario::factory()->create(['email' => 'alice@example.com']);
        $bob = Usuario::factory()->create(['email' => 'bob@example.com']);
        $carol = Usuario::factory()->create(['email' => 'carol@example.com']);

        $propertyId = $this->actingAsUsuario($alice)
            ->postJson('/api/v1/properties', $this->payload())
            ->json('data.id');

        $transferId = $this->actingAsUsuario($alice)
            ->postJson('/api/v1/properties/'.$propertyId.'/transfer', [
                'para_cliente_id' => $bob->id,
            ])
            ->json('data.id');

        $this->actingAsUsuario($alice)
            ->postJson('/api/v1/property-transfers/'.$transferId.'/accept')
            ->assertForbidden();

        $this->actingAsUsuario($carol)
            ->postJson('/api/v1/property-transfers/'.$transferId.'/accept')
            ->assertForbidden();

        $this->assertDatabaseHas('property_ownership_transfers', [
            'id' => $transferId,
            'status' => StatusPropertyOwnershipTransfer::Pendente->value,
        ]);
        $this->assertDatabaseHas('property_ownerships', [
            'property_id' => $propertyId,
            'cliente_id' => $alice->id,
            'ate' => null,
        ]);
        $this->assertSame(1, PropertyOwnership::query()->where('property_id', $propertyId)->whereNull('ate')->count());
    }

    public function test_inv_064_accept_closes_old_ownership_and_opens_new_one(): void
    {
        $alice = Usuario::factory()->create(['email' => 'alice@example.com']);
        $bob = Usuario::factory()->create(['email' => 'bob@example.com']);

        $propertyId = $this->actingAsUsuario($alice)
            ->postJson('/api/v1/properties', $this->payload())
            ->json('data.id');

        $transferId = $this->actingAsUsuario($alice)
            ->postJson('/api/v1/properties/'.$propertyId.'/transfer', [
                'para_email' => $bob->email,
            ])
            ->json('data.id');

        $this->actingAsUsuario($bob)
            ->postJson('/api/v1/property-transfers/'.$transferId.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', 'ACEITO')
            ->assertJsonPath('data.para_cliente_id', $bob->id);

        $this->assertSame(
            1,
            PropertyOwnership::query()->where('property_id', $propertyId)->whereNull('ate')->count(),
        );
        $this->assertDatabaseHas('property_ownerships', [
            'property_id' => $propertyId,
            'cliente_id' => $bob->id,
            'ate' => null,
        ]);

        $old = PropertyOwnership::query()
            ->where('property_id', $propertyId)
            ->where('cliente_id', $alice->id)
            ->first();
        $this->assertNotNull($old);
        $this->assertNotNull($old->ate);

        $this->actingAsUsuario($alice)
            ->getJson('/api/v1/properties')
            ->assertJsonCount(0, 'data');

        $this->actingAsUsuario($bob)
            ->getJson('/api/v1/properties')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $propertyId);
    }

    public function test_decline_does_not_change_ownership(): void
    {
        $alice = Usuario::factory()->create(['email' => 'alice@example.com']);
        $bob = Usuario::factory()->create(['email' => 'bob@example.com']);

        $propertyId = $this->actingAsUsuario($alice)
            ->postJson('/api/v1/properties', $this->payload())
            ->json('data.id');

        $transferId = $this->actingAsUsuario($alice)
            ->postJson('/api/v1/properties/'.$propertyId.'/transfer', [
                'para_cliente_id' => $bob->id,
            ])
            ->json('data.id');

        $this->actingAsUsuario($bob)
            ->postJson('/api/v1/property-transfers/'.$transferId.'/decline')
            ->assertOk()
            ->assertJsonPath('data.status', 'RECUSADO');

        $this->assertDatabaseHas('property_ownerships', [
            'property_id' => $propertyId,
            'cliente_id' => $alice->id,
            'ate' => null,
        ]);
        $this->assertSame(1, PropertyOwnership::query()->where('property_id', $propertyId)->count());
    }

    public function test_non_owner_cannot_initiate_transfer(): void
    {
        $alice = Usuario::factory()->create(['email' => 'alice@example.com']);
        $bob = Usuario::factory()->create(['email' => 'bob@example.com']);

        $propertyId = $this->actingAsUsuario($alice)
            ->postJson('/api/v1/properties', $this->payload())
            ->json('data.id');

        $this->actingAsUsuario($bob)
            ->postJson('/api/v1/properties/'.$propertyId.'/transfer', [
                'para_email' => 'terceiro@example.com',
            ])
            ->assertForbidden();

        $this->assertSame(0, PropertyOwnershipTransfer::query()->count());
    }

    public function test_transfer_requires_destination_and_auth(): void
    {
        $usuario = Usuario::factory()->create();
        $propertyId = $this->actingAsUsuario($usuario)
            ->postJson('/api/v1/properties', $this->payload())
            ->json('data.id');

        $this->actingAsUsuario($usuario)
            ->postJson('/api/v1/properties/'.$propertyId.'/transfer', [])
            ->assertUnprocessable();

        $this->guest()->postJson('/api/v1/properties', $this->payload())->assertUnauthorized();
        $this->guest()->getJson('/api/v1/properties')->assertUnauthorized();
        $this->guest()->getJson('/api/v1/property-transfers')->assertUnauthorized();
    }

    public function test_at_most_one_current_ownership_per_property(): void
    {
        $usuario = Usuario::factory()->create();
        $property = Property::factory()->ownedBy($usuario)->create();

        $this->expectException(QueryException::class);

        PropertyOwnership::query()->create([
            'property_id' => $property->id,
            'cliente_id' => Usuario::factory()->create()->id,
            'desde' => now(),
            'ate' => null,
        ]);
    }
}
