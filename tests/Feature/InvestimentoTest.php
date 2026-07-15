<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot access investimentos routes', function () {
    $this->post(route('investimentos.store'))->assertRedirect(route('login'));
    $this->put(route('investimentos.update', 1))->assertRedirect(route('login'));
    $this->delete(route('investimentos.destroy', 1))->assertRedirect(route('login'));
});

test('store creates an investimento', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investimentos.store'), [
            'produto'    => 'PETR4',
            'empresa'    => 'Petrobras',
            'valor'      => 30,
            'quantidade' => 100,
            'tipoAtivo'  => 'Ação',
            'provento'   => 1.50,
            'frequencia' => 'Trimestral',
            'data'       => '10/01/2026',
        ])
        ->assertRedirect();

    $inv = $user->investimentos()->first();
    expect($inv)->not->toBeNull();
    expect($inv->produto)->toBe('PETR4');
    expect($inv->empresa)->toBe('Petrobras');
    expect($inv->tipo_ativo)->toBe('Ação');
    expect($inv->data->format('Y-m-d'))->toBe('2026-01-10');
    expect($inv->quantidade)->toBe(100);
});

test('store with recurrence creates monthly records', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investimentos.store'), [
            'produto'    => 'Tesouro Selic',
            'empresa'    => 'Tesouro Nacional',
            'valor'      => 500,
            'quantidade' => 1,
            'tipoAtivo'  => 'Renda Fixa',
            'provento'   => 0,
            'frequencia' => 'Mensal',
            'data'       => '05/01/2026',
            'dataLimite' => '03/2026',
        ])
        ->assertRedirect();

    $investimentos = $user->investimentos()->orderBy('data')->get();

    expect($investimentos)->toHaveCount(3);
    expect($investimentos[0]->data->format('Y-m-d'))->toBe('2026-01-05');
    expect($investimentos[1]->data->format('Y-m-d'))->toBe('2026-02-05');
    expect($investimentos[2]->data->format('Y-m-d'))->toBe('2026-03-05');
    expect($investimentos->every(fn ($i) => $i->produto === 'Tesouro Selic'))->toBeTrue();
});

test('update modifies an investimento', function () {
    $user = User::factory()->create();
    $inv  = $user->investimentos()->create([
        'produto'    => 'PETR4',
        'empresa'    => 'Petrobras',
        'valor'      => 30,
        'quantidade' => 100,
        'tipo_ativo' => 'Ação',
        'provento'   => 1.50,
        'frequencia' => 'Trimestral',
        'data'       => '2026-01-10',
    ]);

    $this->actingAs($user)
        ->put(route('investimentos.update', $inv->id), [
            'produto'    => 'PETR4',
            'empresa'    => 'Petrobras',
            'valor'      => 35,
            'quantidade' => 200,
            'tipoAtivo'  => 'Ação',
            'provento'   => 2.00,
            'frequencia' => 'Trimestral',
            'data'       => '15/01/2026',
        ])
        ->assertRedirect();

    $inv->refresh();
    expect($inv->valor)->toBe('35.00');
    expect($inv->quantidade)->toBe(200);
    expect($inv->data->format('Y-m-d'))->toBe('2026-01-15');
});

test('destroy deletes an investimento', function () {
    $user = User::factory()->create();
    $inv  = $user->investimentos()->create([
        'produto'    => 'PETR4',
        'empresa'    => 'Petrobras',
        'valor'      => 30,
        'quantidade' => 100,
        'tipo_ativo' => 'Ação',
        'provento'   => 0,
        'frequencia' => 'Mensal',
        'data'       => '2026-01-10',
    ]);

    $this->actingAs($user)
        ->delete(route('investimentos.destroy', $inv->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('investimentos', ['id' => $inv->id]);
});

test('user cannot access another user investimento', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $inv   = $user1->investimentos()->create([
        'produto'    => 'PETR4',
        'empresa'    => 'Petrobras',
        'valor'      => 30,
        'quantidade' => 100,
        'tipo_ativo' => 'Ação',
        'provento'   => 0,
        'frequencia' => 'Mensal',
        'data'       => '2026-01-10',
    ]);

    $this->actingAs($user2)
        ->put(route('investimentos.update', $inv->id), [
            'produto'    => 'Hack',
            'empresa'    => 'Hack',
            'valor'      => 9999,
            'quantidade' => 1,
            'tipoAtivo'  => 'Hack',
            'provento'   => 0,
            'frequencia' => 'Mensal',
            'data'       => '10/01/2026',
        ])
        ->assertNotFound();

    $this->actingAs($user2)
        ->delete(route('investimentos.destroy', $inv->id))
        ->assertNotFound();
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investimentos.store'), [])
        ->assertSessionHasErrors(['produto', 'empresa', 'valor', 'quantidade', 'tipoAtivo', 'frequencia', 'data']);
});

test('bulk destroy deletes selected investimentos and ignores other users records', function () {
    $user   = User::factory()->create();
    $other  = User::factory()->create();
    $base   = ['empresa' => 'Empresa', 'valor' => 100, 'quantidade' => 1, 'tipo_ativo' => 'Ação', 'provento' => 0, 'frequencia' => 'Mensal', 'data' => '2026-01-05'];
    $i1     = $user->investimentos()->create(['produto' => 'I1'] + $base);
    $i2     = $user->investimentos()->create(['produto' => 'I2'] + $base);
    $alheio = $other->investimentos()->create(['produto' => 'Alheio'] + $base);

    $this->actingAs($user)
        ->delete(route('investimentos.bulk-destroy'), ['ids' => [$i1->id, $i2->id, $alheio->id]])
        ->assertRedirect();

    $this->assertDatabaseMissing('investimentos', ['id' => $i1->id]);
    $this->assertDatabaseMissing('investimentos', ['id' => $i2->id]);
    $this->assertDatabaseHas('investimentos', ['id' => $alheio->id]);
});
