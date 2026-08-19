<?php

namespace App\Trust;

use App\Proposals\Proposta;
use App\Services\Servico;
use App\Trust\Enums\OrigemVazamentoContato;
use App\Trust\Models\ContactLeakAttempt;
use Illuminate\Support\Facades\DB;

final class AdminContactLeakMetrics
{
    /**
     * @return array<string, float|int>
     */
    public function build(): array
    {
        $totalPropostas = Proposta::query()->count();
        $propostasComTentativa = ContactLeakAttempt::query()
            ->where('origem', OrigemVazamentoContato::Proposta)
            ->whereNotNull('proposta_id')
            ->distinct('proposta_id')
            ->count('proposta_id');

        $totalServicos = Servico::query()->count();
        $servicosComTentativaMensagem = ContactLeakAttempt::query()
            ->where('origem', OrigemVazamentoContato::Mensagem)
            ->whereNotNull('servico_id')
            ->distinct('servico_id')
            ->count('servico_id');

        $servicosComTentativa = DB::table('servicos')
            ->whereIn('id', function ($query): void {
                $query->select('servico_id')
                    ->from('contact_leak_attempts')
                    ->whereNotNull('servico_id')
                    ->union(
                        DB::table('contact_leak_attempts')
                            ->join('propostas', 'propostas.id', '=', 'contact_leak_attempts.proposta_id')
                            ->join('servicos as s2', 's2.proposta_id', '=', 'propostas.id')
                            ->whereNotNull('contact_leak_attempts.proposta_id')
                            ->select('s2.id')
                    );
            })
            ->distinct('id')
            ->count('id');

        $servicosAprovadosComTentativa = DB::table('servicos')
            ->where('status', 'APROVADO')
            ->whereIn('id', function ($query): void {
                $query->select('servico_id')
                    ->from('contact_leak_attempts')
                    ->whereNotNull('servico_id')
                    ->union(
                        DB::table('contact_leak_attempts')
                            ->join('propostas', 'propostas.id', '=', 'contact_leak_attempts.proposta_id')
                            ->join('servicos as s2', 's2.proposta_id', '=', 'propostas.id')
                            ->whereNotNull('contact_leak_attempts.proposta_id')
                            ->select('s2.id')
                    );
            })
            ->distinct('id')
            ->count('id');

        return [
            'total_attempts' => ContactLeakAttempt::query()->count(),
            'attempt_rate_pre_acceptance' => $this->rate($propostasComTentativa, $totalPropostas),
            'attempt_rate_post_acceptance' => $this->rate($servicosComTentativaMensagem, $totalServicos),
            'post_attempt_completion_rate' => $this->rate($servicosAprovadosComTentativa, $servicosComTentativa),
        ];
    }

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        // Força tipo float para manter consistência no assertJsonPath (100 vs 100.0).
        return (float) round(($numerator / $denominator) * 100, 2);
    }
}
