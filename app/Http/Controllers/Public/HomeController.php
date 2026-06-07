<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $upcomingExams = Exam::where('status', 'published')
            ->with('subject:id,name', 'classLevel:id,label')
            ->withCount('questions')
            ->orderByRaw('starts_at IS NULL, starts_at')
            ->take(8)
            ->get()
            ->map(function (Exam $e) {
                $state = $e->availabilityState();

                return [
                    'name'   => $e->name,
                    'sub'    => trim(($e->subject?->name ?? 'Olympiad').' · '.($e->classLevel?->label ?? '')),
                    'date'   => $e->starts_at ? $e->starts_at->format('d M Y') : 'TBA',
                    'ribbon' => $state === 'live' ? 'LIVE' : '',
                    'fee'    => $e->isFree() ? 'Free' : '₹'.number_format((float) $e->fee_amount, 0),
                    'pills'  => [
                        '⏱️ '.$e->duration_minutes.' min',
                        '📋 '.$e->questions_count.' MCQ',
                        '🎓 '.($e->classLevel?->label ?? 'All classes'),
                    ],
                ];
            });

        return Inertia::render('Public/Home/Index', [
            'upcomingExams' => $upcomingExams,
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
