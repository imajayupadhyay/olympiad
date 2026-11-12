<?php

namespace App\Services;

use App\Exceptions\HallTicketException;
use App\Models\ExamEnrollment;
use App\Models\HallTicket;
use App\Models\StudentNotification;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owns every hall-ticket rule: eligibility, the profile lock, document storage,
 * submission, admin review and the printable ticket payload (§9.7, §12.14).
 */
class HallTicketService
{
    /** Minimum profile completion before a student may submit. */
    public const MIN_PERCENT = 90;

    /** Mandatory on top of the percentage (owner decision 2026-11-11). */
    public const REQUIRED_FIELDS = ['live_photo', 'passport_photo', 'school_id_card', 'coordinator_name', 'coordinator_phone'];

    /** Read-only while a ticket is pending or approved. Email, phone and password stay editable. */
    public const LOCKED_FIELDS = [
        'name', 'dob', 'class_level_id', 'school', 'school_address', 'city', 'state', 'pincode',
        'coordinator_name', 'coordinator_phone',
    ];

    public const DOCUMENTS = [
        'live_photo' => ['label' => 'Live photo', 'pdf' => false, 'max_kb' => 5120, 'max_edge' => 1000],
        'passport_photo' => ['label' => 'Passport-size photo', 'pdf' => false, 'max_kb' => 5120, 'max_edge' => 1000],
        'school_id_card' => ['label' => 'School ID card', 'pdf' => true, 'max_kb' => 5120, 'max_edge' => 1800],
    ];

    /** Private disk: documents are never reachable by a public URL. */
    public const DISK = 'local';

    public const DIRECTORY = 'student-documents';

    public function __construct(private ExamSessionService $sessions) {}

    /* ───────────────────────── eligibility & lock ───────────────────────── */

    /**
     * @return array{percent:int, min_percent:int, eligible:bool, missing:list<string>, required_missing:list<string>, fields:array<string,bool>, filled:int, total:int}
     */
    public function eligibility(User $user): array
    {
        $completion = $user->profileCompletion();

        // Fixed required items plus any admin-defined field marked required (custom:{key}).
        $required = [...self::REQUIRED_FIELDS, ...array_filter(array_keys($completion['fields']), fn ($k) => str_starts_with($k, 'custom:'))];

        $requiredMissing = collect($required)
            ->reject(fn (string $column) => $completion['fields'][$column] ?? false)
            ->map(fn (string $column) => $completion['labels'][$column] ?? $column)
            ->values()
            ->all();

        return [
            ...$completion,
            'min_percent' => self::MIN_PERCENT,
            'required_missing' => $requiredMissing,
            'eligible' => $completion['percent'] >= self::MIN_PERCENT && $requiredMissing === [],
        ];
    }

    public function isLocked(User $user): bool
    {
        // Fresh query: a relation cached earlier in the request may predate a submission.
        return (bool) HallTicket::where('user_id', $user->id)->first()?->locksProfile();
    }

    /** Compact status used by the dashboard card, profile page and hall-ticket page. */
    public function summary(User $user): array
    {
        $ticket = $user->hallTicket;

        return [
            'status' => $ticket?->status ?? 'none',
            'label' => $ticket?->statusLabel() ?? 'Not submitted',
            'roll_number' => $ticket?->isApproved() ? $ticket->roll_number : null,
            'rejection_reason' => $ticket?->isRejected() ? $ticket->rejection_reason : null,
            'submitted_at' => LocalTime::format($ticket?->submitted_at, 'j M Y, g:i A'),
            'locked' => (bool) $ticket?->locksProfile(),
            'eligibility' => $this->eligibility($user),
        ];
    }

    /* ───────────────────────── student actions ───────────────────────── */

