<?php

use Illuminate\Support\Facades\Route;

Route::get('/m/{slug}', fn (string $slug) => view('qr.menu', ['slug' => $slug]));
Route::get('/owner', fn () => view('qr.owner'));

Route::get('/', function () {
    return view('welcome');
});
