<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the main dashboard renders safely with an empty database', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/home')
        ->assertOk()
        ->assertSee('Resumen general de caja, créditos, socios y utilidades.')
        ->assertSee('Accesos rápidos');
});

test('the main dashboard accepts a custom period', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/home?period=custom&from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->assertSee('Rango personalizado')
        ->assertSee('01/03—31/03/2026');
});
