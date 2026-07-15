<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\ValidationException;

class GanhoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'descricao'  => 'required|string|max:255',
            'fonte'      => 'required|string|max:255',
            'data'       => 'required|string|date_format:d/m/Y',
            'valor'      => 'required|numeric|min:0',
            'dataLimite' => 'nullable|string|date_format:m/Y',
        ]);

        $dataGanho  = Carbon::createFromFormat('d/m/Y', $data['data']);
        $dataLimite = !empty($data['dataLimite'])
            ? Carbon::createFromFormat('m/Y', $data['dataLimite'])->startOfMonth()
            : null;

        unset($data['dataLimite']);
        $data['data'] = $dataGanho->toDateString();

        if ($dataLimite && $dataGanho->copy()->startOfMonth()->diffInMonths($dataLimite) > 60) {
            throw ValidationException::withMessages([
                'dataLimite' => 'A data limite não pode ultrapassar 60 meses.',
            ]);
        }

        if ($dataLimite && $dataLimite->gte($dataGanho->copy()->startOfMonth())) {
            $current = $dataGanho->copy();
            while ($current->copy()->startOfMonth()->lte($dataLimite)) {
                $rec         = $data;
                $rec['data'] = $current->toDateString();
                $request->user()->ganhos()->create($rec);
                $current->addMonth();
            }
        } else {
            $request->user()->ganhos()->create($data);
        }

        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $ganho = $request->user()->ganhos()->findOrFail($id);

        $data = $request->validate([
            'descricao' => 'required|string|max:255',
            'fonte'     => 'required|string|max:255',
            'data'      => 'required|string|date_format:d/m/Y',
            'valor'     => 'required|numeric|min:0',
        ]);

        $data['data'] = Carbon::createFromFormat('d/m/Y', $data['data'])->toDateString();

        $ganho->update($data);

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $request->user()->ganhos()->findOrFail($id)->delete();

        return back();
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $data = $request->validate([
            "ids"   => "required|array|min:1",
            "ids.*" => "integer",
        ]);

        $request->user()->ganhos()->whereIn("id", $data["ids"])->delete();

        return back();
    }
}
