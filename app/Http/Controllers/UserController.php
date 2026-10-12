<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\PostCardResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    /**
     * Exibe o perfil publico de um usuario com cache.
     * Salva as metricas brutas do perfil no Cache (TTL 1 hora / 3600s).
     * O estado 'is_following' e dinamico por usuario autenticado.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $profileData = Cache::remember("user_profile:{$user->id}", 3600, function () use ($user) {
            $freshUser = User::find($user->id);

            return [
                'id'              => $freshUser->id,
                'name'            => $freshUser->name,
                'avatar'          => $freshUser->avatar,
                'followers_count' => (int) $freshUser->followers_count,
                'following_count' => (int) $freshUser->following_count,
                'total_views'     => (int) $freshUser->total_views,
                'posts_count'     => $freshUser->post()->count(),
            ];
        });

        $authUser = $request->user('sanctum');
        $profileData['is_following'] = $authUser ? $authUser->isFollowing($user) : null;
        $profileData['is_blocked']   = $authUser ? $authUser->isBlocking($user) : false;
        $profileData['hidden_count'] = ($authUser && $authUser->id === $user->id)
            ? $authUser->blockedUsers()->count()
            : 0;
        $profileData['reposts_count'] = $user->repostedPosts()->count();

        return response()->json(['data' => $profileData]);
    }

    /**
     * Lista os posts de um usuario especifico.
     * GET /user/{user}/posts
     */
    public function posts(Request $request, User $user): AnonymousResourceCollection
    {
        $authUser = $request->user('sanctum') ?? $request->user();
        $type = $request->query('type', 'all');

        if ($type === 'posts') {
            $posts = Post::visibleTo($authUser)
                ->where('post.user_id', $user->id)
                ->withMediaCounts()
                ->select(['post.id', 'post.user_id', 'post.caption', 'post.thumbnail_path', 'post.created_at', 'post.views_count'])
                ->with([
                    'firstMedia' => fn ($query) => $query->select('post_id', 'file_path'),
                    'user'       => fn ($query) => $query->select('id', 'name', 'avatar'),
                ])
                ->latest('post.created_at')
                ->paginate(16);

            $posts->getCollection()->transform(function ($post) {
                $post->setAttribute('is_repost', false);
                return $post;
            });

            return PostCardResource::collection($posts);
        }

        if ($type === 'reposts') {
            $posts = $user->repostedPosts()
                ->visibleTo($authUser)
                ->withMediaCounts()
                ->select([
                    'post.id',
                    'post.user_id',
                    'post.caption',
                    'post.thumbnail_path',
                    'post.created_at',
                    'post.views_count',
                ])
                ->with([
                    'firstMedia' => fn ($query) => $query->select('post_id', 'file_path'),
                    'user'       => fn ($query) => $query->select('id', 'name', 'avatar'),
                ])
                ->orderByPivot('created_at', 'desc')
                ->paginate(16);

            $posts->getCollection()->transform(function ($post) {
                $post->setAttribute('is_repost', true);
                return $post;
            });

            return PostCardResource::collection($posts);
        }

        // type === 'all' (publicacoes proprias + reposts)
        $ownQuery = Post::visibleTo($authUser)
            ->where('post.user_id', $user->id)
            ->select([
                'post.id as post_id',
                \Illuminate\Support\Facades\DB::raw('0 as is_repost'),
                'post.created_at as activity_at',
            ]);

        $repostQuery = Post::visibleTo($authUser)
            ->join('repost', 'repost.post_id', '=', 'post.id')
            ->where('repost.user_id', $user->id)
            ->select([
                'post.id as post_id',
                \Illuminate\Support\Facades\DB::raw('1 as is_repost'),
                'repost.created_at as activity_at',
            ]);

        $union = $ownQuery->unionAll($repostQuery);

        $feedItems = \Illuminate\Support\Facades\DB::table($union, 'feed')
            ->orderBy('activity_at', 'desc')
            ->paginate(16);

        $postIds = collect($feedItems->items())->pluck('post_id')->unique()->values();

        $postsById = Post::whereIn('id', $postIds)
            ->withMediaCounts()
            ->select(['id', 'user_id', 'caption', 'thumbnail_path', 'created_at', 'views_count'])
            ->with([
                'firstMedia' => fn ($query) => $query->select('post_id', 'file_path'),
                'user'       => fn ($query) => $query->select('id', 'name', 'avatar'),
            ])
            ->get()
            ->keyBy('id');

        $posts = $feedItems->getCollection()->map(function ($item) use ($postsById) {
            $original = $postsById->get($item->post_id);
            if (!$original) return null;
            $post = clone $original;
            $post->setAttribute('is_repost', (bool) $item->is_repost);
            return $post;
        })->filter()->values();

        $feedItems->setCollection($posts);

        return PostCardResource::collection($feedItems);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();
        $user->update($validated);
        return response()->json($user);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->forceDelete();

        return response()->json(['message' => 'Conta deletada com sucesso']);
    }

    public function updateUsername(UpdateUserRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $user->update($validated);
        return response()->json($user);
    }
}
