<?php

/*
 * El registro público está deshabilitado en este sistema: los usuarios
 * se crean desde el panel de administración. Se conservan las pruebas
 * marcadas como omitidas para documentar la diferencia con Breeze.
 */

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
})->skip('El registro público está deshabilitado en este sistema.');

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
})->skip('El registro público está deshabilitado en este sistema.');
