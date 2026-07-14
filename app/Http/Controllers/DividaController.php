<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\ValidationException;

class DividaController extends Controller
{
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
            $current = $vencimento->copy();
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
        $record = $request->user()->dividas()->findOrFail($id);

        $data = $request->validate([
            'descricao'  => 'required|string|max:255',
            'destino'    => 'required|string|max:255',
            'valor'      => 'required|numeric|min:0',
            'vencimento' => 'required|string|date_format:d/m/Y',
            'status'     => 'required|string|max:255',
        ]);

        $data['vencimento'] = Carbon::createFromFormat('d/m/Y', $data['vencimento'])->toDateString();

        $record->update($data);

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $request->user()->dividas()->findOrFail($id)->delete();

        return back();
    }
}
