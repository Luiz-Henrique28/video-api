<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    public function index()
    {
        return User::all();
    }

    public function store(Request $request)
    {
        //
    }

    /**
     * Exibe o perfil publico de um usuario com cache.
     * Salva as metricas brutas do perfil no Cache (TTL 1 hora / 3600s).
     * O estado 'is_following' e dinamico por usuario autenticado.
     */
    public function show(Request $request, User $user)
    {
        $profileData = Cache::remember("user_profile:{$user->id}", 3600, function () use ($user) {
            $freshUser = User::find($user->id);

            return [
                'id'              => $freshUser->id,
                'name'            => $freshUser->name,
                'avatar'          => $freshUser->avatar,
                'followers_count'     => (int) $freshUser->followers_count,
                'following_count'     => (int) $freshUser->following_count,
                'profile_views_count' => (int) $freshUser->profile_views_count,
                'posts_count'         => $freshUser->post()->count(),
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
    public function posts(User $user)
    {
        return Post::where('user_id', $user->id)
            ->select(['id', 'user_id', 'caption', 'thumbnail_path', 'created_at', 'views_count'])
            ->with([
                'firstMedia' => function ($query) {
                    $query->select('post_id', 'file_path');
                },
            ])
            ->withCount([
                'media as image_count' => function ($query) {
                    $query->where('media_type', 'image');
                },
                'media as video_count' => function ($query) {
                    $query->where('media_type', 'video');
                },
            ])
            ->latest()
            ->paginate(16);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();
        $user->update($validated);
        return response()->json($user);
    }

    public function destroy(User $user)
    {
        $deleted = $user->delete();
        return response()->json(['result' => $deleted]);
    }

    public function updateUsername(UpdateUserRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();
        $user->update($validated);
        return response()->json($user);
    }
}
