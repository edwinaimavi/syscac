<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a guest can open the institutional landing page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Sistema de Caja y Créditos para asociaciones')
        ->assertSee('Iniciar sesión')
        ->assertDontSee('Productos destacados');
});

test('an authenticated user is redirected from the landing page to the panel', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('home'));
});

test('the dashboard alias uses the authenticated SysCaC panel', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Resumen general de caja, créditos, socios y utilidades.');
});
