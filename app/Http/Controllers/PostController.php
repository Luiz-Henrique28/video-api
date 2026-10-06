<?php

namespace App\Http\Controllers;

use App\Jobs\IncrementPostViews;
use App\Models\Post;
use App\Http\Requests\StorePostRequest;
use App\Services\PostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUser = $request->user('sanctum');

        // retorna exatamente os dados usados em cada card do home (swipes)
        return Post::visibleTo($authUser)
            ->withMediaCounts()
            ->select([
                'id',
                'user_id',
                'caption',
                'thumbnail_path',
                'views_count',
            ])->with([
                'firstMedia' => function ($query) {
                    $query->select('post_id', 'file_path');
                },
                'user' => function ($query) {
                    $query->select('id', 'name', 'avatar');
                }
            ])->paginate(16);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request, PostService $posts)
    {
        $data = $request->validated();

        $post = $posts->create(
            $request->user(),
            ['caption' => $data['caption'] ?? null, 'visibility' => $data['visibility']],
            $data['tags'] ?? [],
            $request->file('files'),
        );

        return response()->json($post, 201);
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
        $authUser = $request->user('sanctum') ?? $request->user();

        // Post privado so e visivel ao dono. 404 para nao revelar que o post existe.
        if ($post->visibility === 'private' && $post->user_id !== $authUser?->id) {
            abort(404);
        }

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

        $post->setAttribute('is_liked', $post->isLikedBy($authUser));
        
        $identifier = $authUser?->id
            ? "user:{$authUser->id}"
            : "ip:{$request->ip()}";

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
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Post $post)
    {
        $this->authorize('delete', $post);

        $deleted = $post->delete();

        return response()->json(['result' => $deleted]);
    }
}
