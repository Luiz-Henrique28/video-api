<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateFollowCounters;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    /**
     * Seguir um usuario.
     * POST /user/{user}/follow
     */
    public function store(Request $request, User $user): JsonResponse
    {
        $follower = $request->user();

        if ($follower->id === $user->id) {
            return response()->json([
                'message' => 'Voce nao pode seguir a si mesmo.',
            ], 422);
        }

        $alreadyFollowing = Follow::where('follower_id', $follower->id)
            ->where('following_id', $user->id)
            ->exists();

        if ($alreadyFollowing) {
            return response()->json([
                'message' => 'Voce ja segue este usuario.',
            ], 409);
        }

        Follow::create([
            'follower_id'  => $follower->id,
            'following_id' => $user->id,
            'created_at'   => now(),
        ]);

        // Atualiza contadores de forma síncrona e limpa o cache
        UpdateFollowCounters::dispatchSync($follower->id, $user->id, 'follow');

        return response()->json([
            'following'       => true,
            'followers_count' => $user->fresh()->followers_count,
        ]);
    }

    /**
     * Deixar de seguir um usuario.
     * DELETE /user/{user}/follow
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $follower = $request->user();

        $deleted = Follow::where('follower_id', $follower->id)
            ->where('following_id', $user->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Voce nao segue este usuario.',
            ], 404);
        }

        // Atualiza contadores de forma síncrona e limpa o cache
        UpdateFollowCounters::dispatchSync($follower->id, $user->id, 'unfollow');

        return response()->json([
            'following'       => false,
            'followers_count' => $user->fresh()->followers_count,
        ]);
    }
}
