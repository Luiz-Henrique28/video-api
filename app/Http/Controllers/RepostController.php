<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Repost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepostController extends Controller
{
    /**
     * Republicar um post.
     */
    public function store(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();

        if ($user->id === $post->user_id) {
            return response()->json([
                'message' => 'Você não pode republicar a sua própria publicação.'
            ], 422);
        }

        $exists = Repost::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Você já republicou este post.'
            ], 409);
        }

        Repost::create([
            'user_id'    => $user->id,
            'post_id'    => $post->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'reposted'      => true,
            'reposts_count' => $post->reposts()->count(),
        ]);
    }

    /**
     * Desfazer republicação de um post.
     */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();

        $deleted = Repost::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Você não republicou este post.'
            ], 404);
        }

        return response()->json([
            'reposted'      => false,
            'reposts_count' => $post->reposts()->count(),
        ]);
    }
}
