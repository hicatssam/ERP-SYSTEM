<?php

use App\Http\Controllers\PwaController;
use Illuminate\Support\Facades\Route;

Route::get('/pwa/manifest.webmanifest', [PwaController::class, 'manifest'])
    ->name('pwa.manifest');

Route::get('/pwa/icon-{size}.png', [PwaController::class, 'icon'])
    ->whereIn('size', [32, 180, 192, 512])
    ->name('pwa.icon');

Route::get('/menu/{location:code}/manifest.webmanifest', [PwaController::class, 'customerMenuManifest'])
    ->name('pwa.customer-manifest');
