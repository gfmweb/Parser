<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('login returns token for valid credentials', function () {
    User::factory()->create([
        'name' => 'Admin',
        'email' => 'admin@test.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'admin@test.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.email', 'admin@test.com')
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

it('login from a browser origin does not require a csrf token', function () {
    User::factory()->create([
        'email' => 'admin@test.com',
        'password' => 'password',
    ]);

    $this->withHeaders([
        'Origin' => 'http://localhost',
        'Referer' => 'http://localhost/login',
    ])->postJson('/api/login', [
        'email' => 'admin@test.com',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('user.email', 'admin@test.com');
});

it('login returns 422 for invalid credentials', function () {
    $this->postJson('/api/login', [
        'email' => 'not-an-email',
        'password' => 'password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('login returns 401 for wrong password', function () {
    User::factory()->create([
        'email' => 'admin@test.com',
        'password' => 'password',
    ]);

    $this->postJson('/api/login', [
        'email' => 'admin@test.com',
        'password' => 'wrong-password',
    ])->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid credentials.');
});

it('logout revokes token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logged out');

    expect(PersonalAccessToken::query()->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertUnauthorized();
});

it('protected routes return 401 without token', function () {
    $this->getJson('/api/user')->assertUnauthorized();
    $this->postJson('/api/logout')->assertUnauthorized();
    $this->getJson('/api/organizations')->assertUnauthorized();
});
