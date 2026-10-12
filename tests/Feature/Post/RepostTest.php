<?php

use App\Models\Post;
use App\Models\Repost;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication to repost and unrepost', function () {
    $author = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id]);

    $this->postJson("/api/post/{$post->id}/repost")->assertUnauthorized();
    $this->deleteJson("/api/post/{$post->id}/repost")->assertUnauthorized();
});

it('prevents a user from reposting their own post', function () {
    $author = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id]);

    Sanctum::actingAs($author);

    $response = $this->postJson("/api/post/{$post->id}/repost");
    $response->assertStatus(422)
        ->assertJsonPath('message', 'Você não pode republicar a sua própria publicação.');
});

it('successfully reposts and unreposts a post', function () {
    $author = User::factory()->create();
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id]);

    Sanctum::actingAs($user);

    // Repost
    $response = $this->postJson("/api/post/{$post->id}/repost");
    $response->assertOk()
        ->assertJson([
            'reposted' => true,
            'reposts_count' => 1,
        ]);

    $this->assertDatabaseHas('repost', [
        'user_id' => $user->id,
        'post_id' => $post->id,
    ]);

    // Unrepost
    $unrepostResponse = $this->deleteJson("/api/post/{$post->id}/repost");
    $unrepostResponse->assertOk()
        ->assertJson([
            'reposted' => false,
            'reposts_count' => 0,
        ]);

    $this->assertDatabaseMissing('repost', [
        'user_id' => $user->id,
        'post_id' => $post->id,
    ]);
});

it('returns 409 when reposting an already reposted post', function () {
    $author = User::factory()->create();
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id]);

    Sanctum::actingAs($user);

    $this->postJson("/api/post/{$post->id}/repost")->assertOk();
    $response = $this->postJson("/api/post/{$post->id}/repost");
    $response->assertStatus(409);
});

it('returns 404 when trying to unrepost a post that was not reposted', function () {
    $author = User::factory()->create();
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id]);

    Sanctum::actingAs($user);

    $response = $this->deleteJson("/api/post/{$post->id}/repost");
    $response->assertNotFound()
        ->assertJsonPath('message', 'Você não republicou este post.');
});

it('returns 404 when attempting to repost a non-existent post', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/post/999999/repost');
    $response->assertNotFound();
});

it('removes repost records in cascade when original post is deleted', function () {
    $author = User::factory()->create();
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id]);

    Repost::create([
        'user_id' => $user->id,
        'post_id' => $post->id,
        'created_at' => now(),
    ]);

    expect($user->repostedPosts()->count())->toBe(1);

    $post->delete();

    $this->assertDatabaseMissing('repost', [
        'post_id' => $post->id,
    ]);
    expect($user->fresh()->repostedPosts()->count())->toBe(0);
});

it('returns is_reposted true and reposts_count on post detail', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id, 'visibility' => 'public']);

    Repost::create([
        'user_id' => $viewer->id,
        'post_id' => $post->id,
        'created_at' => now(),
    ]);

    Sanctum::actingAs($viewer);
    $response = $this->getJson("/api/post/{$post->id}");
    $response->assertOk()
        ->assertJsonPath('is_reposted', true)
        ->assertJsonPath('reposts_count', 1);

    // Guest views post
    auth('sanctum')->forgetUser();
    $guestResponse = $this->getJson("/api/post/{$post->id}");
    $guestResponse->assertOk()
        ->assertJsonPath('is_reposted', false)
        ->assertJsonPath('reposts_count', 1);
});

it('returns reposts_count on user profile and filters profile posts by tab type', function () {
    $author = User::factory()->create(['name' => 'original_author']);
    $user = User::factory()->create(['name' => 'profile_user']);

    // User's own post
    $ownPost = Post::factory()->create([
        'user_id' => $user->id,
        'visibility' => 'public',
        'caption' => 'Own post',
    ]);

    // Another user's post reposted by $user
    $otherPost = Post::factory()->create([
        'user_id' => $author->id,
        'visibility' => 'public',
        'caption' => 'Reposted post',
    ]);

    Repost::create([
        'user_id' => $user->id,
        'post_id' => $otherPost->id,
        'created_at' => now(),
    ]);

    // Check profile metrics
    $profileResponse = $this->getJson('/api/user/profile_user');
    $profileResponse->assertOk()
        ->assertJsonPath('data.posts_count', 1)
        ->assertJsonPath('data.reposts_count', 1);

    // Check posts type=posts
    $postsTab = $this->getJson("/api/user/{$user->id}/posts?type=posts");
    $postsTab->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownPost->id)
        ->assertJsonPath('data.0.is_repost', false);

    // Check posts type=reposts
    $repostsTab = $this->getJson("/api/user/{$user->id}/posts?type=reposts");
    $repostsTab->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $otherPost->id)
        ->assertJsonPath('data.0.is_repost', true)
        ->assertJsonPath('data.0.user.name', 'original_author');

    // Check posts type=all
    $allTab = $this->getJson("/api/user/{$user->id}/posts?type=all");
    $allTab->assertOk()
        ->assertJsonCount(2, 'data');

    $allIds = collect($allTab->json('data'))->pluck('id');
    expect($allIds->contains($ownPost->id))->toBeTrue();
    expect($allIds->contains($otherPost->id))->toBeTrue();

    // Check fallback when type is unknown
    $fallbackTab = $this->getJson("/api/user/{$user->id}/posts?type=unknown_value");
    $fallbackTab->assertOk()
        ->assertJsonCount(2, 'data');
});
