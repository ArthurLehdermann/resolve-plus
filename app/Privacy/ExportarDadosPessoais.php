<?php

namespace App\Privacy;

use App\Auth\Models\Usuario;
use App\Proposals\Proposta;
use App\Requests\Solicitacao;
use App\Services\Mensagem;
use App\Services\Servico;
use Illuminate\Support\Facades\DB;

/**
 * Portabilidade e acesso (LGPD, art. 18, II e V): devolve, num único
 * documento, o que a plataforma guarda sobre o titular.
 *
 * Só entra dado do próprio titular. Mensagem que ele recebeu, proposta que
 * outro profissional escreveu e avaliação que fizeram sobre ele são dados de
 * terceiro no mesmo serviço — atender um titular não pode virar porta de saída
 * para os dados de quem negociou com ele. Do que é sobre ele mas escrito por
 * outro (nota recebida), vai o número, não o autor.
 */
final class ExportarDadosPessoais
{
    /**
     * @return array<string, mixed>
     */
    public function paraUsuario(Usuario $usuario): array
    {
        return [
            'gerado_em' => now()->toIso8601String(),
            'conta' => [
                'id' => $usuario->id,
                'tipo' => $usuario->tipo->value,
                'nome' => $usuario->nome,
                'email' => $usuario->email,
                'telefone' => $usuario->telefone,
                'foto' => $usuario->foto,
                'status' => $usuario->status->value,
                'criada_em' => $usuario->created_at?->toIso8601String(),
            ],
            'perfil_profissional' => $this->perfilProfissional($usuario),
            'documentos_enviados' => $this->documentos($usuario),
            'imoveis' => $this->imoveis($usuario),
            'solicitacoes' => $this->solicitacoes($usuario),
            'propostas_enviadas' => $this->propostas($usuario),
            'servicos' => $this->servicos($usuario),
            'mensagens_enviadas' => $this->mensagens($usuario),
            'avaliacoes_que_escrevi' => $this->avaliacoesEscritas($usuario),
            'notas_que_recebi' => $this->notasRecebidas($usuario),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function perfilProfissional(Usuario $usuario): ?array
    {
        $perfil = $usuario->perfilProfissional;

        if ($perfil === null) {
            return null;
        }

        return [
            'nivel_confianca' => $perfil->nivel_confianca,
            'categorias_atendidas' => $perfil->categorias_atendidas,
            'servicos_aprovados' => $perfil->servicos_aprovados,
            'nota_media_dez' => $perfil->nota_media_dez,
        ];
    }

    /**
     * Metadado do documento, nunca o arquivo: a exportação é um JSON, e o
     * conteúdo do documento continua acessível pelos canais do app.
     *
     * @return array<int, array<string, mixed>>
     */
    private function documentos(Usuario $usuario): array
    {
        return $usuario->documentosProfissional()
            ->get(['tipo', 'status', 'created_at'])
            ->map(fn ($doc): array => [
                'tipo' => $doc->tipo,
                'status' => $doc->status,
                'enviado_em' => $doc->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function imoveis(Usuario $usuario): array
    {
        return DB::table('property_ownerships')
            ->join('properties', 'properties.id', '=', 'property_ownerships.property_id')
            ->where('property_ownerships.cliente_id', $usuario->id)
            ->get([
                'properties.apelido',
                'properties.cep',
                'properties.logradouro',
                'properties.numero',
                'properties.complemento',
                'properties.bairro',
                'properties.cidade',
                'properties.estado',
                'property_ownerships.desde',
                'property_ownerships.ate',
            ])
            ->map(fn ($linha): array => (array) $linha)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function solicitacoes(Usuario $usuario): array
    {
        return Solicitacao::query()
            ->where('cliente_id', $usuario->id)
            ->orderBy('criado_em')
            ->get(['id', 'descricao', 'escopo', 'status', 'data_desejada', 'criado_em'])
            ->map(fn (Solicitacao $s): array => [
                'id' => $s->id,
                'descricao' => $s->descricao,
                'escopo' => $s->escopo,
                'status' => $s->status,
                'data_desejada' => $s->data_desejada,
                'criada_em' => $s->criado_em,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function propostas(Usuario $usuario): array
    {
        return Proposta::query()
            ->where('profissional_id', $usuario->id)
            ->orderBy('created_at')
            ->get(['id', 'solicitacao_id', 'valor', 'prazo_dias', 'garantia_dias', 'observacoes', 'status', 'created_at'])
            ->map(fn (Proposta $p): array => [
                'id' => $p->id,
                'solicitacao_id' => $p->solicitacao_id,
                'valor' => $p->valor,
                'prazo_dias' => $p->prazo_dias,
                'garantia_dias' => $p->garantia_dias,
                'observacoes' => $p->observacoes,
                'status' => $p->status,
                'enviada_em' => $p->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function servicos(Usuario $usuario): array
    {
        return Servico::query()
            ->doParticipante($usuario->id)
            ->orderBy('created_at')
            ->get(['id', 'status', 'inicio', 'fim', 'notas', 'created_at'])
            ->map(fn (Servico $s): array => [
                'id' => $s->id,
                'status' => $s->status,
                'inicio' => $s->inicio,
                'fim' => $s->fim,
                'notas' => $s->notas,
                'criado_em' => $s->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mensagens(Usuario $usuario): array
    {
        return Mensagem::query()
            ->where('remetente_id', $usuario->id)
            ->orderBy('enviado_em')
            ->get(['servico_id', 'texto', 'enviado_em'])
            ->map(fn (Mensagem $m): array => [
                'servico_id' => $m->servico_id,
                'texto' => $m->texto,
                'enviado_em' => $m->enviado_em,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function avaliacoesEscritas(Usuario $usuario): array
    {
        return DB::table('avaliacoes')
            ->where('autor_id', $usuario->id)
            ->orderBy('criado_em')
            ->get(['servico_id', 'nota', 'comentario', 'criado_em'])
            ->map(fn ($linha): array => (array) $linha)
            ->all();
    }

    /**
     * Nota e comentário sobre o titular, sem identificar quem avaliou.
     *
     * @return array<int, array<string, mixed>>
     */
    private function notasRecebidas(Usuario $usuario): array
    {
        return DB::table('avaliacoes')
            ->where('alvo_id', $usuario->id)
            ->orderBy('criado_em')
            ->get(['servico_id', 'nota', 'comentario', 'criado_em'])
            ->map(fn ($linha): array => (array) $linha)
            ->all();
    }
}
