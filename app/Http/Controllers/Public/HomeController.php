<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\HomepageSection;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $upcomingExams = Exam::where('status', 'published')
            ->with('subject:id,name')
            ->orderByRaw('starts_at IS NULL, starts_at')
            ->take(8)
            ->get()
            ->map(function (Exam $e) {
                $state = $e->availabilityState();

                return [
                    'name'        => $e->subject?->name ?? $e->name,
                    'description' => $e->description,
                    'ribbon'      => $state === 'live' ? 'LIVE' : '',
                    'fee'         => $e->isFree() ? 'Free' : '₹'.number_format((float) $e->fee_amount, 0),
                ];
            });

        return Inertia::render('Public/Home/Index', [
            'upcomingExams' => $upcomingExams,
            'homepageContent' => HomepageSection::publicPayload(),
        ]);
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
