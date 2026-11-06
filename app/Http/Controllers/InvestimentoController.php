<?php

namespace App\Http\Controllers;

use App\Concerns\HandlesGroupedRecords;
use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvestimentoController extends Controller
{
    use HandlesGroupedRecords;

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'produto'    => 'required|string|max:255',
            'empresa'    => 'required|string|max:255',
            'valor'      => 'required|numeric|min:0',
            'quantidade' => 'required|integer|min:1',
            'tipoAtivo'  => 'required|string|max:255',
            'provento'   => 'nullable|numeric|min:0',
            'frequencia' => 'required|string|max:255',
            'data'       => 'required|string|date_format:d/m/Y',
            'dataLimite' => 'nullable|string|date_format:m/Y',
        ]);

        $dataInvest = Carbon::createFromFormat('d/m/Y', $data['data']);
        $dataLimite = !empty($data['dataLimite'])
            ? Carbon::createFromFormat('m/Y', $data['dataLimite'])->startOfMonth()
            : null;

        $base = [
            'produto'    => $data['produto'],
            'empresa'    => $data['empresa'],
            'valor'      => $data['valor'],
            'quantidade' => $data['quantidade'],
            'tipo_ativo' => $data['tipoAtivo'],
            'provento'   => $data['provento'] ?? 0,
            'frequencia' => $data['frequencia'],
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

    public function update(Request $request, int $id): RedirectResponse
    {
        $camposDeGrupo = ['produto', 'empresa', 'valor', 'quantidade', 'tipo_ativo', 'provento', 'frequencia'];

        // Edição pela aba "Meta Financeira": o registro vira (ou continua) um aporte na meta
        if ($request->filled('metaId')) {
            $data = $request->validate([
                'metaId' => 'required|integer',
                'valor'  => 'required|numeric|min:0.01',
                'data'   => 'required|string|date_format:d/m/Y',
            ]);

            $meta = $request->user()->metas()->findOrFail($data['metaId']);

            $this->updateWithScope($request, $request->user()->investimentos(), $id, [
                'produto'    => $meta->nome,
                'empresa'    => '',
                'valor'      => $data['valor'],
                'quantidade' => 1,
                'tipo_ativo' => 'Meta Financeira',
                'provento'   => 0,
                'frequencia' => '',
                'data'       => Carbon::createFromFormat('d/m/Y', $data['data'])->toDateString(),
            ], $camposDeGrupo);

            return back();
        }

        $data = $request->validate([
            'produto'    => 'required|string|max:255',
            'empresa'    => 'required|string|max:255',
            'valor'      => 'required|numeric|min:0',
            'quantidade' => 'required|integer|min:1',
            'tipoAtivo'  => 'required|string|max:255',
            'provento'   => 'nullable|numeric|min:0',
            'frequencia' => 'required|string|max:255',
            'data'       => 'required|string|date_format:d/m/Y',
        ]);

        $this->updateWithScope($request, $request->user()->investimentos(), $id, [
            'produto'    => $data['produto'],
            'empresa'    => $data['empresa'],
            'valor'      => $data['valor'],
            'quantidade' => $data['quantidade'],
            'tipo_ativo' => $data['tipoAtivo'],
            'provento'   => $data['provento'] ?? 0,
            'frequencia' => $data['frequencia'],
            'data'       => Carbon::createFromFormat('d/m/Y', $data['data'])->toDateString(),
        ], $camposDeGrupo);

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->destroyWithScope($request, $request->user()->investimentos(), $id, 'data');

        return back();
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $data = $request->validate([
            "ids"   => "required|array|min:1",
            "ids.*" => "integer",
        ]);

        $request->user()->investimentos()->whereIn("id", $data["ids"])->delete();

        return back();
    }
}
