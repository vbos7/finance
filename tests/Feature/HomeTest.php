<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests see the welcome page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome'));
});

test('authenticated users see the home page with their data', function () {
    $user = User::factory()->create();
    $user->ganhos()->create([
        'descricao' => 'Salário Janeiro',
        'fonte'     => 'Salário',
        'data'      => '2026-01-05',
        'valor'     => 5000,
    ]);
    $user->despesasVariaveis()->create([
        'descricao' => 'Supermercado',
        'categoria' => 'Mercado',
        'valor'     => 350,
        'data'      => '2026-01-10',
        'balanco'   => '2026-01-01',
    ]);
    $user->investimentos()->create([
        'produto'    => 'PETR4',
        'empresa'    => 'Petrobras',
        'valor'      => 30,
        'quantidade' => 10,
        'tipo_ativo' => 'Ação',
        'provento'   => 0,
        'frequencia' => 'Trimestral',
        'data'       => '2026-01-15',
    ]);

    $this->actingAs($user)
        ->get(route('home', ['ano' => 2026]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('home')
            ->where('ano', 2026)
            ->has('ganhos', 1)
            ->where('ganhos.0.descricao', 'Salário Janeiro')
            ->has('variaveis', 1)
            ->where('balancoMensal.0.receita', 5000)
            ->where('balancoMensal.0.investimento', 300)
            ->where('balancoMensal.0.despesa', 350)
            ->where('balancoMensal.1.investimento', 0));
});

test('home does not expose another user data', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user2->ganhos()->create([
        'descricao' => 'Ganho do outro usuário',
        'fonte'     => 'Salário',
        'data'      => '2026-01-05',
        'valor'     => 5000,
    ]);

    $this->actingAs($user1)
        ->get(route('home', ['ano' => 2026]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('home')
            ->has('ganhos', 0));
});

test('home filters records by year', function () {
    $user = User::factory()->create();
    $user->ganhos()->create([
        'descricao' => 'Ganho 2025',
        'fonte'     => 'Salário',
        'data'      => '2025-06-05',
        'valor'     => 1000,
    ]);
    $user->ganhos()->create([
        'descricao' => 'Ganho 2026',
        'fonte'     => 'Salário',
        'data'      => '2026-06-05',
        'valor'     => 2000,
    ]);

    $this->actingAs($user)
        ->get(route('home', ['ano' => 2025]))
        ->assertInertia(fn ($page) => $page
            ->has('ganhos', 1)
            ->where('ganhos.0.descricao', 'Ganho 2025'));
});
