<?php

use Illuminate\Support\Facades\Route;

// Auth routes (Breeze — login, register, password reset, email verify)
require __DIR__.'/auth.php';

// Public marketing website
require __DIR__.'/public.php';

// Student portal (auth + verified required — defined inside student.php)
require __DIR__.'/student.php';

// Admin portal (auth + admin role required — defined inside admin.php)
require __DIR__.'/admin.php';
