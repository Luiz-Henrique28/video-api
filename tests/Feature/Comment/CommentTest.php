<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;

it('requires authentication to create a comment', function () {
    $post = Post::factory()->create();

    $response = $this->postJson('/api/comment', [
        'post_id' => $post->id,
        'content' => 'Nice video!',
    ]);

    $response->assertUnauthorized();
});

it('blocks users with incomplete profile from creating comments', function () {
    $userWithoutName = User::factory()->create(['name' => null]);
    $post = Post::factory()->create();

    $response = $this->actingAs($userWithoutName)
        ->postJson('/api/comment', [
            'post_id' => $post->id,
            'content' => 'Nice video!',
        ]);

    $response->assertForbidden()
        ->assertJsonPath('code', 'USERNAME_REQUIRED');
});

it('validates required fields when creating a comment', function () {
    $user = User::factory()->create(['name' => 'commenter_user']);

    $response = $this->actingAs($user)
        ->postJson('/api/comment', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['post_id', 'content']);
});

it('successfully creates a comment for a post', function () {
    $user = User::factory()->create(['name' => 'commenter_user']);
    $post = Post::factory()->create();

    $payload = [
        'post_id' => $post->id,
        'content' => 'Great content, loved the tutorial!',
    ];

    $response = $this->actingAs($user)
        ->postJson('/api/comment', $payload);

    $response->assertCreated()
        ->assertJsonPath('content', 'Great content, loved the tutorial!')
        ->assertJsonPath('user.name', 'commenter_user');

    $commentId = $response->json('id');

    $this->assertDatabaseHas('comment', [
        'id' => $commentId,
        'post_id' => $post->id,
        'user_id' => $user->id,
        'content' => 'Great content, loved the tutorial!',
    ]);
});

it('requires authentication to delete a comment', function () {
    $comment = Comment::factory()->create();

    $response = $this->deleteJson("/api/comment/{$comment->id}");

    $response->assertUnauthorized();
});

it('prevents a user from deleting someone elses comment', function () {
    $author = User::factory()->create(['name' => 'author_user']);
    $intruder = User::factory()->create(['name' => 'intruder_user']);
    $comment = Comment::factory()->create(['user_id' => $author->id]);

    $response = $this->actingAs($intruder)
        ->deleteJson("/api/comment/{$comment->id}");

    $response->assertForbidden();

    $this->assertDatabaseHas('comment', [
        'id' => $comment->id,
    ]);
});

it('allows the author to delete their own comment', function () {
    $author = User::factory()->create(['name' => 'author_user']);
    $comment = Comment::factory()->create(['user_id' => $author->id]);

    $response = $this->actingAs($author)
        ->deleteJson("/api/comment/{$comment->id}");

    $response->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('comment', [
        'id' => $comment->id,
    ]);
});

it('returns 404 when trying to delete a non-existent comment', function () {
    $user = User::factory()->create(['name' => 'regular_user']);

    $response = $this->actingAs($user)
        ->deleteJson('/api/comment/999999');

    $response->assertNotFound();
});
