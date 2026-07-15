<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot access dividas routes', function () {
    $this->post(route('dividas.store'))->assertRedirect(route('login'));
    $this->put(route('dividas.update', 1))->assertRedirect(route('login'));
    $this->delete(route('dividas.destroy', 1))->assertRedirect(route('login'));
});

test('store creates a divida', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dividas.store'), [
            'descricao'  => 'Cartão de Crédito',
            'destino'    => 'Banco do Brasil',
            'valor'      => 1500,
            'vencimento' => '10/02/2026',
            'status'     => 'Pendente',
        ])
        ->assertRedirect();

    $divida = $user->dividas()->first();
    expect($divida)->not->toBeNull();
    expect($divida->descricao)->toBe('Cartão de Crédito');
    expect($divida->destino)->toBe('Banco do Brasil');
    expect($divida->vencimento->format('Y-m-d'))->toBe('2026-02-10');
    expect($divida->status)->toBe('Pendente');
});

test('store with recurrence creates monthly records', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dividas.store'), [
            'descricao'  => 'Empréstimo',
            'destino'    => 'Banco',
            'valor'      => 800,
            'vencimento' => '05/01/2026',
            'status'     => 'Pendente',
            'dataLimite' => '06/2026',
        ])
        ->assertRedirect();

    $dividas = $user->dividas()->orderBy('vencimento')->get();

    expect($dividas)->toHaveCount(6);
    expect($dividas[0]->vencimento->format('Y-m-d'))->toBe('2026-01-05');
    expect($dividas[5]->vencimento->format('Y-m-d'))->toBe('2026-06-05');
    expect($dividas->every(fn ($d) => $d->descricao === 'Empréstimo'))->toBeTrue();
});

test('update modifies a divida', function () {
    $user   = User::factory()->create();
    $divida = $user->dividas()->create([
        'descricao'  => 'Cartão',
        'destino'    => 'Banco',
        'valor'      => 500,
        'vencimento' => '2026-02-10',
        'status'     => 'Pendente',
    ]);

    $this->actingAs($user)
        ->put(route('dividas.update', $divida->id), [
            'descricao'  => 'Cartão Atualizado',
            'destino'    => 'Outro Banco',
            'valor'      => 600,
            'vencimento' => '15/02/2026',
            'status'     => 'Pago',
        ])
        ->assertRedirect();

    $divida->refresh();
    expect($divida->descricao)->toBe('Cartão Atualizado');
    expect($divida->status)->toBe('Pago');
    expect($divida->vencimento->format('Y-m-d'))->toBe('2026-02-15');
});

test('destroy deletes a divida', function () {
    $user   = User::factory()->create();
    $divida = $user->dividas()->create([
        'descricao'  => 'Cartão',
        'destino'    => 'Banco',
        'valor'      => 500,
        'vencimento' => '2026-02-10',
        'status'     => 'Pendente',
    ]);

    $this->actingAs($user)
        ->delete(route('dividas.destroy', $divida->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('dividas', ['id' => $divida->id]);
});

test('user cannot access another user divida', function () {
    $user1  = User::factory()->create();
    $user2  = User::factory()->create();
    $divida = $user1->dividas()->create([
        'descricao'  => 'Cartão',
        'destino'    => 'Banco',
        'valor'      => 500,
        'vencimento' => '2026-02-10',
        'status'     => 'Pendente',
    ]);

    $this->actingAs($user2)
        ->put(route('dividas.update', $divida->id), [
            'descricao'  => 'Hack',
            'destino'    => 'Hack',
            'valor'      => 9999,
            'vencimento' => '10/02/2026',
            'status'     => 'Pago',
        ])
        ->assertNotFound();

    $this->actingAs($user2)
        ->delete(route('dividas.destroy', $divida->id))
        ->assertNotFound();
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dividas.store'), [])
        ->assertSessionHasErrors(['descricao', 'destino', 'valor', 'vencimento', 'status']);
});

test('bulk destroy deletes selected dividas and ignores other users records', function () {
    $user   = User::factory()->create();
    $other  = User::factory()->create();
    $d1     = $user->dividas()->create(['descricao' => 'D1', 'destino' => 'Banco', 'valor' => 100, 'vencimento' => '2026-01-05', 'status' => 'Pendente']);
    $d2     = $user->dividas()->create(['descricao' => 'D2', 'destino' => 'Banco', 'valor' => 200, 'vencimento' => '2026-01-06', 'status' => 'Pendente']);
    $alheio = $other->dividas()->create(['descricao' => 'Alheio', 'destino' => 'Banco', 'valor' => 300, 'vencimento' => '2026-01-07', 'status' => 'Pendente']);

    $this->actingAs($user)
        ->delete(route('dividas.bulk-destroy'), ['ids' => [$d1->id, $d2->id, $alheio->id]])
        ->assertRedirect();

    $this->assertDatabaseMissing('dividas', ['id' => $d1->id]);
    $this->assertDatabaseMissing('dividas', ['id' => $d2->id]);
    $this->assertDatabaseHas('dividas', ['id' => $alheio->id]);
});
