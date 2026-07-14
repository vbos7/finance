<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot access metas routes', function () {
    $this->post(route('metas.store'))->assertRedirect(route('login'));
    $this->put(route('metas.update', 1))->assertRedirect(route('login'));
    $this->delete(route('metas.destroy', 1))->assertRedirect(route('login'));
});

test('store creates a meta', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('metas.store'), [
            'nome'  => 'Casamento',
            'icone' => 'Gem',
            'valor' => 50000,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('metas', [
        'user_id' => $user->id,
        'nome'    => 'Casamento',
        'icone'   => 'Gem',
        'valor'   => 50000,
    ]);
});

test('update modifies a meta', function () {
    $user = User::factory()->create();
    $meta = $user->metas()->create([
        'nome'  => 'Casamento',
        'icone' => 'Gem',
        'valor' => 50000,
    ]);

    $this->actingAs($user)
        ->put(route('metas.update', $meta->id), [
            'nome'  => 'Casamento Atualizado',
            'icone' => 'Heart',
            'valor' => 60000,
        ])
        ->assertRedirect();

    $meta->refresh();
    expect($meta->nome)->toBe('Casamento Atualizado');
    expect($meta->valor)->toBe('60000.00');
});

test('destroy deletes a meta', function () {
    $user = User::factory()->create();
    $meta = $user->metas()->create([
        'nome'  => 'Casamento',
        'icone' => 'Gem',
        'valor' => 50000,
    ]);

    $this->actingAs($user)
        ->delete(route('metas.destroy', $meta->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('metas', ['id' => $meta->id]);
});

test('investir creates an investimento from meta', function () {
    $user = User::factory()->create();
    $meta = $user->metas()->create([
        'nome'  => 'Casamento',
        'icone' => 'Gem',
        'valor' => 50000,
    ]);

    $this->actingAs($user)
        ->post(route('metas.investir', $meta->id), [
            'valor' => 1000,
            'data'  => '15/01/2026',
        ])
        ->assertRedirect();

    $inv = $user->investimentos()->first();
    expect($inv)->not->toBeNull();
    expect($inv->produto)->toBe('Casamento');
    expect($inv->tipo_ativo)->toBe('Meta Financeira');
    expect($inv->valor)->toBe('1000.00');
    expect($inv->data->format('Y-m-d'))->toBe('2026-01-15');
});

test('investir with recurrence creates monthly investimentos', function () {
    $user = User::factory()->create();
    $meta = $user->metas()->create([
        'nome'  => 'Imóvel',
        'icone' => 'House',
        'valor' => 200000,
    ]);

    $this->actingAs($user)
        ->post(route('metas.investir', $meta->id), [
            'valor'      => 2000,
            'data'       => '10/01/2026',
            'dataLimite' => '05/2026',
        ])
        ->assertRedirect();

    $investimentos = $user->investimentos()->orderBy('data')->get();

    expect($investimentos)->toHaveCount(5);
    expect($investimentos[0]->data->format('Y-m-d'))->toBe('2026-01-10');
    expect($investimentos[4]->data->format('Y-m-d'))->toBe('2026-05-10');
    expect($investimentos->every(fn ($i) => $i->produto === 'Imóvel'))->toBeTrue();
    expect($investimentos->every(fn ($i) => $i->tipo_ativo === 'Meta Financeira'))->toBeTrue();
});

test('user cannot access another user meta', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $meta  = $user1->metas()->create([
        'nome'  => 'Casamento',
        'icone' => 'Gem',
        'valor' => 50000,
    ]);

    $this->actingAs($user2)
        ->put(route('metas.update', $meta->id), [
            'nome'  => 'Hack',
            'icone' => 'X',
            'valor' => 1,
        ])
        ->assertNotFound();

    $this->actingAs($user2)
        ->delete(route('metas.destroy', $meta->id))
        ->assertNotFound();

    $this->actingAs($user2)
        ->post(route('metas.investir', $meta->id), [
            'valor' => 100,
            'data'  => '15/01/2026',
        ])
        ->assertNotFound();
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('metas.store'), [])
        ->assertSessionHasErrors(['nome', 'valor']);
});
