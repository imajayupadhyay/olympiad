<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Coupon;
use App\Models\ExamAttempt;
use App\Models\ReferralSetting;
use App\Models\Result;
use App\Services\ExamSessionService;
use App\Services\HallTicketService;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $enrollments = $user->enrollments()
            ->where('status', 'enrolled')
            ->with('exam.subject:id,name,icon,color')
            ->get();

        $attempts = ExamAttempt::where('user_id', $user->id)->get()->keyBy('exam_id');
        $results = Result::where('user_id', $user->id)->get()->keyBy('exam_id');

        $sessions = app(ExamSessionService::class);

        $myExams = $enrollments->map(function ($en) use ($attempts, $results, $sessions) {
            $exam = $en->exam;
            if (! $exam) {
                return null;
            }

            $attempt = $attempts->get($exam->id);
            $result = $results->get($exam->id);
            // One rule for every card: personal windows, paused and reopened sessions.
            $cta = $sessions->studentAction($exam, $attempt, $en, $result);

            return [
                'exam' => ['id' => $exam->id, 'name' => $exam->name],
                'subject' => $exam->subject?->only(['name', 'icon', 'color']),
                'availability' => $sessions->windowState($exam, $en),
                'starts_at' => $exam->starts_at,
                'ends_at' => $exam->ends_at,
                'duration_minutes' => $exam->duration_minutes,
                'action' => $cta['action'],
                'window_label' => $cta['window_label'],
                'attempt_id' => $attempt?->id,
                'result_id' => ($result && $result->is_released) ? $result->id : null,
            ];
        })->filter()->values();

        return Inertia::render('Student/Dashboard/Index', [
            'myExams' => $myExams,
            'stats' => [
                'enrolled' => $enrollments->count(),
                'completed' => $attempts->whereIn('status', ['submitted', 'timed_out', 'auto_submitted'])->count(),
                'results' => $results->where('is_released', true)->count(),
                'certificates' => Certificate::where('user_id', $user->id)->where('type', 'student')->count(),
            ],
            'referral' => $this->referralWidget($user),
            'onboarding' => [
                'must_change_password' => $user->usingGeneratedPassword(),
                'profile' => $user->profileCompletion(),
                'profile_url' => route('student.profile'),
            ],
            'hallTicket' => app(HallTicketService::class)->summary($user->loadMissing('hallTicket')),
        ]);
    }

    /** Compact Refer & Earn card data (null when the program is off). */
    private function referralWidget($user): ?array
    {
        $settings = ReferralSetting::current();

        if (! $settings->is_active) {
            return null;
        }

        return [
            'link' => $user->referralLink(),
            'progress' => app(ReferralService::class)->progressCount($user),
            'mode' => $settings->qualify_on,
            'rewards' => Coupon::where('owner_user_id', $user->id)
                ->where('source', 'referral_reward')
                ->where('is_active', true)
                ->where('used_count', 0)
                ->count(),
        ];
    }
}
