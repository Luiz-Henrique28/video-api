<?php

use App\Models\Post;
use App\Models\User;
use App\Models\UserBlock;
use Laravel\Sanctum\Sanctum;

it('requires authentication to block, unblock and list hidden users', function () {
    $target = User::factory()->create();

    $this->postJson("/api/user/{$target->id}/block")->assertUnauthorized();
    $this->deleteJson("/api/user/{$target->id}/block")->assertUnauthorized();
    $this->getJson('/api/user/hiddens')->assertUnauthorized();
});

it('successfully blocks and unblocks a user', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    Sanctum::actingAs($user);

    // Block
    $response = $this->postJson("/api/user/{$target->id}/block");
    $response->assertOk()
        ->assertJson([
            'blocked' => true,
            'hidden_count' => 1,
        ]);

    $this->assertDatabaseHas('user_block', [
        'user_id'    => $user->id,
        'blocked_id' => $target->id,
    ]);

    // Unblock
    $unblockResponse = $this->deleteJson("/api/user/{$target->id}/block");
    $unblockResponse->assertOk()
        ->assertJson([
            'blocked' => false,
            'hidden_count' => 0,
        ]);

    $this->assertDatabaseMissing('user_block', [
        'user_id'    => $user->id,
        'blocked_id' => $target->id,
    ]);
});

it('prevents a user from blocking themselves', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/user/{$user->id}/block");
    $response->assertStatus(422);
});

it('returns 409 when blocking an already blocked user', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/user/{$target->id}/block");
    $response = $this->postJson("/api/user/{$target->id}/block");

    $response->assertStatus(409);
});

it('returns 404 when trying to unblock a user that is not blocked', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->deleteJson("/api/user/{$target->id}/block");
    $response->assertNotFound()
        ->assertJsonPath('message', 'Voce nao bloqueou este usuario.');
});

it('returns 404 when attempting to block a non-existent user', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/user/999999/block');
    $response->assertNotFound();
});

it('lists hidden users for authenticated user', function () {
    $user = User::factory()->create();
    $target1 = User::factory()->create(['name' => 'bloqueado_1']);
    $target2 = User::factory()->create(['name' => 'bloqueado_2']);

    Sanctum::actingAs($user);

    $this->postJson("/api/user/{$target1->id}/block");
    $this->postJson("/api/user/{$target2->id}/block");

    $response = $this->getJson('/api/user/hiddens');
    $response->assertOk();

    $data = $response->json('data');
    expect(count($data))->toBe(2);
    expect(collect($data)->pluck('id')->contains($target1->id))->toBeTrue();
    expect(collect($data)->pluck('id')->contains($target2->id))->toBeTrue();
});

it('excludes posts of blocked users from feed for authenticated user', function () {
    $user = User::factory()->create();
    $blocked = User::factory()->create();
    $normal = User::factory()->create();

    $postFromBlocked = Post::factory()->create(['user_id' => $blocked->id, 'visibility' => 'public']);
    $postFromNormal = Post::factory()->create(['user_id' => $normal->id, 'visibility' => 'public']);

    Sanctum::actingAs($user);
    $this->postJson("/api/user/{$blocked->id}/block");

    $response = $this->getJson('/api/post');
    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->contains($postFromNormal->id))->toBeTrue();
    expect($ids->contains($postFromBlocked->id))->toBeFalse();
});

it('excludes blocked users from search results for authenticated user', function () {
    $user = User::factory()->create();
    $blocked = User::factory()->create(['name' => 'alice_bloqueada']);
    $normal = User::factory()->create(['name' => 'alice_normal']);

    Sanctum::actingAs($user);
    $this->postJson("/api/user/{$blocked->id}/block");

    $response = $this->getJson('/api/search?q=alice');
    $response->assertOk();

    $labels = collect($response->json())->pluck('label');
    expect($labels->contains('alice_normal'))->toBeTrue();
    expect($labels->contains('alice_bloqueada'))->toBeFalse();
});

it('excludes posts when authenticated user accesses posts endpoint of a blocked user', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    Post::factory()->create([
        'user_id' => $target->id,
        'visibility' => 'public',
        'caption' => 'Post do usuario bloqueado',
    ]);

    Sanctum::actingAs($user);
    $this->postJson("/api/user/{$target->id}/block");

    $response = $this->getJson("/api/user/{$target->id}/posts");
    $response->assertOk()
        ->assertJsonCount(0, 'data');
});
