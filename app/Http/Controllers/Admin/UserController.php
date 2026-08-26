<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\User;
use App\Rules\ValidPhoneNumber;
use App\Services\ManagedEmailService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private function meta(): array
    {
        return [
            'classLevels' => ClassLevel::active(),
            'states' => User::indianStates(),
        ];
    }

    private function assertStudent(User $user): void
    {
        abort_if($user->role !== 'student', 404);
    }

    private function enrollmentPayload(User $user): array
    {
        return $user->enrollments()
            ->with([
                'exam.subject:id,name,icon,color',
                'exam.classLevel:id,label',
                'assignedByAdmin:id,name',
            ])
            ->latest('enrolled_at')
            ->latest()
            ->get()
            ->map(fn (ExamEnrollment $enrollment) => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'enrollment_source' => $enrollment->enrollment_source,
                'amount' => (float) $enrollment->amount,
                'currency' => $enrollment->currency,
                'enrolled_at' => optional($enrollment->enrolled_at)->toIso8601String(),
                'assigned_at' => optional($enrollment->assigned_at)->toIso8601String(),
                'assigned_by' => $enrollment->assignedByAdmin?->only(['id', 'name']),
                'exam' => $enrollment->exam ? [
                    'id' => $enrollment->exam->id,
                    'name' => $enrollment->exam->name,
                    'exam_code' => $enrollment->exam->exam_code,
                    'status' => $enrollment->exam->status,
                    'starts_at' => optional($enrollment->exam->starts_at)->toIso8601String(),
                    'ends_at' => optional($enrollment->exam->ends_at)->toIso8601String(),
                    'duration_minutes' => $enrollment->exam->duration_minutes,
                    'fee_amount' => (float) $enrollment->exam->fee_amount,
                    'fee_currency' => $enrollment->exam->fee_currency,
                    'subject' => $enrollment->exam->subject?->only(['id', 'name', 'icon', 'color']),
                    'class_level' => $enrollment->exam->classLevel?->only(['id', 'label']),
                ] : null,
            ])
            ->values()
            ->all();
    }

    private function assignableExamPayload(): array
    {
        return Exam::query()
            ->where('status', 'published')
            ->with(['subject:id,name,icon,color', 'classLevel:id,label'])
            ->orderBy('starts_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'name' => $exam->name,
                'exam_code' => $exam->exam_code,
                'starts_at' => optional($exam->starts_at)->toIso8601String(),
                'ends_at' => optional($exam->ends_at)->toIso8601String(),
                'duration_minutes' => $exam->duration_minutes,
                'fee_amount' => (float) $exam->fee_amount,
                'fee_currency' => $exam->fee_currency,
                'subject' => $exam->subject?->only(['id', 'name', 'icon', 'color']),
                'class_level' => $exam->classLevel?->only(['id', 'label']),
            ])
            ->values()
            ->all();
    }

    public function index(Request $request): Response
    {
        $query = User::where('role', 'student')->with('classLevel')->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('school', 'like', "%{$s}%")
                    ->orWhere('city', 'like', "%{$s}%");
            });
        }

        if ($request->filled('class_level_id')) {
            $query->where('class_level_id', $request->class_level_id);
        }

        if ($request->filled('state')) {
            $query->where('state', $request->state);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Campaign attribution — 'website' also covers pre-attribution accounts.
        if ($request->filled('source')) {
            $source = $request->source;
            $query->where(fn ($q) => $source === 'website'
                ? $q->where('registration_source', 'website')->orWhereNull('registration_source')
                : $q->where('registration_source', $source));
        }

        return Inertia::render('Admin/Users/Index', [
            'students' => $query->paginate(20)->withQueryString(),
            'classLevels' => ClassLevel::active(),
            'states' => User::indianStates(),
            'sources' => User::REGISTRATION_SOURCES,
            'filters' => $request->only(['search', 'class_level_id', 'state', 'status', 'source']),
            'totals' => [
                'all' => User::where('role', 'student')->count(),
                'active' => User::where('role', 'student')->where('is_active', true)->count(),
                'inactive' => User::where('role', 'student')->where('is_active', false)->count(),
                'today' => User::where('role', 'student')->whereDate('created_at', today())->count(),
                'marketing' => User::where('role', 'student')->where('registration_source', 'marketing')->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', $this->meta());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'class_level_id' => 'nullable|exists:class_levels,id',
            'phone' => ['nullable', 'string', 'max:25', new ValidPhoneNumber],
            'dob' => 'nullable|date|before:today',
            'school' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $plainPassword = $data['password'];
        $data['role'] = 'student';
        $data['registration_source'] = 'admin';
        $data['password'] = Hash::make($plainPassword);

        $user = User::create($data);

        app(ManagedEmailService::class)->queue(
            'student_registered',
            $user,
            app(ManagedEmailService::class)->studentRegistrationVariables($user, $plainPassword),
            ['related_type' => User::class, 'related_id' => $user->id, 'created_by_admin' => auth()->id()]
        );

        return redirect()->route('admin.users.index')->with('success', 'Student account created successfully.');
    }

    public function show(User $user): Response
    {
        $this->assertStudent($user);

        $user->load('classLevel');

        return Inertia::render('Admin/Users/Show', [
            'student' => $user,
            'enrollments' => $this->enrollmentPayload($user),
        ]);
    }

    public function edit(User $user): Response
    {
        $this->assertStudent($user);

        $user->load('classLevel');

        return Inertia::render('Admin/Users/Edit', array_merge(
            [
                'student' => $user,
                'enrollments' => $this->enrollmentPayload($user),
                'assignableExams' => $this->assignableExamPayload(),
            ],
            $this->meta()
        ));
    }

    public function update(Request $request, User $user)
    {
        $this->assertStudent($user);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'class_level_id' => 'nullable|exists:class_levels,id',
            'phone' => ['nullable', 'string', 'max:25', new ValidPhoneNumber],
            'dob' => 'nullable|date|before:today',
            'school' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.show', $user)->with('success', 'Student profile updated successfully.');
    }

    public function toggle(Request $request, User $user)
    {
        $this->assertStudent($user);

        $request->validate(['is_active' => 'required|boolean']);

        $user->update(['is_active' => $request->is_active]);

        $action = $request->is_active ? 'enabled' : 'disabled';

        return back()->with('success', "Student account {$action} successfully.");
    }

    public function destroy(User $user)
    {
        $this->assertStudent($user);

        if ($user->role === 'admin') {
            return back()->with('error', 'Cannot delete admin accounts.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Student deleted successfully.');
    }

    public function assignExam(Request $request, User $user)
    {
        $this->assertStudent($user);

        $data = $request->validate([
            'exam_id' => [
                'required',
                'integer',
                Rule::exists('exams', 'id')->where(fn ($query) => $query->where('status', 'published')),
            ],
            'manual_reference' => ['nullable', 'string', 'max:100'],
            'manual_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $exam = Exam::findOrFail($data['exam_id']);
        $existing = ExamEnrollment::where('user_id', $user->id)->where('exam_id', $exam->id)->first();

        if ($existing?->status === 'enrolled') {
            return back()->with('info', "{$user->name} is already assigned to {$exam->name}.");
        }

        $payment = app(PaymentService::class)->recordManualExamPayment(
            $user,
            $exam,
            $request->user(),
            $data['manual_reference'] ?? null,
            $data['manual_note'] ?? null,
        );

        $enrollment = ExamEnrollment::where('user_id', $user->id)->where('exam_id', $exam->id)->first();
        $enrollment?->update([
            'assigned_by_admin_id' => $request->user()->id,
            'assigned_at' => now(),
        ]);

        return back()->with('success', "{$exam->name} assigned to {$user->name}. Manual payment NEO-".str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT).' recorded.');
    }

    public function cancelEnrollment(Request $request, User $user, ExamEnrollment $enrollment)
    {
        $this->assertStudent($user);
        abort_if($enrollment->user_id !== $user->id, 404);

        if ($user->examAttempts()->where('exam_id', $enrollment->exam_id)->exists()) {
            return back()->with('error', 'This enrollment cannot be cancelled because the student has already attempted the exam.');
        }

        if ($enrollment->status !== 'enrolled') {
            return back()->with('info', 'This enrollment is not currently active.');
        }

        $enrollment->update(['status' => 'cancelled']);

        return back()->with('success', 'Olympiad assignment cancelled.');
    }
}
