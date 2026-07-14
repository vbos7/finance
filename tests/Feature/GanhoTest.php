<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot access ganhos routes', function () {
    $this->post(route('ganhos.store'))->assertRedirect(route('login'));
    $this->put(route('ganhos.update', 1))->assertRedirect(route('login'));
    $this->delete(route('ganhos.destroy', 1))->assertRedirect(route('login'));
});

test('store creates a ganho', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ganhos.store'), [
            'descricao' => 'Salário',
            'fonte'     => 'Trabalho',
            'data'      => '15/01/2026',
            'valor'     => 5000,
        ])
        ->assertRedirect();

    $ganho = $user->ganhos()->first();
    expect($ganho)->not->toBeNull();
    expect($ganho->descricao)->toBe('Salário');
    expect($ganho->fonte)->toBe('Trabalho');
    expect($ganho->data->format('Y-m-d'))->toBe('2026-01-15');
    expect($ganho->valor)->toBe('5000.00');
});

test('store with recurrence creates monthly records', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ganhos.store'), [
            'descricao'  => 'Salário',
            'fonte'      => 'Trabalho',
            'data'       => '10/01/2026',
            'valor'      => 5000,
            'dataLimite' => '04/2026',
        ])
        ->assertRedirect();

    $ganhos = $user->ganhos()->orderBy('data')->get();

    expect($ganhos)->toHaveCount(4);
    expect($ganhos[0]->data->format('Y-m-d'))->toBe('2026-01-10');
    expect($ganhos[1]->data->format('Y-m-d'))->toBe('2026-02-10');
    expect($ganhos[2]->data->format('Y-m-d'))->toBe('2026-03-10');
    expect($ganhos[3]->data->format('Y-m-d'))->toBe('2026-04-10');
});

test('store without dataLimite creates single record', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ganhos.store'), [
            'descricao' => 'Freelance',
            'fonte'     => 'Freelance',
            'data'      => '20/03/2026',
            'valor'     => 1500,
        ])
        ->assertRedirect();

    expect($user->ganhos)->toHaveCount(1);
});

test('update modifies a ganho', function () {
    $user  = User::factory()->create();
    $ganho = $user->ganhos()->create([
        'descricao' => 'Salário',
        'fonte'     => 'Trabalho',
        'data'      => '2026-01-15',
        'valor'     => 5000,
    ]);

    $this->actingAs($user)
        ->put(route('ganhos.update', $ganho->id), [
            'descricao' => 'Salário Atualizado',
            'fonte'     => 'Trabalho',
            'data'      => '20/01/2026',
            'valor'     => 6000,
        ])
        ->assertRedirect();

    $ganho->refresh();
    expect($ganho->descricao)->toBe('Salário Atualizado');
    expect($ganho->valor)->toBe('6000.00');
    expect($ganho->data->format('Y-m-d'))->toBe('2026-01-20');
});

test('destroy deletes a ganho', function () {
    $user  = User::factory()->create();
    $ganho = $user->ganhos()->create([
        'descricao' => 'Salário',
        'fonte'     => 'Trabalho',
        'data'      => '2026-01-15',
        'valor'     => 5000,
    ]);

    $this->actingAs($user)
        ->delete(route('ganhos.destroy', $ganho->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('ganhos', ['id' => $ganho->id]);
});

test('user cannot access another user ganho', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $ganho = $user1->ganhos()->create([
        'descricao' => 'Salário',
        'fonte'     => 'Trabalho',
        'data'      => '2026-01-15',
        'valor'     => 5000,
    ]);

    $this->actingAs($user2)
        ->put(route('ganhos.update', $ganho->id), [
            'descricao' => 'Hack',
            'fonte'     => 'Hack',
            'data'      => '15/01/2026',
            'valor'     => 9999,
        ])
        ->assertNotFound();

    $this->actingAs($user2)
        ->delete(route('ganhos.destroy', $ganho->id))
        ->assertNotFound();
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ganhos.store'), [])
        ->assertSessionHasErrors(['descricao', 'fonte', 'data', 'valor']);
});
