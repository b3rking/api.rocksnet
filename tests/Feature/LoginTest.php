<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * We use 'refresh database' to ensure each test
 * starts with a clean slate.
 */
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user can login with correct credentials', function () {
    $user = User::factory()->create([
        'email' => 'dev@ollavtech.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'dev@ollavtech.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'access_token',
            'token_type'
        ]);

    // Instead of assertAuthenticatedAs, verify the token works for a protected route
    $token = $response->json('access_token');

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertStatus(200)
        ->assertJson(['email' => $user->email]);
});

test('user cannot login with wrong password', function () {
    $user = User::factory()->create([
        'email' => 'tester@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'tester@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login requires a valid email and password', function () {
    $response = $this->postJson('/api/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

test('new users can register', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'B3RKING',
        'email' => 'berk@ollavtech.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
             ->assertJsonStructure(['access_token', 'user']);

    $this->assertDatabaseHas('users', ['email' => 'berk@ollavtech.com']);
});

test('existing users can login', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123')
    ]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
             ->assertJsonStructure(['access_token']);
});
