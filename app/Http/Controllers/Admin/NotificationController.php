<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\NotificationLog;
use App\Models\StudentNotification;
use App\Models\User;
use App\Services\ManagedEmailService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(): Response
    {
        $logs = NotificationLog::with(['sentBy:id,name', 'exam:id,name', 'classLevel:id,label'])
            ->latest('sent_at')
            ->paginate(20);

        return Inertia::render('Admin/Notifications/Index', [
            'logs'        => $logs,
            'exams'       => Exam::whereIn('status', ['published', 'archived'])->get(['id', 'name']),
            'classLevels' => ClassLevel::active(),
            'stats'       => [
                'total_sent'  => NotificationLog::count(),
                'this_month'  => NotificationLog::whereMonth('sent_at', now()->month)->whereYear('sent_at', now()->year)->count(),
                'total_reach' => NotificationLog::sum('recipient_count'),
            ],
        ]);
    }

    public function send(Request $request, ManagedEmailService $emails)
    {
        $data = $request->validate([
            'title'          => 'required|string|max:150',
            'message'        => 'required|string|max:2000',
            'channel'        => 'required|in:email,in_app,both',
            'audience'       => 'required|in:all,exam,class',
            'exam_id'        => 'nullable|required_if:audience,exam|exists:exams,id',
            'class_level_id' => 'nullable|required_if:audience,class|exists:class_levels,id',
        ]);

        $query = User::where('role', 'student')->where('is_active', true);

        if ($data['audience'] === 'exam' && ! empty($data['exam_id'])) {
            $query->whereHas('examAttempts', fn($q) => $q->where('exam_id', $data['exam_id']));
        } elseif ($data['audience'] === 'class' && ! empty($data['class_level_id'])) {
            $query->where('class_level_id', $data['class_level_id']);
        }

        $recipients = $query->get(['id', 'name', 'email']);
        $count      = $recipients->count();

        if ($count === 0) {
            return back()->with('error', 'No active students found for the selected audience.');
        }

        $log = NotificationLog::create([
            'title'           => $data['title'],
            'message'         => $data['message'],
            'channel'         => $data['channel'],
            'audience'        => $data['audience'],
            'exam_id'         => $data['exam_id'] ?? null,
            'class_level_id'  => $data['class_level_id'] ?? null,
            'recipient_count' => $count,
            'status'          => 'sent',
            'sent_by'         => auth()->id(),
            'sent_at'         => now(),
        ]);

        if (in_array($data['channel'], ['in_app', 'both'])) {
            $inserts = $recipients->map(fn($u) => [
                'user_id'             => $u->id,
                'notification_log_id' => $log->id,
                'title'               => $data['title'],
                'message'             => $data['message'],
                'is_read'             => false,
                'created_at'          => now(),
                'updated_at'          => now(),
            ])->toArray();

            StudentNotification::insert($inserts);
        }

        if (in_array($data['channel'], ['email', 'both'])) {
            foreach ($recipients as $student) {
                $emails->queue(
                    'notification_blast',
                    $student,
                    $emails->notificationVariables($student, $data['title'], $data['message']),
                    ['related_type' => NotificationLog::class, 'related_id' => $log->id, 'notification_log_id' => $log->id]
                );
            }
        }

        return back()->with('success', "Notification sent to {$count} student(s) successfully.");
    }

    public function destroy(NotificationLog $log)
    {
        StudentNotification::where('notification_log_id', $log->id)->delete();
        $log->delete();

        return back()->with('success', 'Notification log deleted.');
    }
}
