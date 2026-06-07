<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class ExamController extends Controller
{
    public function index() { return Inertia::render('Student/Exam/Index'); }
}
