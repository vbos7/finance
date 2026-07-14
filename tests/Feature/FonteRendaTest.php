<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot access fontes-renda routes', function () {
    $this->post(route('fontes-renda.store'))->assertRedirect(route('login'));
    $this->put(route('fontes-renda.update', 1))->assertRedirect(route('login'));
    $this->delete(route('fontes-renda.destroy', 1))->assertRedirect(route('login'));
});

test('store creates a fonte de renda', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('fontes-renda.store'), [
            'nome'       => 'Salário',
            'icone'      => 'Briefcase',
            'meta_anual' => 60000,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('fontes_renda', [
        'user_id'    => $user->id,
        'nome'       => 'Salário',
        'icone'      => 'Briefcase',
        'meta_anual' => 60000,
    ]);
});

test('update modifies a fonte de renda', function () {
    $user  = User::factory()->create();
    $fonte = $user->fontesRenda()->create([
        'nome'       => 'Salário',
        'icone'      => 'Briefcase',
        'meta_anual' => 60000,
    ]);

    $this->actingAs($user)
        ->put(route('fontes-renda.update', $fonte->id), [
            'nome'       => 'Freelance',
            'icone'      => 'Laptop',
            'meta_anual' => 30000,
        ])
        ->assertRedirect();

    $fonte->refresh();
    expect($fonte->nome)->toBe('Freelance');
    expect($fonte->icone)->toBe('Laptop');
    expect((float) $fonte->meta_anual)->toBe(30000.0);
});

test('destroy deletes a fonte de renda', function () {
    $user  = User::factory()->create();
    $fonte = $user->fontesRenda()->create([
        'nome'       => 'Salário',
        'meta_anual' => 60000,
    ]);

    $this->actingAs($user)
        ->delete(route('fontes-renda.destroy', $fonte->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('fontes_renda', ['id' => $fonte->id]);
});

test('user cannot access another user fonte de renda', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $fonte = $user1->fontesRenda()->create([
        'nome'       => 'Salário',
        'meta_anual' => 60000,
    ]);

    $this->actingAs($user2)
        ->put(route('fontes-renda.update', $fonte->id), [
            'nome' => 'Hack',
        ])
        ->assertNotFound();

    $this->actingAs($user2)
        ->delete(route('fontes-renda.destroy', $fonte->id))
        ->assertNotFound();
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('fontes-renda.store'), ['meta_anual' => -1])
        ->assertSessionHasErrors(['nome', 'meta_anual']);
});
