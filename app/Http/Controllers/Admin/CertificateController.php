<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Exam;
use App\Models\Result;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    public function index(): Response
    {
        $exams = Exam::with(['subject', 'classLevel', 'template'])
            ->withCount([
                'results as total_results',
                'results as released_results' => fn($q) => $q->where('is_released', true),
                'certificates as student_certs' => fn($q) => $q->where('type', 'student'),
            ])
            ->whereIn('status', ['published', 'archived'])
            ->latest('starts_at')
            ->get();

        return Inertia::render('Admin/Certificates/Index', [
            'exams' => $exams,
        ]);
    }

    public function show(Exam $exam): Response
    {
        $exam->load(['subject', 'classLevel', 'template']);

        $studentCerts = Certificate::where('exam_id', $exam->id)
            ->where('type', 'student')
            ->with('user:id,name,email,school')
            ->latest()
            ->paginate(50);

        $stats = [
            'total_results'  => Result::where('exam_id', $exam->id)->count(),
            'released'       => Result::where('exam_id', $exam->id)->where('is_released', true)->count(),
            'certs_issued'   => Certificate::where('exam_id', $exam->id)->where('type', 'student')->count(),
            'has_template'   => $exam->template !== null,
        ];

        return Inertia::render('Admin/Certificates/Show', [
            'exam'         => $exam,
            'studentCerts' => $studentCerts,
            'stats'        => $stats,
        ]);
    }

    public function upload(Request $request, Exam $exam)
    {
        $request->validate([
            'template' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        // Delete old template if exists
        $old = Certificate::where('exam_id', $exam->id)->where('type', 'template')->first();
        if ($old) {
            Storage::disk('public')->delete($old->file_path);
            $old->delete();
        }

        $file = $request->file('template');
        $path = $file->store("certificates/templates/{$exam->id}", 'public');

        Certificate::create([
            'exam_id'       => $exam->id,
            'type'          => 'template',
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'uploaded_by'   => auth()->id(),
        ]);

        return back()->with('success', 'Certificate template uploaded successfully.');
    }

    public function generate(Exam $exam)
    {
        $template = Certificate::where('exam_id', $exam->id)->where('type', 'template')->first();

        if (! $template) {
            return back()->with('error', 'Upload a certificate template first.');
        }

        $results = Result::where('exam_id', $exam->id)
            ->where('is_released', true)
            ->get();

        if ($results->isEmpty()) {
            return back()->with('error', 'No released results found. Release results before generating certificates.');
        }

        $generated = 0;
        foreach ($results as $result) {
            Certificate::updateOrCreate(
                ['exam_id' => $exam->id, 'user_id' => $result->user_id, 'type' => 'student'],
                [
                    'file_path'    => $template->file_path,
                    'original_name'=> $template->original_name,
                    'mime_type'    => $template->mime_type,
                    'file_size'    => $template->file_size,
                    'uploaded_by'  => auth()->id(),
                    'generated_at' => now(),
                ]
            );
            $generated++;
        }

        return back()->with('success', "{$generated} certificates made available to students.");
    }

    public function deleteTemplate(Exam $exam)
    {
        $template = Certificate::where('exam_id', $exam->id)->where('type', 'template')->first();

        if ($template) {
            Storage::disk('public')->delete($template->file_path);
            $template->delete();
        }

        return back()->with('success', 'Certificate template removed.');
    }

    public function download(Exam $exam)
    {
        $template = Certificate::where('exam_id', $exam->id)->where('type', 'template')->firstOrFail();

        return Storage::disk('public')->download($template->file_path, $template->original_name);
    }
}
