<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return auth()->check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    }
}
