<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()) {
            return Inertia::render('welcome');
        }

        $user = $request->user();
        $ano  = (int) $request->query('ano', now()->year);

        $ganhos = $user->ganhos()->whereYear('data', $ano)->orderBy('data')->get();
        // Fixas aparecem no balanço do mês de pagamento; se ainda não pagas, no mês de vencimento
        $fixas = $user->despesasFixas()
            ->where(function ($q) use ($ano) {
                $q->where(fn ($q) => $q->whereNotNull('data_pgto')->whereYear('data_pgto', $ano))
                  ->orWhere(fn ($q) => $q->whereNull('data_pgto')->whereYear('vencimento', $ano));
            })
            ->orderBy('vencimento')->get();
        $variaveis     = $user->despesasVariaveis()->whereYear('balanco', $ano)->orderBy('balanco')->get();
        $dividas       = $user->dividas()->whereYear('vencimento', $ano)->orderBy('vencimento')->get();
        $investimentos = $user->investimentos()->whereYear('data', $ano)->orderBy('data')->get();
        $metas         = $user->metas()->orderBy('nome')->get();

        $configFontes     = $user->fontesRenda()->orderBy('nome')->get();
        $configCategorias = $user->categorias()->orderBy('nome')->get();
        $configFormas     = $user->formasPagamento()->orderBy('nome')->get();

        $meses = [
            'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
        ];

        // Balanço mensal
        $balancoMensal = collect($meses)->map(function ($mes, $i) use ($ganhos, $fixas, $variaveis, $dividas) {
            $month   = $i + 1;
            $receita = $ganhos->filter(fn ($g) => $g->data->month === $month)->sum('valor');
            $despesa = $fixas->filter(fn ($d) => ($d->data_pgto ?? $d->vencimento)->month === $month)->sum('valor')
                     + $variaveis->filter(fn ($d) => $d->balanco->month === $month)->sum('valor')
                     + $dividas->filter(fn ($d) => $d->vencimento->month === $month)->sum('valor');

            return ['mes' => $mes, 'receita' => round($receita, 2), 'despesa' => round($despesa, 2)];
        })->values();

        // Fontes de renda (agrupa ganhos por fonte + config)
        $ganhosPorFonte = $ganhos->groupBy('fonte');
        $fontes         = $configFontes->map(function ($f) use ($ganhosPorFonte) {
            $receitaAnual = $ganhosPorFonte->has($f->nome) ? round($ganhosPorFonte[$f->nome]->sum('valor'), 2) : 0;
            $metaAnual    = (float) $f->meta_anual ?: 0;
            $percent      = $metaAnual > 0 ? round(($receitaAnual / $metaAnual) * 100) : 0;

            return [
                'id'           => $f->id,
                'nome'         => $f->nome,
                'icone'        => $f->icone,
                'percent'      => $percent,
                'metaAnual'    => $metaAnual,
                'receitaAnual' => $receitaAnual,
            ];
        });

        // Categorias (agrupa despesas por categoria + config)
        $todasDespesas = $fixas->concat($variaveis);
        $despPorCateg  = $todasDespesas->groupBy('categoria');
        $categorias    = $configCategorias->map(function ($c) use ($despPorCateg) {
            $desp = $despPorCateg->has($c->nome) ? round($despPorCateg[$c->nome]->sum('valor'), 2) : 0;
            $lim  = $c->limite_anual ? (float) $c->limite_anual : null;
            $pct  = $lim ? round(($desp / $lim) * 100) : 0;

            return ['id' => $c->id, 'nome' => $c->nome, 'icone' => $c->icone, 'pct' => $pct, 'lim' => $lim, 'desp' => $desp];
        });

        // Formas de pagamento (agrupa despesas por forma + config)
        $despPorForma = $todasDespesas->groupBy('forma');
        $formas       = $configFormas->map(function ($f) use ($despPorForma) {
            $desp = $despPorForma->has($f->nome) ? round($despPorForma[$f->nome]->sum('valor'), 2) : 0;
            $lim  = $f->limite_anual ? (float) $f->limite_anual : 0;
            $pct  = $lim > 0 ? round(($desp / $lim) * 100) : 0;

            return ['id' => $f->id, 'nome' => $f->nome, 'icone' => $f->icone, 'pct' => $pct, 'lim' => $lim, 'desp' => $desp];
        });

        $fmtDate = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '';

        return Inertia::render('home', [
            'ano'           => $ano,
            'balancoMensal' => $balancoMensal,
            'ganhos'        => $ganhos->map(fn ($g) => [
                'id'        => $g->id,
                'descricao' => $g->descricao,
                'fonte'     => $g->fonte,
                'data'      => $fmtDate($g->data),
                'valor'     => (float) $g->valor,
                'balanco'   => $meses[$g->data->month - 1],
            ])->values(),
            'fixas' => $fixas->map(fn ($d) => [
                'id'         => $d->id,
                'descricao'  => $d->descricao,
                'categoria'  => $d->categoria,
                'valor'      => (float) $d->valor,
                'vencimento' => $fmtDate($d->vencimento),
                'status'     => $d->status,
                'dataPgto'   => $fmtDate($d->data_pgto),
                'forma'      => $d->forma ?? '',
                'balanco'    => $meses[($d->data_pgto ?? $d->vencimento)->month - 1],
            ])->values(),
            'variaveis' => $variaveis->map(fn ($d) => [
                'id'        => $d->id,
                'descricao' => $d->descricao,
                'categoria' => $d->categoria,
                'valor'     => (float) $d->valor,
                'data'      => $fmtDate($d->data),
                'forma'     => $d->forma ?? '',
                'balanco'   => $d->balanco->format('m/Y'),
            ])->values(),
            'dividas' => $dividas->map(fn ($d) => [
                'id'         => $d->id,
                'descricao'  => $d->descricao,
                'destino'    => $d->destino,
                'valor'      => (float) $d->valor,
                'vencimento' => $fmtDate($d->vencimento),
                'status'     => $d->status,
                'balanco'    => $meses[$d->vencimento->month - 1],
            ])->values(),
            'investimentos' => $investimentos->map(fn ($inv) => [
                'id'         => $inv->id,
                'produto'    => $inv->produto,
                'empresa'    => $inv->empresa,
                'valor'      => (float) $inv->valor,
                'quantidade' => $inv->quantidade,
                'valorTotal' => round((float) $inv->valor * $inv->quantidade, 2),
                'tipoAtivo'  => $inv->tipo_ativo,
                'provento'   => (float) $inv->provento,
                'frequencia' => $inv->frequencia,
                'data'       => $fmtDate($inv->data),
                'balanco'    => $meses[$inv->data->month - 1],
            ])->values(),
            'metas' => $metas->map(function ($m) use ($investimentos) {
                $investido = round($investimentos
                    ->where('tipo_ativo', 'Meta Financeira')
                    ->where('produto', $m->nome)
                    ->sum('valor'), 2);
                $percent = $m->valor > 0 ? round(($investido / (float) $m->valor) * 100) : 0;

                return [
                    'id'        => $m->id,
                    'nome'      => $m->nome,
                    'icone'     => $m->icone,
                    'percent'   => $percent,
                    'valor'     => (float) $m->valor,
                    'investido' => $investido,
                    'faltante'  => round((float) $m->valor - $investido, 2),
                ];
            })->values(),
            'fontes'           => $fontes->values(),
            'categorias'       => $categorias->values(),
            'formas'           => $formas->values(),
            'configFontes'     => $configFontes->pluck('nome')->values(),
            'configCategorias' => $configCategorias->pluck('nome')->values(),
            'configFormas'     => $configFormas->pluck('nome')->values(),
        ]);
    }
}
