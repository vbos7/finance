<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request};

class DespesaFixaController extends Controller
{
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
        ]);

        $request->user()->despesasFixas()->create([
            'descricao'  => $data['descricao'],
            'categoria'  => $data['categoria'],
            'valor'      => $data['valor'],
            'vencimento' => Carbon::createFromFormat('d/m/Y', $data['vencimento'])->toDateString(),
            'status'     => $data['status'],
            'data_pgto'  => !empty($data['dataPgto']) ? Carbon::createFromFormat('d/m/Y', $data['dataPgto'])->toDateString() : null,
            'forma'      => $data['forma'] ?? null,
        ]);

        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $record = $request->user()->despesasFixas()->findOrFail($id);

        $data = $request->validate([
            'descricao'  => 'required|string|max:255',
            'categoria'  => 'required|string|max:255',
            'valor'      => 'required|numeric|min:0',
            'vencimento' => 'required|string|date_format:d/m/Y',
            'status'     => 'required|string|max:255',
            'dataPgto'   => 'nullable|string|date_format:d/m/Y',
            'forma'      => 'nullable|string|max:255',
        ]);

        $record->update([
            'descricao'  => $data['descricao'],
            'categoria'  => $data['categoria'],
            'valor'      => $data['valor'],
            'vencimento' => Carbon::createFromFormat('d/m/Y', $data['vencimento'])->toDateString(),
            'status'     => $data['status'],
            'data_pgto'  => !empty($data['dataPgto']) ? Carbon::createFromFormat('d/m/Y', $data['dataPgto'])->toDateString() : null,
            'forma'      => $data['forma'] ?? null,
        ]);

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $request->user()->despesasFixas()->findOrFail($id)->delete();

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
