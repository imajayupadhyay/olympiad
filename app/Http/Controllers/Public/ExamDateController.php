<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\ExamCatalogueService;
use Inertia\Inertia;
use Inertia\Response;

class ExamDateController extends Controller
{
    /**
     * Display-only calendar of upcoming olympiad sessions, built straight from the
     * published exams' schedules — editing an exam's date or time in the admin
     * updates this page on the next load.
     */
    public function index(ExamCatalogueService $catalogue): Response
    {
        return Inertia::render('Public/ExamDates/Index', [
            'sessions' => $catalogue->upcomingSchedule(),
        ]);
    }
}
