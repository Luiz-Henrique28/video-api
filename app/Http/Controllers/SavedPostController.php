<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostCardResource;
use App\Models\Post;
use App\Models\SavedPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SavedPostController extends Controller
{
    /**
     * Listar posts salvos do usuario autenticado.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $posts = $user->savedPosts()
            ->visibleTo($user)
            ->withMediaCounts()
            ->select([
                'post.id',
                'post.user_id',
                'post.caption',
                'post.thumbnail_path',
                'post.views_count',
                'post.created_at',
            ])
            ->with([
                'firstMedia' => function ($query) {
                    $query->select('post_id', 'file_path');
                },
                'user' => function ($query) {
                    $query->select('id', 'name', 'avatar');
                }
            ])
            ->orderByPivot('created_at', 'desc')
            ->paginate(16);

        return PostCardResource::collection($posts);
    }

    /**
     * Salvar um post.
     */
    public function store(Request $request, Post $post): JsonResponse
    {
        $userId = $request->user()->id;

        $exists = SavedPost::where('user_id', $userId)
            ->where('post_id', $post->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Você já salvou este post.'
            ], 409);
        }

        SavedPost::create([
            'user_id' => $userId,
            'post_id' => $post->id,
        ]);

        return response()->json([
            'saved' => true,
        ]);
    }

    /**
     * Remover post dos salvos.
     */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        $userId = $request->user()->id;

        $deleted = SavedPost::where('user_id', $userId)
            ->where('post_id', $post->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Você não salvou este post.'
            ], 404);
        }

        return response()->json([
            'saved' => false,
        ]);
    }
}
