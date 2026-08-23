<?php

namespace App\Privacy\Console;

use App\Auth\Models\Usuario;
use App\Privacy\ContaComServicoEmAndamento;
use App\Privacy\ExcluirConta;
use App\Privacy\ExportarDadosPessoais;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Atendimento de pedido de titular que chega pelo encarregado (e-mail), e não
 * pelo app: mesma regra, mesmo resultado, sem consulta manual ao banco.
 *
 *   php artisan privacy:subject fulano@example.com --export
 *   php artisan privacy:subject fulano@example.com --delete
 */
class DataSubjectCommand extends Command
{
    protected $signature = 'privacy:subject
        {email : E-mail da conta}
        {--export : Grava a exportação em storage/app/private}
        {--delete : Exclui a conta (anonimiza o titular e mantém a transação)}';

    protected $description = 'Atende pedido de titular (LGPD, art. 18) recebido fora do app.';

    public function handle(ExportarDadosPessoais $exportar, ExcluirConta $excluir): int
    {
        $email = (string) $this->argument('email');

        $usuario = Usuario::query()->comEmail($email)->first();

        if ($usuario === null) {
            $this->error("Nenhuma conta com o e-mail {$email}.");

            return self::FAILURE;
        }

        $this->line("Conta: {$usuario->id} ({$usuario->tipo->value}, {$usuario->status->value})");

        if ($this->option('export')) {
            $arquivo = 'privacy/export-'.$usuario->id.'-'.now()->format('Ymd-His').'.json';
            $conteudo = json_encode(
                $exportar->paraUsuario($usuario),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            Storage::disk('local')->put($arquivo, (string) $conteudo);

            $this->info("Exportação gravada em storage/app/private/{$arquivo}");
        }

        if ($this->option('delete')) {
            if (! $this->confirm("Excluir definitivamente os dados de identificação de {$email}?")) {
                $this->comment('Nada foi alterado.');

                return self::SUCCESS;
            }

            try {
                $excluir->paraUsuario($usuario);
            } catch (ContaComServicoEmAndamento $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $this->info('Conta excluída e dados de identificação removidos.');
        }

        if (! $this->option('export') && ! $this->option('delete')) {
            $this->comment('Use --export para gerar a cópia dos dados ou --delete para excluir.');
        }

        return self::SUCCESS;
    }
}
