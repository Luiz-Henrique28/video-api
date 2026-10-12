<?php

use App\Models\Follow;
use App\Models\User;

it('requires authentication to follow a user', function () {
    $targetUser = User::factory()->create();

    $response = $this->postJson("/api/user/{$targetUser->id}/follow");

    $response->assertUnauthorized();
});

it('blocks users with incomplete profile from following', function () {
    $userWithoutName = User::factory()->create(['name' => null]);
    $targetUser = User::factory()->create();

    $response = $this->actingAs($userWithoutName)
        ->postJson("/api/user/{$targetUser->id}/follow");

    $response->assertForbidden()
        ->assertJsonPath('code', 'USERNAME_REQUIRED');
});

it('prevents a user from following themselves', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson("/api/user/{$user->id}/follow");

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Voce nao pode seguir a si mesmo.');
});

it('allows an authenticated user to follow another user and increments counters', function () {
    $follower = User::factory()->create([
        'following_count' => 0,
    ]);
    $targetUser = User::factory()->create([
        'followers_count' => 0,
    ]);

    $response = $this->actingAs($follower)
        ->postJson("/api/user/{$targetUser->id}/follow");

    $response->assertOk()
        ->assertJsonPath('following', true)
        ->assertJsonPath('followers_count', 1);

    $this->assertDatabaseHas('follow', [
        'follower_id' => $follower->id,
        'following_id' => $targetUser->id,
    ]);

    expect($targetUser->fresh()->followers_count)->toBe(1);
    expect($follower->fresh()->following_count)->toBe(1);
});

it('prevents duplicate following with 409 conflict', function () {
    $follower = User::factory()->create();
    $targetUser = User::factory()->create();

    // First follow succeeds
    $this->actingAs($follower)
        ->postJson("/api/user/{$targetUser->id}/follow")
        ->assertOk();

    // Second follow attempt returns 409
    $response = $this->actingAs($follower)
        ->postJson("/api/user/{$targetUser->id}/follow");

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Voce ja segue este usuario.');

    $this->assertDatabaseCount('follow', 1);
});

it('requires authentication to unfollow a user', function () {
    $targetUser = User::factory()->create();

    $response = $this->deleteJson("/api/user/{$targetUser->id}/follow");

    $response->assertUnauthorized();
});

it('returns 404 when trying to unfollow a user that is not being followed', function () {
    $follower = User::factory()->create();
    $targetUser = User::factory()->create();

    $response = $this->actingAs($follower)
        ->deleteJson("/api/user/{$targetUser->id}/follow");

    $response->assertNotFound()
        ->assertJsonPath('message', 'Voce nao segue este usuario.');
});

it('allows an authenticated user to unfollow another user and decrements counters', function () {
    $follower = User::factory()->create([
        'following_count' => 1,
    ]);
    $targetUser = User::factory()->create([
        'followers_count' => 1,
    ]);

    Follow::create([
        'follower_id' => $follower->id,
        'following_id' => $targetUser->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($follower)
        ->deleteJson("/api/user/{$targetUser->id}/follow");

    $response->assertOk()
        ->assertJsonPath('following', false)
        ->assertJsonPath('followers_count', 0);

    $this->assertDatabaseMissing('follow', [
        'follower_id' => $follower->id,
        'following_id' => $targetUser->id,
    ]);

    expect($targetUser->fresh()->followers_count)->toBe(0);
    expect($follower->fresh()->following_count)->toBe(0);
});

it('returns 404 when attempting to follow a non-existent user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/user/999999/follow');

    $response->assertNotFound();
});
