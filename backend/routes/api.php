<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::get('/health', fn () => response()
    ->json(['status' => 'ok'])
    ->header('Cache-Control', 'no-store'))
    ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class);

Route::get('/user', function (Request $request) {
    return response()
        ->json($request->user()->only(['id', 'name', 'email']))
        ->header('Cache-Control', 'no-store');
})->middleware('auth:sanctum');
