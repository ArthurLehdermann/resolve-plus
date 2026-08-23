<?php

namespace App\Privacy;

use App\Auth\Enums\StatusConta;
use App\Auth\Models\Usuario;
use App\Professionals\DocumentoProfissional;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Eliminação a pedido do titular (LGPD, art. 18, VI).
 *
 * Não é `DELETE FROM usuarios`: serviço, pagamento e garantia são registros de
 * uma transação entre duas pessoas e sustentam obrigação legal e direito do
 * outro lado (art. 16, I e II). Então apagamos o que identifica — nome,
 * e-mail, telefone, foto, documentos, sessões e o texto pré-filtro guardado
 * para auditoria — e deixamos a linha da transação de pé, apontando para uma
 * conta anônima.
 */
final class ExcluirConta
{
    /**
     * Status em que ainda existe combinado aberto entre as partes. Excluir a
     * conta no meio disso deixaria o outro lado sem contraparte e o dinheiro
     * em aberto.
     */
    private const STATUS_EM_ANDAMENTO = [
        StatusServico::Agendado,
        StatusServico::EmAndamento,
        StatusServico::AguardandoAprovacao,
        StatusServico::EmContestacao,
    ];

    public function paraUsuario(Usuario $usuario): void
    {
        if ($this->temServicoEmAndamento($usuario)) {
            throw new ContaComServicoEmAndamento;
        }

        $this->apagarDocumentos($usuario);

        DB::transaction(function () use ($usuario): void {
            DB::table('links_magicos')->where('usuario_id', $usuario->id)->delete();
            DB::table('notificacoes')->where('usuario_id', $usuario->id)->delete();
            DB::table('sessions')->where('user_id', $usuario->id)->delete();
            DB::table('password_reset_tokens')->where('email', $usuario->email)->delete();
            $usuario->tokens()->delete();

            // Cópia pré-filtro do texto: existe só para apurar tentativa de
            // desintermediação e é justamente onde sobra o contato pessoal.
            DB::table('mensagens')->where('remetente_id', $usuario->id)->update(['texto_original' => null]);
            DB::table('propostas')->where('profissional_id', $usuario->id)->update(['observacoes_original' => null]);

            $usuario->forceFill([
                'nome' => 'Conta removida',
                // Sufixo aleatório em domínio reservado (RFC 2606): a coluna é
                // única e não pode colidir com outra conta removida, nem virar
                // endereço para o qual alguém consiga escrever.
                'email' => 'removido+'.Str::uuid()->toString().'@resolveplus.invalid',
                'telefone' => null,
                'foto' => null,
                'senha_hash' => null,
                'status' => StatusConta::Excluida,
            ])->save();
        });
    }

    private function temServicoEmAndamento(Usuario $usuario): bool
    {
        return Servico::query()
            ->doParticipante($usuario->id)
            ->whereIn('status', array_map(fn (StatusServico $s): string => $s->value, self::STATUS_EM_ANDAMENTO))
            ->exists();
    }

    /**
     * Documento de identificação é o dado mais sensível que a plataforma
     * guarda: sai do armazenamento junto com o registro, não fica órfão no
     * bucket.
     */
    private function apagarDocumentos(Usuario $usuario): void
    {
        $disco = Storage::disk((string) config('filesystems.object_disk', 's3'));

        DocumentoProfissional::query()
            ->where('profissional_id', $usuario->id)
            ->get()
            ->each(function (DocumentoProfissional $documento) use ($disco): void {
                if ($documento->arquivo !== null && $documento->arquivo !== '') {
                    $disco->delete($documento->arquivo);
                }

                $documento->delete();
            });
    }
}
