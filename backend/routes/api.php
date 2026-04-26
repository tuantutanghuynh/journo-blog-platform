<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PostController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Public routs
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/posts',      [PostController::class, 'index']);
Route::get('/posts/{id}', [PostController::class, 'show']);


//protected routes - must have token to enter
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/posts',           [PostController::class, 'store']);
    Route::put('/posts/{id}',       [PostController::class, 'update']);
    Route::delete('/posts/{id}',    [PostController::class, 'destroy']);
});
