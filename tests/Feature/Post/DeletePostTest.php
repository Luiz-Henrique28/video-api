<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('requires authentication to delete a post', function () {
    $post = Post::factory()->create();

    $response = $this->deleteJson("/api/post/{$post->id}");

    $response->assertUnauthorized();
});

it('prevents a user from deleting someone elses post', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($intruder)
        ->deleteJson("/api/post/{$post->id}");

    $response->assertForbidden();

    $this->assertDatabaseHas('post', [
        'id' => $post->id,
    ]);
});

it('allows the author to delete their own post', function () {
    $owner = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)
        ->deleteJson("/api/post/{$post->id}");

    $response->assertOk();

    $this->assertDatabaseMissing('post', [
        'id' => $post->id,
    ]);
});

it('removes post media and cleans up storage directory on deletion', function () {
    $disk = config('filesystems.default');
    Storage::fake($disk);

    $owner = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $owner->id]);

    $filePath = "{$post->mediaDirectory()}/image.jpg";
    Storage::disk($disk)->put($filePath, 'fake-image-content');

    $media = $post->media()->create([
        'file_path' => $filePath,
        'media_type' => 'image',
        'order' => 1,
    ]);

    Storage::disk($disk)->assertExists($filePath);

    $response = $this->actingAs($owner)
        ->deleteJson("/api/post/{$post->id}");

    $response->assertOk();

    $this->assertDatabaseMissing('post', ['id' => $post->id]);
    $this->assertDatabaseMissing('media', ['id' => $media->id]);
    Storage::disk($disk)->assertMissing($filePath);
});

it('returns 404 when trying to delete a non-existent post', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->deleteJson('/api/post/999999');

    $response->assertNotFound();
});
