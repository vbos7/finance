<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name'                  => 'Test User',
        'email'                 => 'test@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));
});

test('new user receives default seed data on registration', function () {
    $this->post(route('register.store'), [
        'name'                  => 'Seed User',
        'email'                 => 'seed@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = \App\Models\User::where('email', 'seed@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->metas)->toHaveCount(3);
    expect($user->fontesRenda)->toHaveCount(3);
    expect($user->categorias)->toHaveCount(12);
    expect($user->formasPagamento)->toHaveCount(5);
});
