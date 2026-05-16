<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    return response()->json([
        'data' => Str::uuid(),
        'version' => 'v1',
    ]);
});
