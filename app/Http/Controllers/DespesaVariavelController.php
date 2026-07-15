<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\ValidationException;

class DespesaVariavelController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'descricao'  => 'required|string|max:255',
            'categoria'  => 'required|string|max:255',
            'valor'      => 'required|numeric|min:0',
            'data'       => 'required|string|date_format:d/m/Y',
            'forma'      => 'nullable|string|max:255',
            'balanco'    => 'required|string|date_format:m/Y',
            'parcelas'   => 'nullable|integer|min:1|max:12',
            'dataLimite' => 'nullable|string|date_format:m/Y',
        ]);

        $data['data'] = Carbon::createFromFormat('d/m/Y', $data['data'])->toDateString();
        $balancoDate  = Carbon::createFromFormat('m/Y', $data['balanco'])->startOfMonth();
        $parcelas     = (int) ($data['parcelas'] ?? 1);
        $dataLimite   = !empty($data['dataLimite'])
            ? Carbon::createFromFormat('m/Y', $data['dataLimite'])->startOfMonth()
            : null;

        unset($data['balanco'], $data['parcelas'], $data['dataLimite']);

        if ($dataLimite && $balancoDate->diffInMonths($dataLimite) > 60) {
            throw ValidationException::withMessages([
                'dataLimite' => 'A data limite não pode ultrapassar 60 meses.',
            ]);
        }

        if ($dataLimite && $dataLimite->gte($balancoDate)) {
            // Assinatura: criar mesmo registro em cada mês até data limite,
            // avançando a data de cobrança junto com o balanço
            $dataBase = Carbon::parse($data['data']);
            $current  = $balancoDate->copy();
            $offset   = 0;
            while ($current->lte($dataLimite)) {
                $rec            = $data;
                $rec['balanco'] = $current->toDateString();
                $rec['data']    = $dataBase->copy()->addMonthsNoOverflow($offset)->toDateString();
                $request->user()->despesasVariaveis()->create($rec);
                $current->addMonth();
                $offset++;
            }
        } elseif ($parcelas <= 1) {
            $data['balanco'] = $balancoDate->toDateString();
            $request->user()->despesasVariaveis()->create($data);
        } else {
            $total        = (float) $data['valor'];
            $valorParcela = round($total / $parcelas, 2);
            $descOriginal = $data['descricao'];

            for ($i = 0; $i < $parcelas; $i++) {
                $parcelaData              = $data;
                $parcelaData['descricao'] = "{$descOriginal} " . ($i + 1) . "/{$parcelas}";
                $parcelaData['balanco']   = $balancoDate->copy()->addMonths($i)->toDateString();
                $parcelaData['valor']     = $valorParcela;

                // Ajustar centavos na última parcela
                if ($i === $parcelas - 1) {
                    $parcelaData['valor'] = round($total - ($valorParcela * ($parcelas - 1)), 2);
                }

                $request->user()->despesasVariaveis()->create($parcelaData);
            }
        }

        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $record = $request->user()->despesasVariaveis()->findOrFail($id);

        $data = $request->validate([
            'descricao' => 'required|string|max:255',
            'categoria' => 'required|string|max:255',
            'valor'     => 'required|numeric|min:0',
            'data'      => 'required|string|date_format:d/m/Y',
            'forma'     => 'nullable|string|max:255',
            'balanco'   => 'required|string|date_format:m/Y',
        ]);

        $data['data']    = Carbon::createFromFormat('d/m/Y', $data['data'])->toDateString();
        $data['balanco'] = Carbon::createFromFormat('m/Y', $data['balanco'])->startOfMonth()->toDateString();

        $record->update($data);

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $request->user()->despesasVariaveis()->findOrFail($id)->delete();

        return back();
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $data = $request->validate([
            "ids"   => "required|array|min:1",
            "ids.*" => "integer",
        ]);

        $request->user()->despesasVariaveis()->whereIn("id", $data["ids"])->delete();

        return back();
    }
}
