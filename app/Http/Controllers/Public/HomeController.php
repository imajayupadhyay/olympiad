<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\HomepageSection;
use App\Models\Lead;
use App\Services\ExamCatalogueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(ExamCatalogueService $catalogue): Response
    {
        return Inertia::render('Public/Home/Index', [
            // One card per subject — see ExamCatalogueService for why this is rolled
            // up server-side rather than deduped in the page component.
            'upcomingExams' => $catalogue->homepageSubjectCards(),
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

    public function submitContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:120',
            'email'   => 'required|email|max:190',
            'phone'   => 'nullable|string|max:20',
            'message' => 'required|string|max:2000',
        ]);

        Lead::create([
            ...$data,
            'source'     => 'homepage_contact',
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Thanks for reaching out — we will reply within 24 hours.');
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
