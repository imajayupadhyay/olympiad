<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    public function index(Request $request): Response
    {
        $certs = Certificate::where('user_id', $request->user()->id)
            ->where('type', 'student')
            ->with('exam:id,name,subject_id', 'exam.subject:id,name,icon,color')
            ->latest('generated_at')
            ->get()
            ->map(fn (Certificate $c) => [
                'id'           => $c->id,
                'exam'         => $c->exam?->only(['id', 'name']),
                'subject'      => $c->exam?->subject?->only(['name', 'icon', 'color']),
                'generated_at' => $c->generated_at,
            ]);

        return Inertia::render('Student/Certificates/Index', ['certificates' => $certs]);
    }

    public function download(Request $request, Certificate $certificate)
    {
        abort_unless($certificate->user_id === $request->user()->id, 403);

        abort_unless(Storage::disk('public')->exists($certificate->file_path), 404);

        return Storage::disk('public')->download(
            $certificate->file_path,
            $certificate->original_name ?: 'certificate.pdf'
        );
    }
}
