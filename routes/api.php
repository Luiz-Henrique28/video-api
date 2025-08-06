<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


Route::resource('user', UserController::class)
    ->only(['index', 'show', 'store', 'update', 'destroy']);

Route::resource('post', PostController::class)
    ->only(['index', 'show', 'store', 'update', 'destroy']);

Route::resource('media', MediaController::class)
    ->only(['index', 'show', 'store', 'update', 'destroy']);

Route::resource('comment', CommentController::class)
    ->only(['index', 'show', 'store', 'update', 'destroy']);
