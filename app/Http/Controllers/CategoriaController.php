<?php

namespace App\Http\Controllers;

use Illuminate\Http\{RedirectResponse, Request};

class CategoriaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome'         => 'required|string|max:255',
            'icone'        => 'nullable|string|max:255',
            'limite_anual' => 'nullable|numeric|min:0',
        ]);

        $request->user()->categorias()->create($data);

        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $record = $request->user()->categorias()->findOrFail($id);

        $data = $request->validate([
            'nome'         => 'required|string|max:255',
            'icone'        => 'nullable|string|max:255',
            'limite_anual' => 'nullable|numeric|min:0',
        ]);

        $record->update($data);

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $request->user()->categorias()->findOrFail($id)->delete();

        return back();
    }
}