    /** Create or resubmit the student's request. Idempotent while pending or approved. */
    public function submit(User $user): HallTicket
    {
        return DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $ticket = HallTicket::where('user_id', $user->id)->lockForUpdate()->first();

            if ($ticket?->locksProfile()) {
                return $ticket;
            }

            $eligibility = $this->eligibility($user);
            if (! $eligibility['eligible']) {
                throw new HallTicketException($eligibility['required_missing']
                    ? 'Add your '.implode(', ', $eligibility['required_missing']).' before submitting.'
                    : 'Complete at least '.self::MIN_PERCENT.'% of your profile before submitting.');
            }

            if ($ticket) {
                $ticket->update([
                    'status' => 'pending',
                    'submitted_at' => now(),
                    'submission_count' => $ticket->submission_count + 1,
                    'rejection_reason' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);

                return $ticket;
            }

            return HallTicket::create(['user_id' => $user->id, 'status' => 'pending', 'submitted_at' => now()]);
        });
    }

    public function storeDocument(User $user, string $type, UploadedFile $file): void
    {
        $this->guardDocumentType($type);
        $this->guardUnlocked($user);

        $config = self::DOCUMENTS[$type];
        $isPdf = $config['pdf'] && strtolower((string) $file->getMimeType()) === 'application/pdf';

        $contents = $isPdf ? (string) file_get_contents($file->getRealPath()) : $this->reencodeImage($file, $config['max_edge'], $type);
        $path = self::DIRECTORY.'/'.$user->id.'/'.$type.'-'.Str::random(32).($isPdf ? '.pdf' : '.jpg');

        Storage::disk(self::DISK)->put($path, $contents);

        $old = $user->getAttribute($type);
        $user->forceFill([$type => $path])->save();

        if ($old && $old !== $path) {
            Storage::disk(self::DISK)->delete($old);
        }
    }

    public function deleteDocument(User $user, string $type): void
    {
        $this->guardDocumentType($type);
        $this->guardUnlocked($user);

        if ($old = $user->getAttribute($type)) {
            Storage::disk(self::DISK)->delete($old);
        }

        $user->forceFill([$type => null])->save();
    }

    /** Inline, no-store response for one document. Callers authorise first. */
    public function documentResponse(User $user, string $type): StreamedResponse
    {
        $this->guardDocumentType($type);
        $path = $user->getAttribute($type);

        abort_unless($path && Storage::disk(self::DISK)->exists($path), 404);

        $isPdf = str_ends_with($path, '.pdf');

        return Storage::disk(self::DISK)->response($path, Str::slug(self::DOCUMENTS[$type]['label']).($isPdf ? '.pdf' : '.jpg'), [
            'Content-Type' => $isPdf ? 'application/pdf' : 'image/jpeg',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /**
     * Upload rules per document. Images are re-encoded on the server, so EXIF data
     * (including GPS) and anything hidden in the file never reach the disk.
     */
    public function documentRules(string $type): array
    {
        $this->guardDocumentType($type);
        $config = self::DOCUMENTS[$type];
        $mimes = $config['pdf'] ? 'mimes:jpg,jpeg,png,webp,pdf' : 'mimes:jpg,jpeg,png,webp';

        $rules = ['required', 'file', $mimes, 'max:'.$config['max_kb']];

        if ($type === 'passport_photo') {
            $rules[] = 'dimensions:min_width=200,min_height=200';
        }

        return $rules;
    }

    /* ───────────────────────── admin actions ───────────────────────── */

    public function approve(HallTicket $ticket, User $admin): HallTicket
    {
        $ticket = DB::transaction(function () use ($ticket, $admin) {
            $ticket = HallTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (! $ticket->isPending()) {
                throw new HallTicketException('Only a hall ticket awaiting verification can be approved.');
            }

            $ticket->update([
                'status' => 'approved',
                'roll_number' => $ticket->roll_number ?: $this->rollNumber($ticket),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            return $ticket;
        });

        $this->notify($ticket->user_id, 'Your hall ticket is ready', 'Your details were verified. You can now view and print your hall ticket (Roll No. '.$ticket->roll_number.').');

        return $ticket;
    }

    /** Reject a pending ticket, or revoke an approved one. Unlocks the profile for corrections. */
    public function reject(HallTicket $ticket, User $admin, string $reason): HallTicket
    {
        $ticket = DB::transaction(function () use ($ticket, $admin, $reason) {
            $ticket = HallTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if ($ticket->isRejected()) {
                throw new HallTicketException('This hall ticket is already rejected.');
            }

            $ticket->update([
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'approved_at' => null,
                'rejection_reason' => $reason,
            ]);

            return $ticket;
        });

        $this->notify($ticket->user_id, 'Hall ticket needs changes', 'Your hall ticket request was not approved: '.$reason.' Update your profile and submit again.');

        return $ticket;
    }

    /** Admin list: filters status / class / search, newest submission first. */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return HallTicket::query()
            ->with(['user:id,name,email,phone,school,city,state,class_level_id,coordinator_name', 'user.classLevel:id,label', 'reviewer:id,name'])
            ->when(($filters['status'] ?? '') !== '' && isset(HallTicket::STATUSES[$filters['status']]), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['class_level_id'] ?? null), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('class_level_id', $filters['class_level_id'])))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('roll_number', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where(fn ($uu) => $uu
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('school', 'like', "%{$search}%")))))
            // Pending first (the work queue), then most recent activity.
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END")
            ->orderByDesc('submitted_at')
            ->paginate(20)
            ->withQueryString();
    }

    public function stats(): array
    {
        $counts = HallTicket::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'pending' => (int) ($counts['pending'] ?? 0),
            'approved' => (int) ($counts['approved'] ?? 0),
            'rejected' => (int) ($counts['rejected'] ?? 0),
            'total' => (int) $counts->sum(),
        ];
    }

    /* ───────────────────────── printable ticket ───────────────────────── */

    /**
     * The rendered ticket for any viewer: the raw context below, shaped by the admin's
     * saved layout (HallTicketLayoutService). `$documentUrl` maps a document type to a
     * URL the viewer may open.
     */
    public function ticket(HallTicket $ticket, callable $documentUrl): array
    {
        return app(HallTicketLayoutService::class)->render($this->context($ticket, $documentUrl));
    }

    /**
     * Raw, unformatted facts about one student's ticket. Every value a ticket field or
     * schedule column can show lives here (see HallTicketLayoutService::FIELDS / COLUMNS).
     */
    public function context(HallTicket $ticket, callable $documentUrl): array
    {
        $user = $ticket->user()->with('classLevel')->firstOrFail();
        $exams = $this->examRows($user);

        $values = [
            'name' => $user->name,
            'roll_number' => $ticket->isApproved() ? $ticket->roll_number : null,
            'registration_no' => 'NEO-ST-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'dob' => $user->dob?->format('j F Y'),
            'class' => $user->classLevel?->label,
            'phone' => $user->phone,
            'email' => $user->email,
            'school' => $user->school,
            'school_address' => collect([$user->school_address, $user->city, $user->state, $user->pincode])->filter()->implode(', '),
            'city' => $user->city,
            'state' => $user->state,
            'pincode' => $user->pincode,
            'coordinator_name' => $user->coordinator_name,
            'coordinator_phone' => $user->coordinator_phone,
            'olympiads' => collect($exams)->pluck('exam')->implode(', '),
        ];

        $customFields = app(StudentCustomFieldService::class);
        $stored = $customFields->valuesFor($user);
        foreach ($customFields->active() as $field) {
            $values[$field->ticketKey()] = $customFields->display($field, $stored[$field->key] ?? null);
        }

        return [
            'status' => $ticket->status,
            'roll_number' => $ticket->isApproved() ? $ticket->roll_number : null,
            'session_at' => $ticket->approved_at ?? $ticket->submitted_at ?? now(),
            'approved_on' => $ticket->isApproved() ? LocalTime::format($ticket->approved_at, 'j F Y') : null,
            'values' => $values,
            'photo_url' => $user->passport_photo ? $documentUrl('passport_photo') : null,
            'exams' => $exams,
        ];
    }

    /* ───────────────────────── internals ───────────────────────── */

    private function examRows(User $user): array
    {
        return ExamEnrollment::query()
            ->where('user_id', $user->id)
            ->where('status', 'enrolled')
            ->whereHas('exam', fn ($q) => $q->where('status', 'published'))
            ->with('exam.subject:id,name')
            ->get()
            ->map(function (ExamEnrollment $enrollment) {
                $exam = $enrollment->exam;
                [$starts, $ends, $personal] = $this->sessions->window($exam, $enrollment);

                return [
                    'enrollment_id' => 'NEO-EN-'.str_pad((string) $enrollment->id, 6, '0', STR_PAD_LEFT),
                    'exam' => $exam->name,
                    'exam_code' => $exam->exam_code,
                    'subject' => $exam->subject?->name,
                    'starts' => $starts,
                    'ends' => $ends,
                    'duration_minutes' => $exam->duration_minutes,
                    'personal_window' => $personal,
                    'sort' => $starts?->timestamp ?? PHP_INT_MAX,
                ];
            })
            ->sortBy('sort')
            ->map(fn (array $row) => collect($row)->except('sort')->all())
            ->values()
            ->all();
    }

    /** NEO + yy + class (2 digits) + ticket id (5 digits), e.g. NEO260600012. Unique by construction. */
    private function rollNumber(HallTicket $ticket): string
    {
        $level = (int) ($ticket->user()->with('classLevel')->first()?->classLevel?->level ?? 0);

        return 'NEO'.now()->format('y').str_pad((string) $level, 2, '0', STR_PAD_LEFT).str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT);
    }

    /** Decode, fix camera rotation, shrink and re-save as JPEG. Rejects anything GD can't read. */
    private function reencodeImage(UploadedFile $file, int $maxEdge, string $type): string
    {
        $raw = (string) file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($raw);

        if (! $image) {
            throw ValidationException::withMessages(['file' => 'This image could not be read. Upload a JPG, PNG or WebP photo.']);
        }

        if (function_exists('exif_read_data') && in_array(strtolower((string) $file->getMimeType()), ['image/jpeg', 'image/jpg'], true)) {
            $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
            $rotated = match ($orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => null,
            };
            if ($rotated) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxEdge / max($width, $height));

        if ($scale < 1) {
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale), IMG_BICUBIC);
            imagedestroy($image);
            $image = $resized;
        }

        // Flatten transparency onto white so PNG cut-outs don't turn black as JPEG.
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);

        ob_start();
        imagejpeg($canvas, null, 86);
        imagedestroy($canvas);

        return (string) ob_get_clean();
    }

    private function guardDocumentType(string $type): void
    {
        abort_unless(isset(self::DOCUMENTS[$type]), 404);
    }

    private function guardUnlocked(User $user): void
    {
        if ($this->isLocked($user)) {
            throw new HallTicketException('Your details are locked while your hall ticket is under review or approved.');
        }
    }

    private function notify(int $userId, string $title, string $message): void
    {
        StudentNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'link' => route('student.hall-ticket'),
        ]);
    }
}
