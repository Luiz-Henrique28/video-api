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

        return response()->json(['data' => $profileData]);
    }

    /**
     * Lista os posts de um usuario especifico.
     * GET /user/{user}/posts
     */
    public function posts(Request $request, User $user): AnonymousResourceCollection
    {
        $posts = Post::visibleTo($request->user('sanctum'))
            ->where('user_id', $user->id)
            ->withMediaCounts()
            ->select(['id', 'user_id', 'caption', 'thumbnail_path', 'created_at', 'views_count'])
            ->with([
                'firstMedia' => function ($query) {
                    $query->select('post_id', 'file_path');
                },
            ])
            ->latest()
            ->paginate(16);

        return PostCardResource::collection($posts);
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
