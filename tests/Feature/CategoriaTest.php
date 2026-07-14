<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot access categorias routes', function () {
    $this->post(route('categorias.store'))->assertRedirect(route('login'));
    $this->put(route('categorias.update', 1))->assertRedirect(route('login'));
    $this->delete(route('categorias.destroy', 1))->assertRedirect(route('login'));
});

test('store creates a categoria', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categorias.store'), [
            'nome'         => 'Mercado',
            'icone'        => 'ShoppingBag',
            'limite_anual' => 12000,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('categorias', [
        'user_id'      => $user->id,
        'nome'         => 'Mercado',
        'icone'        => 'ShoppingBag',
        'limite_anual' => 12000,
    ]);
});

test('update modifies a categoria', function () {
    $user      = User::factory()->create();
    $categoria = $user->categorias()->create([
        'nome'         => 'Mercado',
        'icone'        => 'ShoppingBag',
        'limite_anual' => 12000,
    ]);

    $this->actingAs($user)
        ->put(route('categorias.update', $categoria->id), [
            'nome'         => 'Alimentação',
            'icone'        => 'Utensils',
            'limite_anual' => 15000,
        ])
        ->assertRedirect();

    $categoria->refresh();
    expect($categoria->nome)->toBe('Alimentação');
    expect($categoria->icone)->toBe('Utensils');
    expect((float) $categoria->limite_anual)->toBe(15000.0);
});

test('destroy deletes a categoria', function () {
    $user      = User::factory()->create();
    $categoria = $user->categorias()->create([
        'nome'         => 'Mercado',
        'limite_anual' => 12000,
    ]);

    $this->actingAs($user)
        ->delete(route('categorias.destroy', $categoria->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
});

test('user cannot access another user categoria', function () {
    $user1     = User::factory()->create();
    $user2     = User::factory()->create();
    $categoria = $user1->categorias()->create([
        'nome'         => 'Mercado',
        'limite_anual' => 12000,
    ]);

    $this->actingAs($user2)
        ->put(route('categorias.update', $categoria->id), [
            'nome' => 'Hack',
        ])
        ->assertNotFound();

    $this->actingAs($user2)
        ->delete(route('categorias.destroy', $categoria->id))
        ->assertNotFound();
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categorias.store'), ['limite_anual' => -1])
        ->assertSessionHasErrors(['nome', 'limite_anual']);
});
