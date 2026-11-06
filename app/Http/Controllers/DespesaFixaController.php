<?php

namespace App\Http\Controllers;

use App\Concerns\HandlesGroupedRecords;
use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DespesaFixaController extends Controller
{
    use HandlesGroupedRecords;

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'descricao'  => 'required|string|max:255',
            'categoria'  => 'required|string|max:255',
            'valor'      => 'required|numeric|min:0',
            'vencimento' => 'required|string|date_format:d/m/Y',
            'status'     => 'required|string|max:255',
            'dataPgto'   => 'nullable|string|date_format:d/m/Y',
            'forma'      => 'nullable|string|max:255',
            'dataLimite' => 'nullable|string|date_format:m/Y',
        ]);

        $vencimento = Carbon::createFromFormat('d/m/Y', $data['vencimento']);
        $dataPgto   = !empty($data['dataPgto']) ? Carbon::createFromFormat('d/m/Y', $data['dataPgto']) : null;
        $dataLimite = !empty($data['dataLimite'])
            ? Carbon::createFromFormat('m/Y', $data['dataLimite'])->startOfMonth()
            : null;

        if ($dataLimite && $vencimento->copy()->startOfMonth()->diffInMonths($dataLimite) > 60) {
            throw ValidationException::withMessages([
                'dataLimite' => 'A data limite não pode ultrapassar 60 meses.',
            ]);
        }

        $base = [
            'descricao' => $data['descricao'],
            'categoria' => $data['categoria'],
            'valor'     => $data['valor'],
            'status'    => $data['status'],
            'forma'     => $data['forma'] ?? null,
        ];

        if ($dataLimite && $dataLimite->gte($vencimento->copy()->startOfMonth())) {
            // Recorrente: um registro por mês, avançando vencimento e pagamento juntos
            $base['grupo_id'] = (string) Str::uuid();
            $offset           = 0;
            $current          = $vencimento->copy();

            while ($current->copy()->startOfMonth()->lte($dataLimite)) {
                $request->user()->despesasFixas()->create($base + [
                    'vencimento' => $current->toDateString(),
                    'data_pgto'  => $dataPgto?->copy()->addMonthsNoOverflow($offset)->toDateString(),
                ]);
                $current = $vencimento->copy()->addMonthsNoOverflow(++$offset);
            }
        } else {
            $request->user()->despesasFixas()->create($base + [
                'vencimento' => $vencimento->toDateString(),
                'data_pgto'  => $dataPgto?->toDateString(),
            ]);
        }

        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'descricao'  => 'required|string|max:255',
            'categoria'  => 'required|string|max:255',
            'valor'      => 'required|numeric|min:0',
            'vencimento' => 'required|string|date_format:d/m/Y',
            'status'     => 'required|string|max:255',
            'dataPgto'   => 'nullable|string|date_format:d/m/Y',
            'forma'      => 'nullable|string|max:255',
        ]);

        $this->updateWithScope($request, $request->user()->despesasFixas(), $id, [
            'descricao'  => $data['descricao'],
            'categoria'  => $data['categoria'],
            'valor'      => $data['valor'],
            'vencimento' => Carbon::createFromFormat('d/m/Y', $data['vencimento'])->toDateString(),
            'status'     => $data['status'],
            'data_pgto'  => !empty($data['dataPgto']) ? Carbon::createFromFormat('d/m/Y', $data['dataPgto'])->toDateString() : null,
            'forma'      => $data['forma'] ?? null,
        ], ['descricao', 'categoria', 'valor', 'forma', 'status']);

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->destroyWithScope($request, $request->user()->despesasFixas(), $id, 'vencimento');

        return back();
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $data = $request->validate([
            "ids"   => "required|array|min:1",
            "ids.*" => "integer",
        ]);

        $request->user()->despesasFixas()->whereIn("id", $data["ids"])->delete();

        return back();
    }
}
