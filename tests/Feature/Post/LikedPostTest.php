<?php

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication to list liked posts', function () {
    $response = $this->getJson('/api/user/liked-posts');

    $response->assertUnauthorized();
});

it('lists liked posts for authenticated user', function () {
    $user = User::factory()->create();
    $post1 = Post::factory()->create(['caption' => 'Post Curtido 1', 'visibility' => 'public']);
    $post2 = Post::factory()->create(['caption' => 'Post Curtido 2', 'visibility' => 'public']);
    $post3 = Post::factory()->create(['caption' => 'Post Nao Curtido', 'visibility' => 'public']);

    Like::create(['user_id' => $user->id, 'post_id' => $post1->id]);
    Like::create(['user_id' => $user->id, 'post_id' => $post2->id]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/user/liked-posts');

    $response->assertOk();
    $data = $response->json('data');

    expect(count($data))->toBe(2);
    expect(collect($data)->pluck('id')->contains($post1->id))->toBeTrue();
    expect(collect($data)->pluck('id')->contains($post2->id))->toBeTrue();
    expect(collect($data)->pluck('id')->contains($post3->id))->toBeFalse();
});
