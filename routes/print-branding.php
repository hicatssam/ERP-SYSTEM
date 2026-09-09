<?php

use App\Http\Controllers\Admin\PrintBrandingController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'location.scope',
    'password.changed',
    'can:settings.manage',
])->prefix('settings')
  ->name('settings.')
  ->group(function (): void {
      Route::get(
          '/print-branding',
          [PrintBrandingController::class, 'edit']
      )->name('print-branding.edit');

      Route::put(
          '/print-branding',
          [PrintBrandingController::class, 'update']
      )->name('print-branding.update');

      Route::get(
          '/print-branding/preview',
          [PrintBrandingController::class, 'preview']
      )->name('print-branding.preview');
  });
