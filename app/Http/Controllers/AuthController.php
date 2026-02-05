<?php

namespace App\Http\Controllers;

use App\Http\Requests\FirebaseAuthRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class AuthController extends Controller
{

    public function authenticateOrRegisterWithFirebase(FirebaseAuthRequest $request)
    {

        $firebaseToken = $request->validated()['firebase_token'];

        try {
            $auth = app('firebase.auth');
            $verifiedIdToken = $auth->verifyIdToken($firebaseToken);
        } catch (FailedToVerifyToken $e) {
            return response()->json([
                'message' => 'Token inválido ou expirado.',
            ], 401);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Erro ao processar autenticação externa.',
            ], 500);
        }

        $email = $verifiedIdToken->claims()->get('email');
        //$name = $verifiedIdToken->claims()->get('name');
        $picture = $verifiedIdToken->claims()->get('picture');

        $firebaseData = $verifiedIdToken->claims()->get('firebase');
        $provider = $firebaseData['sign_in_provider'] ?? 'unknown';

        $providerId = $verifiedIdToken->claims()->get('sub');

        $user = User::firstOrCreate(
            ['email' => $email],
            ['avatar' => $picture, 'provider' => $provider, 'provider_id' => $providerId]
        );

        if (!$user->wasRecentlyCreated) {
            $user->update([
                'provider_id' => $providerId,
                'avatar' => $picture,
                // 'name' => $name // Descomente se quiser forçar o nome do Google
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function me(Request $request) {
        return response()->json($request->user());
    }


    public function logout(Request $request)
    {

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'token revoked']);
    }
}
