<?php

namespace App\Http\Controllers;

use Illuminate\Http\{RedirectResponse, Request};

class FonteRendaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome'       => 'required|string|max:255',
            'icone'      => 'nullable|string|max:255',
            'meta_anual' => 'nullable|numeric|min:0',
        ]);

        $request->user()->fontesRenda()->create($data);

        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $record = $request->user()->fontesRenda()->findOrFail($id);

        $data = $request->validate([
            'nome'       => 'required|string|max:255',
            'icone'      => 'nullable|string|max:255',
            'meta_anual' => 'nullable|numeric|min:0',
        ]);

        $record->update($data);

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $request->user()->fontesRenda()->findOrFail($id)->delete();

        return back();
    }
}
