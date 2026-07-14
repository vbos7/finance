<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot access despesas-variaveis routes', function () {
    $this->post(route('despesas-variaveis.store'))->assertRedirect(route('login'));
    $this->put(route('despesas-variaveis.update', 1))->assertRedirect(route('login'));
    $this->delete(route('despesas-variaveis.destroy', 1))->assertRedirect(route('login'));
});

test('store creates a despesa variavel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('despesas-variaveis.store'), [
            'descricao' => 'Supermercado',
            'categoria' => 'Mercado',
            'valor'     => 350,
            'data'      => '10/01/2026',
            'forma'     => 'Cartão de Crédito',
            'balanco'   => '01/2026',
        ])
        ->assertRedirect();

    $despesa = $user->despesasVariaveis()->first();
    expect($despesa)->not->toBeNull();
    expect($despesa->descricao)->toBe('Supermercado');
    expect($despesa->categoria)->toBe('Mercado');
    expect($despesa->data->format('Y-m-d'))->toBe('2026-01-10');
    expect($despesa->balanco->format('Y-m-d'))->toBe('2026-01-01');
});

test('store with recurrence (assinatura) creates monthly records', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('despesas-variaveis.store'), [
            'descricao'  => 'Netflix',
            'categoria'  => 'Assinaturas',
            'valor'      => 45,
            'data'       => '12/01/2026',
            'forma'      => 'Cartão de Crédito',
            'balanco'    => '02/2026',
            'dataLimite' => '06/2026',
        ])
        ->assertRedirect();

    $despesas = $user->despesasVariaveis()->orderBy('balanco')->get();

    expect($despesas)->toHaveCount(5);
    expect($despesas[0]->balanco->format('Y-m-d'))->toBe('2026-02-01');
    expect($despesas[4]->balanco->format('Y-m-d'))->toBe('2026-06-01');
    expect($despesas->every(fn ($d) => $d->descricao === 'Netflix'))->toBeTrue();

    // A data de cobrança avança um mês a cada balanço
    expect($despesas[0]->data->format('Y-m-d'))->toBe('2026-01-12');
    expect($despesas[1]->data->format('Y-m-d'))->toBe('2026-02-12');
    expect($despesas[4]->data->format('Y-m-d'))->toBe('2026-05-12');
});

test('store with parcelas creates installment records', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('despesas-variaveis.store'), [
            'descricao' => 'Notebook',
            'categoria' => 'Shopping',
            'valor'     => 3000,
            'data'      => '15/01/2026',
            'forma'     => 'Cartão de Crédito',
            'balanco'   => '01/2026',
            'parcelas'  => 3,
        ])
        ->assertRedirect();

    $despesas = $user->despesasVariaveis()->orderBy('balanco')->get();

    expect($despesas)->toHaveCount(3);
    expect($despesas[0]->descricao)->toBe('Notebook 1/3');
    expect($despesas[1]->descricao)->toBe('Notebook 2/3');
    expect($despesas[2]->descricao)->toBe('Notebook 3/3');
    expect($despesas[0]->valor)->toBe('1000.00');

    $total = $despesas->sum(fn ($d) => (float) $d->valor);
    expect($total)->toBe(3000.0);
});

test('update modifies a despesa variavel', function () {
    $user    = User::factory()->create();
    $despesa = $user->despesasVariaveis()->create([
        'descricao' => 'Supermercado',
        'categoria' => 'Mercado',
        'valor'     => 350,
        'data'      => '2026-01-10',
        'forma'     => 'Pix',
        'balanco'   => '2026-01-01',
    ]);

    $this->actingAs($user)
        ->put(route('despesas-variaveis.update', $despesa->id), [
            'descricao' => 'Supermercado Atualizado',
            'categoria' => 'Mercado',
            'valor'     => 400,
            'data'      => '12/01/2026',
            'forma'     => 'Cartão de Débito',
            'balanco'   => '01/2026',
        ])
        ->assertRedirect();

    $despesa->refresh();
    expect($despesa->descricao)->toBe('Supermercado Atualizado');
    expect($despesa->valor)->toBe('400.00');
});

test('destroy deletes a despesa variavel', function () {
    $user    = User::factory()->create();
    $despesa = $user->despesasVariaveis()->create([
        'descricao' => 'Supermercado',
        'categoria' => 'Mercado',
        'valor'     => 350,
        'data'      => '2026-01-10',
        'balanco'   => '2026-01-01',
    ]);

    $this->actingAs($user)
        ->delete(route('despesas-variaveis.destroy', $despesa->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('despesas_variaveis', ['id' => $despesa->id]);
});

test('user cannot access another user despesa variavel', function () {
    $user1   = User::factory()->create();
    $user2   = User::factory()->create();
    $despesa = $user1->despesasVariaveis()->create([
        'descricao' => 'Supermercado',
        'categoria' => 'Mercado',
        'valor'     => 350,
        'data'      => '2026-01-10',
        'balanco'   => '2026-01-01',
    ]);

    $this->actingAs($user2)
        ->put(route('despesas-variaveis.update', $despesa->id), [
            'descricao' => 'Hack',
            'categoria' => 'Hack',
            'valor'     => 1,
            'data'      => '10/01/2026',
            'forma'     => 'Pix',
            'balanco'   => '01/2026',
        ])
        ->assertNotFound();

    $this->actingAs($user2)
        ->delete(route('despesas-variaveis.destroy', $despesa->id))
        ->assertNotFound();
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('despesas-variaveis.store'), [])
        ->assertSessionHasErrors(['descricao', 'categoria', 'valor', 'data', 'balanco']);
});

test('store rejects malformed dates', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('despesas-variaveis.store'), [
            'descricao'  => 'Supermercado',
            'categoria'  => 'Mercado',
            'valor'      => 350,
            'data'       => '2026-01-10',
            'balanco'    => 'janeiro',
            'dataLimite' => '32/13',
        ])
        ->assertSessionHasErrors(['data', 'balanco', 'dataLimite']);
});

test('store rejects dataLimite beyond 60 months', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('despesas-variaveis.store'), [
            'descricao'  => 'Netflix',
            'categoria'  => 'Assinaturas',
            'valor'      => 45,
            'data'       => '12/01/2026',
            'balanco'    => '01/2026',
            'dataLimite' => '01/2099',
        ])
        ->assertSessionHasErrors(['dataLimite']);

    expect($user->despesasVariaveis()->count())->toBe(0);
});
