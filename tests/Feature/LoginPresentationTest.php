<?php

it('renders the SysCaC login override with the authentication contract intact', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('sys-login-card', false)
        ->assertSee('Gestión integral para asociaciones CAC')
        ->assertSee('css/syscac-login.css', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="remember"', false)
        ->assertSee('Acceder al sistema');
});
