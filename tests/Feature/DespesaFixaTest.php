<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot access despesas-fixas routes', function () {
    $this->post(route('despesas-fixas.store'))->assertRedirect(route('login'));
    $this->put(route('despesas-fixas.update', 1))->assertRedirect(route('login'));
    $this->delete(route('despesas-fixas.destroy', 1))->assertRedirect(route('login'));
});

test('store creates a despesa fixa', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('despesas-fixas.store'), [
            'descricao'  => 'Aluguel',
            'categoria'  => 'Casa',
            'valor'      => 2000,
            'vencimento' => '10/01/2026',
            'status'     => 'Pendente',
            'dataPgto'   => '',
            'forma'      => 'Pix',
        ])
        ->assertRedirect();

    $despesa = $user->despesasFixas()->first();
    expect($despesa)->not->toBeNull();
    expect($despesa->descricao)->toBe('Aluguel');
    expect($despesa->categoria)->toBe('Casa');
    expect($despesa->vencimento->format('Y-m-d'))->toBe('2026-01-10');
    expect($despesa->forma)->toBe('Pix');
});

test('store with data de pagamento saves correctly', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('despesas-fixas.store'), [
            'descricao'  => 'Internet',
            'categoria'  => 'Casa',
            'valor'      => 120,
            'vencimento' => '15/01/2026',
            'status'     => 'Pago',
            'dataPgto'   => '14/01/2026',
            'forma'      => 'Pix',
        ])
        ->assertRedirect();

    $despesa = $user->despesasFixas()->first();
    expect($despesa)->not->toBeNull();
    expect($despesa->descricao)->toBe('Internet');
    expect($despesa->data_pgto->format('Y-m-d'))->toBe('2026-01-14');
    expect($despesa->status)->toBe('Pago');
});

test('update modifies a despesa fixa', function () {
    $user    = User::factory()->create();
    $despesa = $user->despesasFixas()->create([
        'descricao'  => 'Aluguel',
        'categoria'  => 'Casa',
        'valor'      => 2000,
        'vencimento' => '2026-01-10',
        'status'     => 'Pendente',
    ]);

    $this->actingAs($user)
        ->put(route('despesas-fixas.update', $despesa->id), [
            'descricao'  => 'Aluguel Atualizado',
            'categoria'  => 'Casa',
            'valor'      => 2200,
            'vencimento' => '10/02/2026',
            'status'     => 'Pago',
            'dataPgto'   => '09/02/2026',
            'forma'      => 'Boleto',
        ])
        ->assertRedirect();

    $despesa->refresh();
    expect($despesa->descricao)->toBe('Aluguel Atualizado');
    expect($despesa->valor)->toBe('2200.00');
    expect($despesa->status)->toBe('Pago');
});

test('home shows despesa fixa in payment month when paid, due month otherwise', function () {
    $user = User::factory()->create();
    $user->despesasFixas()->create([
        'descricao'  => 'Aluguel',
        'categoria'  => 'Casa',
        'valor'      => 2000,
        'vencimento' => '2026-04-10',
        'status'     => 'Pago',
        'data_pgto'  => '2026-05-02',
    ]);
    $user->despesasFixas()->create([
        'descricao'  => 'Internet',
        'categoria'  => 'Casa',
        'valor'      => 120,
        'vencimento' => '2026-04-15',
        'status'     => 'Pendente',
    ]);

    $this->actingAs($user)
        ->get(route('home', ['ano' => 2026]))
        ->assertInertia(fn ($page) => $page
            ->where('fixas.0.balanco', 'Maio')
            ->where('fixas.0.vencimento', '10/04/2026')
            ->where('fixas.1.balanco', 'Abril'));
});

test('destroy deletes a despesa fixa', function () {
    $user    = User::factory()->create();
    $despesa = $user->despesasFixas()->create([
        'descricao'  => 'Aluguel',
        'categoria'  => 'Casa',
        'valor'      => 2000,
        'vencimento' => '2026-01-10',
        'status'     => 'Pendente',
    ]);

    $this->actingAs($user)
        ->delete(route('despesas-fixas.destroy', $despesa->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('despesas_fixas', ['id' => $despesa->id]);
});

test('user cannot access another user despesa fixa', function () {
    $user1   = User::factory()->create();
    $user2   = User::factory()->create();
    $despesa = $user1->despesasFixas()->create([
        'descricao'  => 'Aluguel',
        'categoria'  => 'Casa',
        'valor'      => 2000,
        'vencimento' => '2026-01-10',
        'status'     => 'Pendente',
    ]);

    $this->actingAs($user2)
        ->put(route('despesas-fixas.update', $despesa->id), [
            'descricao'  => 'Hack',
            'categoria'  => 'Hack',
            'valor'      => 1,
            'vencimento' => '10/01/2026',
            'status'     => 'Pago',
        ])
        ->assertNotFound();

    $this->actingAs($user2)
        ->delete(route('despesas-fixas.destroy', $despesa->id))
        ->assertNotFound();
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('despesas-fixas.store'), [])
        ->assertSessionHasErrors(['descricao', 'categoria', 'valor', 'vencimento', 'status']);
});
