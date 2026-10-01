<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LikeController;
use App\Http\Middleware\EnsureProfileIsComplete;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Route;

// Rate Limiters

RateLimiter::for('follow', function (Request $request) {
    return Limit::perMinute(30)->by($request->user()?->id);
});

// Rotas Publicas

Route::post('/auth/firebase', [AuthController::class, 'authenticateOrRegisterWithFirebase']);

Route::apiResource('post', PostController::class)->only(['index', 'show']);

Route::get('/user/{user:name}', [UserController::class, 'show']);

Route::get('/user/{user}/posts', [UserController::class, 'posts']);

Route::apiResource('search', SearchController::class)->only(['index']);

// Auth obrigatoria

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::patch('/user/username', [UserController::class, 'updateUsername']);

    Route::delete('/user/{user}', [UserController::class, 'destroy']);

    Route::post('/post/{post}/like', [LikeController::class, 'store']);
    Route::delete('/post/{post}/like', [LikeController::class, 'destroy']);
});

// Auth + Perfil completo

Route::middleware(['auth:sanctum', EnsureProfileIsComplete::class])->group(function () {

    Route::apiResource('post', PostController::class)->except(['index', 'show']);

    Route::apiResource('media', MediaController::class)->only(['store', 'destroy']);

    Route::apiResource('comment', CommentController::class)->only(['store', 'update', 'destroy']);

    Route::middleware('throttle:follow')->group(function () {
        Route::post('/user/{user}/follow',   [FollowController::class, 'store']);
        Route::delete('/user/{user}/follow', [FollowController::class, 'destroy']);
    });
});
