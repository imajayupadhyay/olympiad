<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Public/Home/Index');
    }

    public function about(): Response
    {
        return Inertia::render('Public/About/Index');
    }

    public function exams(): Response
    {
        return Inertia::render('Public/Exams/Index');
    }

    public function results(): Response
    {
        return Inertia::render('Public/Results/Index');
    }

    public function blog(): Response
    {
        return Inertia::render('Public/Blog/Index');
    }

    public function contact(): Response
    {
        return Inertia::render('Public/Contact/Index');
    }

    public function pricing(): Response
    {
        return Inertia::render('Public/Pricing/Index');
    }

    public function faq(): Response
    {
        return Inertia::render('Public/Faq/Index');
    }
}
