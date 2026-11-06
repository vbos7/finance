<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MetaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome'  => 'required|string|max:255',
            'icone' => 'nullable|string|max:255',
            'valor' => 'required|numeric|min:0',
        ]);

        $request->user()->metas()->create($data);

        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $record = $request->user()->metas()->findOrFail($id);

        $data = $request->validate([
            'nome'  => 'required|string|max:255',
            'icone' => 'nullable|string|max:255',
            'valor' => 'required|numeric|min:0',
        ]);

        $record->update($data);

        return back();
    }

    public function investir(Request $request, int $id): RedirectResponse
    {
        $meta = $request->user()->metas()->findOrFail($id);

        $data = $request->validate([
            'valor'      => 'required|numeric|min:0.01',
            'data'       => 'required|string|date_format:d/m/Y',
            'dataLimite' => 'nullable|string|date_format:m/Y',
        ]);

        $dataInvest = Carbon::createFromFormat('d/m/Y', $data['data']);
        $dataLimite = !empty($data['dataLimite'])
            ? Carbon::createFromFormat('m/Y', $data['dataLimite'])->startOfMonth()
            : null;

        $base = [
            'produto'    => $meta->nome,
            'empresa'    => '',
            'valor'      => $data['valor'],
            'quantidade' => 1,
            'tipo_ativo' => 'Meta Financeira',
            'provento'   => 0,
            'frequencia' => '',
        ];

        if ($dataLimite && $dataInvest->copy()->startOfMonth()->diffInMonths($dataLimite) > 60) {
            throw ValidationException::withMessages([
                'dataLimite' => 'A data limite não pode ultrapassar 60 meses.',
            ]);
        }

        if ($dataLimite && $dataLimite->gte($dataInvest->copy()->startOfMonth())) {
            $base['grupo_id'] = (string) Str::uuid();
            $current          = $dataInvest->copy();
            while ($current->copy()->startOfMonth()->lte($dataLimite)) {
                $rec         = $base;
                $rec['data'] = $current->toDateString();
                $request->user()->investimentos()->create($rec);
                $current->addMonth();
            }
        } else {
            $base['data'] = $dataInvest->toDateString();
            $request->user()->investimentos()->create($base);
        }

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $meta = $request->user()->metas()->findOrFail($id);

        // O que já foi aportado na meta continua sendo patrimônio: os aportes
        // viram investimentos comuns (tipo "Outros") em vez de sumirem junto
        DB::transaction(function () use ($request, $meta) {
            $request->user()->investimentos()
                ->where('tipo_ativo', 'Meta Financeira')
                ->where('produto', $meta->nome)
                ->update(['tipo_ativo' => 'Outros']);

            $meta->delete();
        });

        return back();
    }
}
