<?php

use App\Jobs\GenerateThumbFromVideo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->disk = config('filesystems.default');
    Storage::fake($this->disk);
});

it('requires authentication to create a post', function () {
    $file = UploadedFile::fake()->create('photo.jpg', 500, 'image/jpeg');

    $response = $this->postJson('/api/post', [
        'visibility' => 'public',
        'files' => [$file],
    ]);

    $response->assertUnauthorized();
});

it('fails validation when required fields are missing', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/post', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['visibility', 'files']);
});

it('validates allowed visibility values and file constraints', function () {
    $invalidFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->user)
        ->postJson('/api/post', [
            'visibility' => 'archived',
            'files' => [$invalidFile],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['visibility', 'files.0']);
});

it('successfully creates a post with images and tags', function () {
    $image1 = UploadedFile::fake()->create('first.jpg', 500, 'image/jpeg');
    $image2 = UploadedFile::fake()->create('second.png', 500, 'image/png');

    $payload = [
        'caption' => 'Testing post creation with Pest',
        'visibility' => 'public',
        'tags' => ['technology', 'laravel'],
        'files' => [$image1, $image2],
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/post', $payload);

    $response->assertCreated()
        ->assertJsonPath('caption', 'Testing post creation with Pest')
        ->assertJsonCount(2, 'media');

    $postId = $response->json('id');

    $this->assertDatabaseHas('post', [
        'id' => $postId,
        'user_id' => $this->user->id,
        'caption' => 'Testing post creation with Pest',
        'visibility' => 'public',
    ]);

    $this->assertDatabaseHas('tag', ['name' => 'technology']);
    $this->assertDatabaseHas('tag', ['name' => 'laravel']);

    $this->assertDatabaseCount('media', 2);
});

it('successfully creates a post with a video and dispatches thumbnail generation', function () {
    Queue::fake();

    $video = UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4');

    $payload = [
        'caption' => 'Video upload test',
        'visibility' => 'private',
        'files' => [$video],
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/post', $payload);

    $response->assertCreated()
        ->assertJsonCount(1, 'media');

    $postId = $response->json('id');

    $this->assertDatabaseHas('post', [
        'id' => $postId,
        'user_id' => $this->user->id,
        'visibility' => 'private',
    ]);

    Queue::assertPushed(GenerateThumbFromVideo::class);
});
