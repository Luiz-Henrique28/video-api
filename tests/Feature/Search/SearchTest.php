<?php

use App\Models\Tag;
use App\Models\User;

it('allows guest visitors to perform search without authentication', function () {
    $response = $this->getJson('/api/search?q=test');

    $response->assertOk();
});

it('returns matching users with correct structure', function () {
    $user = User::factory()->create([
        'name' => 'marcos_dev',
        'avatar' => 'https://example.com/avatar.png',
    ]);

    $response = $this->getJson('/api/search?q=marcos');

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $user->id,
            'label' => 'marcos_dev',
            'image' => 'https://example.com/avatar.png',
            'type' => 'user',
        ]);
});

it('returns matching tags with correct structure and null image', function () {
    $tag = Tag::create([
        'name' => 'javascript',
    ]);

    $response = $this->getJson('/api/search?q=java');

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $tag->id,
            'label' => 'javascript',
            'image' => null,
            'type' => 'tag',
        ]);
});

it('returns unified results combining both users and tags', function () {
    $user = User::factory()->create([
        'name' => 'vue_master',
    ]);

    $tag = Tag::create([
        'name' => 'vuejs',
    ]);

    $response = $this->getJson('/api/search?q=vue');

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $user->id,
            'label' => 'vue_master',
            'type' => 'user',
        ])
        ->assertJsonFragment([
            'id' => $tag->id,
            'label' => 'vuejs',
            'type' => 'tag',
        ]);
});

it('returns empty array when no users or tags match query', function () {
    $response = $this->getJson('/api/search?q=nonexistent_xyz_999');

    $response->assertOk()
        ->assertExactJson([]);
});

it('limits the combined results to a maximum of 15 items', function () {
    // Create 10 users starting with 'search_test'
    for ($i = 1; $i <= 10; $i++) {
        User::factory()->create(['name' => "search_test_user_{$i}"]);
    }

    // Create 10 tags containing 'search_test'
    for ($i = 1; $i <= 10; $i++) {
        Tag::create(['name' => "search_test_tag_{$i}"]);
    }

    $response = $this->getJson('/api/search?q=search_test');

    $response->assertOk();
    expect(count($response->json()))->toBe(15);
});
