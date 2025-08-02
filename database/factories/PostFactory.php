<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'caption' => $this->faker->sentence(10),
            'visibility' => $this->faker->randomElement(['public', 'private']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
