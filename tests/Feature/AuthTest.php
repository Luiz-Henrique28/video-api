<?php

use App\Models\User;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

function mockFirebase(string $email, string $sub = 'google-sub-123', string $avatar = 'https://avatar.example/photo.png'): void
{
    $claims = Mockery::mock();
    $claims->shouldReceive('get')->with('email')->andReturn($email);
    $claims->shouldReceive('get')->with('picture')->andReturn($avatar);
    $claims->shouldReceive('get')->with('firebase')->andReturn(['sign_in_provider' => 'google']);
    $claims->shouldReceive('get')->with('sub')->andReturn($sub);

    $tokenMock = Mockery::mock();
    $tokenMock->shouldReceive('claims')->andReturn($claims);

    $firebaseAuth = Mockery::mock();
    $firebaseAuth->shouldReceive('verifyIdToken')->andReturn($tokenMock);

    app()->instance('firebase.auth', $firebaseAuth);
}

it('registers and authenticates a new user on first login', function () {
    mockFirebase('new_user@example.com', 'google-uid-new');

    $response = $this->postJson('/api/auth/firebase', [
        'firebase_token' => 'fake.token.firebase',
        'device_name' => 'desktop'
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user']);

    $this->assertDatabaseHas('user', [
        'email' => 'new_user@example.com',
        'provider_id' => 'google-uid-new'
    ]);
});

it('authenticates an existing user without duplicating in database', function () {
    $existingUser = User::factory()->create([
        'email' => 'existing_user@example.com',
        'provider_id' => 'google-uid-old',
        'avatar' => 'https://avatar.example/old.png'
    ]);

    mockFirebase('existing_user@example.com', 'google-uid-updated', 'https://avatar.example/new.png');

    $response = $this->postJson('/api/auth/firebase', [
        'firebase_token' => 'fake.token.firebase',
        'device_name' => 'desktop'
    ]);

    $response->assertOk()
        ->assertJsonPath('user.id', $existingUser->id);

    $this->assertDatabaseCount('user', 1);

    $this->assertDatabaseHas('user', [
        'id' => $existingUser->id,
        'avatar' => 'https://avatar.example/new.png'
    ]);
});

it('revokes only the token for the same device on login and preserves others', function () {
    $user = User::factory()->create(['email' => 'multi@example.com']);
    $user->createToken('phone');
    $user->createToken('laptop');

    expect($user->tokens()->count())->toBe(2);

    mockFirebase('multi@example.com');

    $this->postJson('/api/auth/firebase', [
        'firebase_token' => 'fake.token.firebase',
        'device_name' => 'laptop'
    ])->assertOk();

    $user->refresh();
    expect($user->tokens()->count())->toBe(2);
    expect($user->tokens()->where('name', 'phone')->exists())->toBeTrue();
    expect($user->tokens()->where('name', 'laptop')->exists())->toBeTrue();
});

it('fails authentication when firebase token is invalid or expired', function () {
    $firebaseAuth = Mockery::mock();
    $firebaseAuth->shouldReceive('verifyIdToken')
        ->andThrow(new FailedToVerifyToken('Token inválido'));

    app()->instance('firebase.auth', $firebaseAuth);

    $response = $this->postJson('/api/auth/firebase', [
        'firebase_token' => 'token.invalid.expired',
        'device_name' => 'desktop'
    ]);

    $response->assertUnauthorized()
        ->assertJson(['message' => 'Token inválido ou expirado.']);
});

it('fails validation when firebase token or device name are missing', function () {
    $response = $this->postJson('/api/auth/firebase', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['firebase_token', 'device_name']);
});

it('returns authenticated user data on route auth/me', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar
        ]);
});

it('rejects access to route auth/me without token', function () {
    $this->getJson('/api/auth/me')
        ->assertUnauthorized();
});

it('revokes the current access token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('active-session')->plainTextToken;

    expect($user->tokens()->count())->toBe(1);

    $response = $this->withToken($token)
        ->postJson('/api/auth/logout');

    $response->assertOk()
        ->assertJson(['message' => 'token revoked']);

    expect($user->tokens()->count())->toBe(0);
});

it('requires authentication to logout', function () {
    $this->postJson('/api/auth/logout')
        ->assertUnauthorized();
});
