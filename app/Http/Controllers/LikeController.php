<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    /**
     * Curtir um post.
     */
    public function store(Request $request, Post $post)
    {
        $userId = $request->user()->id;

        $exists = Like::where('user_id', $userId)
            ->where('post_id', $post->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Você já curtiu este post.'
            ], 409);
        }

        Like::create([
            'user_id' => $userId,
            'post_id' => $post->id,
        ]);

        return response()->json([
            'liked' => true,
            'likes_count' => $post->likes()->count(),
        ]);
    }

    /**
     * Descurtir um post.
     */
    public function destroy(Request $request, Post $post)
    {
        $userId = $request->user()->id;

        $deleted = Like::where('user_id', $userId)
            ->where('post_id', $post->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Você não curtiu este post.'
            ], 404);
        }

        return response()->json([
            'liked' => false,
            'likes_count' => $post->likes()->count(),
        ]);
    }
}
