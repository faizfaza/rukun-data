<?php

use Illuminate\Support\Facades\Route;

// Root langsung mengarah ke panel (tidak ada landing page publik).
Route::redirect('/', '/admin');
