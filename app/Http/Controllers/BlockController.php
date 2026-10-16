<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserMinifiedResource;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BlockController extends Controller
{
    /**
     * Listar usuarios bloqueados/ocultados pelo usuario autenticado.
     * GET /user/hiddens
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $blockedUsers = $user->blockedUsers()
            ->select(['user.id', 'user.name', 'user.avatar'])
            ->orderByPivot('created_at', 'desc')
            ->paginate(20);

        return UserMinifiedResource::collection($blockedUsers);
    }

    /**
     * Bloquear/ocultar um usuario.
     * POST /user/{user}/block
     */
    public function store(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        if ($currentUser->id === $user->id) {
            return response()->json([
                'message' => 'Voce nao pode bloquear a si mesmo.',
            ], 422);
        }

        $alreadyBlocked = UserBlock::where('user_id', $currentUser->id)
            ->where('blocked_id', $user->id)
            ->exists();

        if ($alreadyBlocked) {
            return response()->json([
                'message' => 'Voce ja bloqueou este usuario.',
            ], 409);
        }

        UserBlock::create([
            'user_id'    => $currentUser->id,
            'blocked_id' => $user->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'blocked'      => true,
            'hidden_count' => $currentUser->blockedUsers()->count(),
        ]);
    }

    /**
     * Desbloquear um usuario.
     * DELETE /user/{user}/block
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        $deleted = UserBlock::where('user_id', $currentUser->id)
            ->where('blocked_id', $user->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Voce nao bloqueou este usuario.',
            ], 404);
        }

        return response()->json([
            'blocked'      => false,
            'hidden_count' => $currentUser->blockedUsers()->count(),
        ]);
    }
}
