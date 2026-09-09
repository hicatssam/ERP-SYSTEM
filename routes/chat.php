<?php

use App\Http\Controllers\Chat\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'location.scope',
    'password.changed',
])
    ->prefix('chat')
    ->name('chat.')
    ->group(function (): void {

        Route::get(
            '/',
            [ChatController::class, 'index']
        )->name('index');

        /*
         * Static routes MUST be above /{chatChannel}.
         */
        Route::get(
            '/unread-count',
            [ChatController::class, 'unreadCount']
        )->name('unread-count');

        Route::get(
            '/users',
            [ChatController::class, 'directUsers']
        )->name('users');

        Route::post(
            '/direct/{user}',
            [ChatController::class, 'startDirect']
        )->name('direct.start');

        Route::get(
            '/{chatChannel}',
            [ChatController::class, 'show']
        )->name('show');

        Route::get(
            '/{chatChannel}/messages',
            [ChatController::class, 'messages']
        )->name('messages');

        Route::post(
            '/{chatChannel}/messages',
            [ChatController::class, 'store']
        )->name('messages.store');

        Route::post(
            '/{chatChannel}/read',
            [ChatController::class, 'markRead']
        )->name('read');
    });