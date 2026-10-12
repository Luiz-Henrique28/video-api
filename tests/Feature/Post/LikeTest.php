<?php

use App\Models\Like;
use App\Models\Post;
use App\Models\User;

it('requires authentication to like a post', function () {
    $post = Post::factory()->create();

    $response = $this->postJson("/api/post/{$post->id}/like");

    $response->assertUnauthorized();
});

it('allows an authenticated user to like a post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();

    $response = $this->actingAs($user)
        ->postJson("/api/post/{$post->id}/like");

    $response->assertOk()
        ->assertJsonPath('liked', true)
        ->assertJsonPath('likes_count', 1);

    $this->assertDatabaseHas('like', [
        'user_id' => $user->id,
        'post_id' => $post->id,
    ]);
});

it('prevents a user from liking the same post twice with 409 conflict', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();

    // First like succeeds
    $this->actingAs($user)
        ->postJson("/api/post/{$post->id}/like")
        ->assertOk();

    // Second like on same post returns 409 Conflict
    $response = $this->actingAs($user)
        ->postJson("/api/post/{$post->id}/like");

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Você já curtiu este post.');

    $this->assertDatabaseCount('like', 1);
});

it('requires authentication to unlike a post', function () {
    $post = Post::factory()->create();

    $response = $this->deleteJson("/api/post/{$post->id}/like");

    $response->assertUnauthorized();
});

it('allows an authenticated user to unlike a previously liked post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();

    Like::create([
        'user_id' => $user->id,
        'post_id' => $post->id,
    ]);

    $response = $this->actingAs($user)
        ->deleteJson("/api/post/{$post->id}/like");

    $response->assertOk()
        ->assertJsonPath('liked', false)
        ->assertJsonPath('likes_count', 0);

    $this->assertDatabaseMissing('like', [
        'user_id' => $user->id,
        'post_id' => $post->id,
    ]);
});

it('returns 404 when trying to unlike a post that was not liked', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();

    $response = $this->actingAs($user)
        ->deleteJson("/api/post/{$post->id}/like");

    $response->assertNotFound()
        ->assertJsonPath('message', 'Você não curtiu este post.');
});

it('correctly tracks likes count across multiple users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $post = Post::factory()->create();

    // User A likes
    $this->actingAs($userA)
        ->postJson("/api/post/{$post->id}/like")
        ->assertOk()
        ->assertJsonPath('likes_count', 1);

    // User B likes
    $this->actingAs($userB)
        ->postJson("/api/post/{$post->id}/like")
        ->assertOk()
        ->assertJsonPath('likes_count', 2);

    // User A unlikes
    $this->actingAs($userA)
        ->deleteJson("/api/post/{$post->id}/like")
        ->assertOk()
        ->assertJsonPath('likes_count', 1);
});

it('returns 404 when attempting to like a non-existent post', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/post/999999/like');

    $response->assertNotFound();
});
