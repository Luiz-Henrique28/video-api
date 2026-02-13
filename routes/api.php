<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\EnsureProfileIsComplete;
use Illuminate\Support\Facades\Route;

Route::post('/auth/firebase', [AuthController::class, 'authenticateOrRegisterWithFirebase']);

Route::apiResource('post', PostController::class)->only(['index', 'show']);

Route::get('/users/{user:name}', [UserController::class, 'show']);

Route::apiResource('search', SearchController::class)->only(['index']);



Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::patch('/user/username', [UserController::class, 'updateUsername']);

    Route::delete('/user/{user}', [UserController::class, 'destroy']);
});


Route::middleware(['auth:sanctum', EnsureProfileIsComplete::class])->group(function () {

    Route::apiResource('post', PostController::class)->except(['index', 'show']);

    Route::apiResource('media', MediaController::class)->only(['store', 'destroy']);

    Route::apiResource('comment', CommentController::class)->only(['store', 'update', 'destroy']);

});