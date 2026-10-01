<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// The API has no public web pages; the admin panel lives at /admin (Filament).
Route::redirect('/', '/admin');
