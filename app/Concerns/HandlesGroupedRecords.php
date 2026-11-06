<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * Lançamentos criados no mesmo lote (recorrência, parcelamento ou assinatura)
 * compartilham um grupo_id, o que permite excluir só um item ou o grupo inteiro.
 */
trait HandlesGroupedRecords
{
    /**
     * Exclui o lançamento informado. Com escopo "grupo", remove também os
     * lançamentos vinculados a partir dele (mesma data ou posterior em
     * $colunaData) — os anteriores ficam preservados. Sem grupo_id, o escopo
     * é ignorado.
     *
     * @template TModel of Model
     *
     * @param  HasMany<TModel, \App\Models\User>  $relation
     */
    protected function destroyWithScope(Request $request, HasMany $relation, int $id, string $colunaData): void
    {
        $escopo = $request->validate([
            'escopo' => 'nullable|in:registro,grupo',
        ])['escopo'] ?? 'registro';

        // Clonar preserva a query base: findOrFail adiciona "where id" ao builder
        // da relation, o que contaminaria a exclusão do grupo logo abaixo
        $record  = (clone $relation)->findOrFail($id);
        $grupoId = $record->getAttribute('grupo_id');

        if ($escopo === 'grupo' && $grupoId !== null) {
            (clone $relation)
                ->where('grupo_id', $grupoId)
                ->whereDate($colunaData, '>=', $record->getAttribute($colunaData))
                ->delete();

            return;
        }

        $record->delete();
    }

    /**
     * Atualiza o lançamento informado. Com escopo "grupo", replica nos vinculados
     * apenas os campos listados em $atributosDeGrupo que realmente mudaram —
     * campos de data ficam de fora da lista, pois são individuais de cada mês.
     *
     * @template TModel of Model
     *
     * @param  HasMany<TModel, \App\Models\User>  $relation
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $atributosDeGrupo
     */
    protected function updateWithScope(Request $request, HasMany $relation, int $id, array $attributes, array $atributosDeGrupo): void
    {
        $escopo = $request->validate([
            'escopo' => 'nullable|in:registro,grupo',
        ])['escopo'] ?? 'registro';

        $record  = (clone $relation)->findOrFail($id);
        $grupoId = $record->getAttribute('grupo_id');

        $mudancas = [];

        if ($escopo === 'grupo' && $grupoId !== null) {
            foreach ($atributosDeGrupo as $campo) {
                if (array_key_exists($campo, $attributes) && $record->getAttribute($campo) != $attributes[$campo]) {
                    $mudancas[$campo] = $attributes[$campo];
                }
            }
        }

        $record->update($attributes);

        if ($mudancas !== []) {
            (clone $relation)
                ->where('grupo_id', $grupoId)
                ->whereKeyNot($record->getKey())
                ->update($mudancas);
        }
    }
}
