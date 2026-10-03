<?php

namespace App\Http\Controllers;

use App\Jobs\IncrementPostViews;
use App\Models\Post;
use App\Http\Requests\StorePostRequest;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // retorna exatamente os dados usados em cada card do home (swipes)
        return Post::select([
            'id',
            'user_id',
            'caption',
            'thumbnail_path',
            'views_count',
        ])->with([
            'firstMedia' => function($query) {
                $query->select('post_id', 'file_path');
            },
            'user' => function ($query) {
                $query->select('id', 'name', 'avatar');
            }
        ])->withCount([
            'media as image_count' => function ($query) {
                $query->where('media_type', 'image');
            },
            'media as video_count' => function ($query) {
                $query->where('media_type', 'video');
            }
        ])->paginate(16);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request)
    {
        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;

        $newPost = Post::create($validated);

        $tags = $request->input('tags', []);

        $tagIds = collect($tags)->map(function ($tagName) {
            return Tag::firstOrCreate(['name' => $tagName])->id;
        });

        $newPost->tag()->attach($tagIds);

        return $newPost;
    }

    /**
     * Exibe os detalhes de um post especifico.
     *
     * Fluxo de visualizacao (hibrido):
     *  1. Check rapido no Controller (filtro): evita criar Jobs desnecessarios
     *     para visitantes que ja viram o post nas ultimas 4h.
     *  2. Check definitivo dentro do Job (guarda): resolve race conditions raras
     *     de requests simultaneos do mesmo visitante.
     */
    public function show(Request $request, Post $post)
    {
        $post->load(['media', 'comment.user:id,name', 'tag', 'user'])
            ->loadCount([
                'media as image_count' => function ($query) {
                    $query->where('media_type', 'image');
                },
                'media as video_count' => function ($query) {
                    $query->where('media_type', 'video');
                },
                'likes as likes_count',
            ]);

        $authUser = $request->user('sanctum') ?? $request->user();
        $post->setAttribute('is_liked', $post->isLikedBy($authUser));

        // Identificador do visitante: user ID (logado) ou IP (anonimo)
        $identifier = $authUser?->id
            ? "user:{$authUser->id}"
            : "ip:{$request->ip()}";

        Log::debug(['teste: ',Cache::has("post_view_seen:{$post->id}:{$identifier}")]);
        // Check rapido: so dispara o Job se ainda nao contabilizou nas ultimas 4h
        if (!Cache::has("post_view_seen:{$post->id}:{$identifier}")) {
            IncrementPostViews::dispatch(
                postId:        $post->id,
                visitorIp:     $request->ip(),
                visitorUserId: $authUser?->id,
            );
        }

        return $post;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Post $post)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Post $post)
    {
        if ($post->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $deleted = $post->delete();

        return response()->json(['result' => $deleted]);
    }
}
