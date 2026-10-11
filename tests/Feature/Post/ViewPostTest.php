<?php

use App\Jobs\IncrementPostViews;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

it('allows anyone to view a public post', function () {
    $author = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => $author->id,
        'visibility' => 'public',
        'caption' => 'A wonderful public post',
    ]);

    $response = $this->getJson("/api/post/{$post->id}");

    $response->assertOk()
        ->assertJsonPath('id', $post->id)
        ->assertJsonPath('caption', 'A wonderful public post')
        ->assertJsonPath('user.id', $author->id);
});

it('allows the author to view their private post', function () {
    $author = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => $author->id,
        'visibility' => 'private',
    ]);

    $response = $this->actingAs($author)
        ->getJson("/api/post/{$post->id}");

    $response->assertOk()
        ->assertJsonPath('id', $post->id);
});

it('hides a private post from guest visitors and other users with a 404', function () {
    $author = User::factory()->create();
    $intruder = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => $author->id,
        'visibility' => 'private',
    ]);

    // Guest gets 404
    $guestResponse = $this->getJson("/api/post/{$post->id}");
    $guestResponse->assertNotFound();

    // Another authenticated user also gets 404
    $intruderResponse = $this->actingAs($intruder)
        ->getJson("/api/post/{$post->id}");
    $intruderResponse->assertNotFound();
});

it('dispatches IncrementPostViews job when viewed and respects anti-flood cache', function () {
    Queue::fake();

    $post = Post::factory()->create(['visibility' => 'public']);

    // First view: should dispatch IncrementPostViews
    $this->getJson("/api/post/{$post->id}")->assertOk();

    Queue::assertPushed(IncrementPostViews::class, 1);

    // Simulate 4h cache lock
    Cache::put("post_view_seen:{$post->id}:ip:127.0.0.1", true, now()->addHours(4));

    // Second view within cache window: should NOT dispatch again
    $this->getJson("/api/post/{$post->id}")->assertOk();

    Queue::assertPushed(IncrementPostViews::class, 1);
});

it('lists only public posts in the feed for guest visitors', function () {
    $author = User::factory()->create();

    Post::factory()->count(3)->create([
        'user_id' => $author->id,
        'visibility' => 'public',
    ]);

    Post::factory()->count(2)->create([
        'user_id' => $author->id,
        'visibility' => 'private',
    ]);

    $response = $this->getJson('/api/post');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('includes authors own private posts in feed while hiding private posts from others', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // User A: 1 public, 1 private
    Post::factory()->create(['user_id' => $userA->id, 'visibility' => 'public']);
    Post::factory()->create(['user_id' => $userA->id, 'visibility' => 'private']);

    // User B: 1 public, 1 private
    Post::factory()->create(['user_id' => $userB->id, 'visibility' => 'public']);
    Post::factory()->create(['user_id' => $userB->id, 'visibility' => 'private']);

    // User A should see: 2 public posts + 1 own private post = 3 posts
    $response = Sanctum::actingAs($userA);
    $response = $this->getJson('/api/post');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('marks is_liked as true for user who liked the post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['visibility' => 'public']);

    Like::create([
        'user_id' => $user->id,
        'post_id' => $post->id,
    ]);

    Sanctum::actingAs($user);

    $this->getJson("/api/post/{$post->id}")
        ->assertOk()
        ->assertJsonPath('is_liked', true);
});

it('marks is_liked as false for guest or user who did not like', function () {
    $userWhoLiked = User::factory()->create();
    $otherUser = User::factory()->create();
    $post = Post::factory()->create(['visibility' => 'public']);

    Like::create([
        'user_id' => $userWhoLiked->id,
        'post_id' => $post->id,
    ]);

    // Guest visitor
    $this->getJson("/api/post/{$post->id}")
        ->assertOk()
        ->assertJsonPath('is_liked', false);

    // Other user
    Sanctum::actingAs($otherUser);

    $this->getJson("/api/post/{$post->id}")
        ->assertOk()
        ->assertJsonPath('is_liked', false);
});
