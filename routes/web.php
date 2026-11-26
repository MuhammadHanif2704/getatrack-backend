<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/storage/{path}', function (string $path) {
    $baseDir = env('VERCEL') ? '/tmp/storage/public' : storage_path('app/public');
    $fullPath = $baseDir . '/' . ltrim($path, '/');

    if (!file_exists($fullPath)) {
        abort(404);
    }

    return response()->file($fullPath);
})->where('path', '.*');