<?php

namespace App\Privacy\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Necessidade e minimização (LGPD, art. 6º, III): dado que já cumpriu a
 * finalidade não fica guardado à toa.
 *
 * Só entra aqui o que é transitório por natureza — código de entrada já usado,
 * chave de idempotência, payload cru de webhook e token expirado. Registro de
 * serviço, pagamento, garantia e auditoria não é expurgado por rotina: sai
 * pelos prazos legais ou por pedido de titular (ExcluirConta).
 */
class PurgeExpiredDataCommand extends Command
{
    protected $signature = 'privacy:purge {--dry-run : Apenas conta o que seria apagado}';

    protected $description = 'Expurga dados transitórios vencidos (códigos de entrada, tokens, webhooks e chaves de idempotência).';

    public function handle(): int
    {
        $seco = (bool) $this->option('dry-run');

        $alvos = [
            'links de entrada vencidos' => DB::table('links_magicos')
                ->where('expires_at', '<', now()->subDays(7)),

            'tokens de acesso expirados' => DB::table('personal_access_tokens')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now()->subDays(7)),

            'tokens de redefinição de senha' => DB::table('password_reset_tokens')
                ->where('created_at', '<', now()->subDays(7)),

            'chaves de idempotência' => DB::table('idempotency_keys')
                ->where('created_at', '<', now()->subDays(30)),

            // O payload cru do gateway carrega dados do pagador. O efeito dele
            // já está nas tabelas de pagamento; a cópia bruta serve para
            // conferência de curto prazo.
            'payloads de webhook' => DB::table('payment_webhook_events')
                ->where('criado_em', '<', now()->subDays(90)),
        ];

        $total = 0;

        foreach ($alvos as $rotulo => $consulta) {
            $quantidade = $seco ? $consulta->count() : $consulta->delete();
            $total += $quantidade;

            $this->line(sprintf('%-32s %d', $rotulo, $quantidade));
        }

        $this->info($seco
            ? "Total que seria apagado: {$total}"
            : "Total apagado: {$total}");

        return self::SUCCESS;
    }
}
