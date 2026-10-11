<?php

use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication to save a post', function () {
    $post = Post::factory()->create(['visibility' => 'public']);

    $response = $this->postJson("/api/post/{$post->id}/save");

    $response->assertUnauthorized();
});

it('successfully saves a post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['visibility' => 'public']);

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/post/{$post->id}/save");

    $response->assertOk()
        ->assertJson([
            'saved' => true,
        ]);

    $this->assertDatabaseHas('saved_post', [
        'user_id' => $user->id,
        'post_id' => $post->id,
    ]);
});

it('returns 409 when saving an already saved post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['visibility' => 'public']);

    Sanctum::actingAs($user);

    $this->postJson("/api/post/{$post->id}/save");

    $response = $this->postJson("/api/post/{$post->id}/save");

    $response->assertStatus(409)
        ->assertJson([
            'message' => 'Você já salvou este post.',
        ]);
});

it('successfully unsaves a post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['visibility' => 'public']);

    Sanctum::actingAs($user);

    $this->postJson("/api/post/{$post->id}/save");

    $response = $this->deleteJson("/api/post/{$post->id}/save");

    $response->assertOk()
        ->assertJson([
            'saved' => false,
        ]);

    $this->assertDatabaseMissing('saved_post', [
        'user_id' => $user->id,
        'post_id' => $post->id,
    ]);
});

it('returns 404 when removing a post that is not saved', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['visibility' => 'public']);

    Sanctum::actingAs($user);

    $response = $this->deleteJson("/api/post/{$post->id}/save");

    $response->assertNotFound();
});

it('returns is_saved true in post detail when user saved the post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['visibility' => 'public']);

    Sanctum::actingAs($user);

    $this->postJson("/api/post/{$post->id}/save");

    $response = $this->getJson("/api/post/{$post->id}");

    $response->assertOk()
        ->assertJsonPath('is_saved', true);
});

it('returns is_saved false in post detail when user has not saved the post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['visibility' => 'public']);

    Sanctum::actingAs($user);

    $response = $this->getJson("/api/post/{$post->id}");

    $response->assertOk()
        ->assertJsonPath('is_saved', false);
});

it('lists saved posts for authenticated user', function () {
    $user = User::factory()->create();
    $post1 = Post::factory()->create(['caption' => 'Post Salvo 1', 'visibility' => 'public']);
    $post2 = Post::factory()->create(['caption' => 'Post Salvo 2', 'visibility' => 'public']);
    $post3 = Post::factory()->create(['caption' => 'Post Nao Salvo', 'visibility' => 'public']);

    Sanctum::actingAs($user);

    $this->postJson("/api/post/{$post1->id}/save");
    $this->postJson("/api/post/{$post2->id}/save");

    $response = $this->getJson('/api/user/saved-posts');

    $response->assertOk();
    $data = $response->json('data');

    expect(count($data))->toBe(2);
    expect(collect($data)->pluck('id')->contains($post1->id))->toBeTrue();
    expect(collect($data)->pluck('id')->contains($post2->id))->toBeTrue();
    expect(collect($data)->pluck('id')->contains($post3->id))->toBeFalse();
});
