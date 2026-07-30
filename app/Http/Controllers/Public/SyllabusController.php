<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SyllabusController extends Controller
{
    /**
     * The syllabus handbook (Class 1–10, 8 subjects).
     *
     * Content is currently static and lives with the page in
     * `Pages/Public/Syllabus/syllabus.data.js`. When it becomes admin-managed,
     * pass the same shape here as a `syllabus` prop — the page already prefers
     * the prop over its bundled fallback.
     */
    public function index(): Response
    {
        return Inertia::render('Public/Syllabus/Index');
    }
}
