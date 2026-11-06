<?php

namespace App\Http\Controllers;

use App\Concerns\HandlesGroupedRecords;
use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DividaController extends Controller
{
    use HandlesGroupedRecords;

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'descricao'  => 'required|string|max:255',
            'destino'    => 'required|string|max:255',
            'valor'      => 'required|numeric|min:0',
            'vencimento' => 'required|string|date_format:d/m/Y',
            'status'     => 'required|string|max:255',
            'dataLimite' => 'nullable|string|date_format:m/Y',
        ]);

        $vencimento = Carbon::createFromFormat('d/m/Y', $data['vencimento']);
        $dataLimite = !empty($data['dataLimite'])
            ? Carbon::createFromFormat('m/Y', $data['dataLimite'])->startOfMonth()
            : null;

        unset($data['dataLimite']);
        $data['vencimento'] = $vencimento->toDateString();

        if ($dataLimite && $vencimento->copy()->startOfMonth()->diffInMonths($dataLimite) > 60) {
            throw ValidationException::withMessages([
                'dataLimite' => 'A data limite não pode ultrapassar 60 meses.',
            ]);
        }

        if ($dataLimite && $dataLimite->gte($vencimento->copy()->startOfMonth())) {
            $data['grupo_id'] = (string) Str::uuid();
            $current          = $vencimento->copy();
            while ($current->copy()->startOfMonth()->lte($dataLimite)) {
                $rec               = $data;
                $rec['vencimento'] = $current->toDateString();
                $request->user()->dividas()->create($rec);
                $current->addMonth();
            }
        } else {
            $request->user()->dividas()->create($data);
        }

        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'descricao'  => 'required|string|max:255',
            'destino'    => 'required|string|max:255',
            'valor'      => 'required|numeric|min:0',
            'vencimento' => 'required|string|date_format:d/m/Y',
            'status'     => 'required|string|max:255',
        ]);

        $data['vencimento'] = Carbon::createFromFormat('d/m/Y', $data['vencimento'])->toDateString();

        $this->updateWithScope($request, $request->user()->dividas(), $id, $data, ['descricao', 'destino', 'valor', 'status']);

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->destroyWithScope($request, $request->user()->dividas(), $id, 'vencimento');

        return back();
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $data = $request->validate([
            "ids"   => "required|array|min:1",
            "ids.*" => "integer",
        ]);

        $request->user()->dividas()->whereIn("id", $data["ids"])->delete();

        return back();
    }
}
