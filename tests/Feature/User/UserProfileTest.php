<?php

use App\Models\Comment;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('allows anyone to view a user public profile by username', function () {
    $user = User::factory()->create([
        'name' => 'janedoe',
        'followers_count' => 5,
        'following_count' => 3,
    ]);

    Post::factory()->create([
        'user_id' => $user->id,
        'visibility' => 'public',
    ]);

    $response = $this->getJson('/api/user/janedoe');

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'janedoe')
        ->assertJsonPath('data.followers_count', 5)
        ->assertJsonPath('data.following_count', 3)
        ->assertJsonPath('data.posts_count', 1)
        ->assertJsonPath('data.is_following', null);
});

it('returns is_following true for an authenticated follower and false otherwise', function () {
    $author = User::factory()->create(['name' => 'author_user']);
    $follower = User::factory()->create();
    $otherUser = User::factory()->create();

    Follow::create([
        'follower_id' => $follower->id,
        'following_id' => $author->id,
        'created_at' => now(),
    ]);

    // Follower views profile
    Sanctum::actingAs($follower);
    $responseFollower = $this->getJson('/api/user/author_user');
    $responseFollower->assertOk()
        ->assertJsonPath('data.is_following', true);

    // Other user views profile
    Sanctum::actingAs($otherUser);
    $responseOther = $this->getJson('/api/user/author_user');
    $responseOther->assertOk()
        ->assertJsonPath('data.is_following', false);
});

it('returns 404 when viewing a non-existent username', function () {
    $response = $this->getJson('/api/user/non_existent_user_99999');

    $response->assertNotFound();
});

it('lists only public posts for guest visitors on user posts endpoint', function () {
    $user = User::factory()->create();

    $publicPost = Post::factory()->create([
        'user_id' => $user->id,
        'visibility' => 'public',
        'caption' => 'Public post',
    ]);

    $privatePost = Post::factory()->create([
        'user_id' => $user->id,
        'visibility' => 'private',
        'caption' => 'Private post',
    ]);

    $response = $this->getJson("/api/user/{$user->id}/posts");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $publicPost->id);
});

it('allows the author to see their own private and public posts on user posts endpoint', function () {
    $author = User::factory()->create();

    Post::factory()->create([
        'user_id' => $author->id,
        'visibility' => 'public',
    ]);

    Post::factory()->create([
        'user_id' => $author->id,
        'visibility' => 'private',
    ]);

    Sanctum::actingAs($author);
    $response = $this->getJson("/api/user/{$author->id}/posts");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('requires authentication to update username', function () {
    $response = $this->patchJson('/api/user/username', [
        'name' => 'new_username',
    ]);

    $response->assertUnauthorized();
});

it('validates username format and uniqueness when updating', function () {
    $existingUser = User::factory()->create(['name' => 'taken_name']);
    $user = User::factory()->create(['name' => 'current_name']);

    // Missing/empty name
    Sanctum::actingAs($user);
    $this->patchJson('/api/user/username', ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    // Name with spaces
    Sanctum::actingAs($user);
    $this->patchJson('/api/user/username', ['name' => 'invalid name with space'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    // Name with consecutive dots/underscores
    Sanctum::actingAs($user);
    $this->patchJson('/api/user/username', ['name' => 'invalid__name'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    // Already taken username
    Sanctum::actingAs($user);
    $this->patchJson('/api/user/username', ['name' => 'taken_name'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('successfully updates username with valid format', function () {
    $user = User::factory()->create(['name' => 'old_name']);

    Sanctum::actingAs($user);
    $response = $this->patchJson('/api/user/username', [
        'name' => 'brand_new_name_123',
    ]);

    $response->assertOk()
        ->assertJsonPath('name', 'brand_new_name_123');

    $this->assertDatabaseHas('user', [
        'id' => $user->id,
        'name' => 'brand_new_name_123',
    ]);
});

it('requires authentication to delete account', function () {
    $response = $this->deleteJson('/api/user');

    $response->assertUnauthorized();
});

it('successfully deletes the authenticated user account and their associated content', function () {
    $user = User::factory()->create();

    $post = Post::factory()->create(['user_id' => $user->id]);
    $comment = Comment::factory()->create([
        'user_id' => $user->id,
        'post_id' => $post->id,
    ]);

    Sanctum::actingAs($user);
    $response = $this->deleteJson('/api/user');

    $response->assertOk()
        ->assertJsonPath('message', 'Conta deletada com sucesso');

    $this->assertDatabaseMissing('user', [
        'id' => $user->id,
    ]);

    $this->assertDatabaseMissing('post', [
        'id' => $post->id,
    ]);

    $this->assertDatabaseMissing('comment', [
        'id' => $comment->id,
    ]);
});
