<?php

namespace App\Trust;

use App\Auth\Enums\StatusConta;
use App\Auth\Models\Usuario;
use App\Payments\Auditoria;
use App\Trust\Enums\OrigemVazamentoContato;
use App\Trust\Models\ContactLeakAttempt;
use App\Trust\Models\ContactPenaltyNote;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ContactLeakEnforcer
{
    public function __construct(private readonly ContactSanitizer $sanitizer) {}

    /**
     * @return array{sanitized: string, changed: bool, warning: ?string, attempts_in_window: int}
     */
    public function apply(
        Usuario $usuario,
        OrigemVazamentoContato $origem,
        string $text,
        ?string $propostaId = null,
        ?string $servicoId = null,
    ): array {
        $result = $this->sanitizer->sanitize($text);
        $attemptsInWindow = $this->attemptsInWindow($usuario->id);

        // Mesmo quando o filtro não atua neste texto, precisamos manter a régua em rolling 90 dias,
        // para permitir suspensão reversível (INV-003).
        if ($result['changed'] !== true) {
            $this->syncPenaltyStatus($usuario, $attemptsInWindow);

            return [
                'sanitized' => $text,
                'changed' => false,
                'warning' => null,
                'attempts_in_window' => $attemptsInWindow,
            ];
        }

        $attemptsInWindow = DB::transaction(function () use ($usuario, $origem, $text, $result, $propostaId, $servicoId): int {
            foreach ($result['detected_patterns'] as $pattern) {
                ContactLeakAttempt::query()->create([
                    'usuario_id' => $usuario->id,
                    'origem' => $origem,
                    'proposta_id' => $propostaId,
                    'servico_id' => $servicoId,
                    'padrao_detectado' => $pattern,
                    'texto_original' => $text,
                    'texto_filtrado' => $result['sanitized'],
                ]);
            }

            $count = $this->attemptsInWindow($usuario->id);

            if ($count >= 3 && $count <= 4) {
                ContactPenaltyNote::query()->create([
                    'usuario_id' => $usuario->id,
                    'attempts_in_window' => $count,
                    'nota' => 'Tentativa recorrente de compartilhamento de contato em janela de 90 dias.',
                ]);
            }

            $this->syncPenaltyStatus($usuario, $count);

            return $count;
        });

        return [
            'sanitized' => $result['sanitized'],
            'changed' => true,
            'warning' => 'Detectamos tentativa de compartilhamento de contato. O trecho foi removido para manter a negociação protegida na plataforma.',
            'attempts_in_window' => $attemptsInWindow,
        ];
    }

    private function attemptsInWindow(string $usuarioId): int
    {
        $windowStart = CarbonImmutable::now()->subDays(90);

        return ContactLeakAttempt::query()
            ->where('usuario_id', $usuarioId)
            ->where('created_at', '>=', $windowStart)
            ->count();
    }

    private function syncPenaltyStatus(Usuario $usuario, int $attemptsInWindow): void
    {
        if ($attemptsInWindow >= 5 && $usuario->status !== StatusConta::Suspensa) {
            $usuario->forceFill(['status' => StatusConta::Suspensa])->save();

            Auditoria::query()->create([
                'usuario_id' => $usuario->id,
                'acao' => 'CONTACT_LEAK_AUTO_SUSPEND',
                'entidade' => 'Usuario',
                'id_entidade' => $usuario->id,
                'justificativa' => 'Suspensão automática por 5+ tentativas de vazamento de contato em 90 dias.',
            ]);

            return;
        }

        if ($attemptsInWindow < 5 && $usuario->status === StatusConta::Suspensa) {
            $usuario->forceFill(['status' => StatusConta::Ativa])->save();

            Auditoria::query()->create([
                'usuario_id' => $usuario->id,
                'acao' => 'CONTACT_LEAK_AUTO_REACTIVATE_INV003',
                'entidade' => 'Usuario',
                'id_entidade' => $usuario->id,
                'justificativa' => 'Reativação automática após janela rolling de 90 dias com menos de 5 tentativas de vazamento de contato (INV-003).',
            ]);
        }
    }
}
