<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class LeaderboardController extends Controller
{
    public function index() { return Inertia::render('Student/Leaderboard/Index'); }
}
