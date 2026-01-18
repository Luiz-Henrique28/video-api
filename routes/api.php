<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::prefix('auth')->group(function () {
    
    // Rota PÚBLICA (Guest entra aqui)
    Route::post('/firebase', [AuthController::class, 'authenticateOrRegisterWithFirebase']);

    // Rotas PROTEGIDAS de Auth
    Route::middleware('auth:sanctum')->group(function() {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

/* ROTAS PÚBLICAS 
   Guests podem ver Posts.
   Como os comentários vêm junto com o post (via relacionamento), 
   não precisamos de rota pública para CommentController.
*/
Route::apiResource('post', PostController::class)->only(['index', 'show']);

/* ROTAS PROTEGIDAS 
   Aqui fica a lógica de escrita.
*/
Route::middleware('auth:sanctum')->group(function () {
    
    // Escrita de Posts
    Route::apiResource('post', PostController::class)->except(['index', 'show']);

    // Escrita de Comentários (Criar, Editar, Deletar)
    // O 'index' e 'show' foram removidos pois não são usados via API direta
    Route::apiResource('comment', CommentController::class)
        ->only(['store', 'update', 'destroy']);

    // Demais rotas
    Route::apiResource('user', UserController::class);
    Route::apiResource('media', MediaController::class);
});