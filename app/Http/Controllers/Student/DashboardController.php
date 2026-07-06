<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Coupon;
use App\Models\ExamAttempt;
use App\Models\ReferralSetting;
use App\Models\Result;
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

        $myExams = $enrollments->map(function ($en) use ($attempts, $results) {
            $exam = $en->exam;
            if (! $exam) {
                return null;
            }

            $attempt = $attempts->get($exam->id);
            $result = $results->get($exam->id);
            $avail = $exam->availabilityState();

            // Resolve the single call-to-action for this exam.
            if ($attempt && $attempt->status === 'in_progress') {
                $action = 'continue';
            } elseif ($attempt) {
                $action = ($result && $result->is_released) ? 'result' : 'awaiting';
            } else {
                $action = match ($avail) {
                    'live'     => 'start',
                    'upcoming' => 'upcoming',
                    default    => 'closed',
                };
            }

            return [
                'exam'             => ['id' => $exam->id, 'name' => $exam->name],
                'subject'          => $exam->subject?->only(['name', 'icon', 'color']),
                'availability'     => $avail,
                'starts_at'        => $exam->starts_at,
                'ends_at'          => $exam->ends_at,
                'duration_minutes' => $exam->duration_minutes,
                'action'           => $action,
                'attempt_id'       => $attempt?->id,
                'result_id'        => ($result && $result->is_released) ? $result->id : null,
            ];
        })->filter()->values();

        return Inertia::render('Student/Dashboard/Index', [
            'myExams' => $myExams,
            'stats'   => [
                'enrolled'     => $enrollments->count(),
                'completed'    => $attempts->whereIn('status', ['submitted', 'timed_out', 'auto_submitted'])->count(),
                'results'      => $results->where('is_released', true)->count(),
                'certificates' => Certificate::where('user_id', $user->id)->where('type', 'student')->count(),
            ],
            'referral' => $this->referralWidget($user),
            'onboarding' => [
                'must_change_password' => $user->usingGeneratedPassword(),
                'profile'              => $user->profileCompletion(),
                'profile_url'          => route('student.profile'),
            ],
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
            'link'     => $user->referralLink(),
            'progress' => app(ReferralService::class)->progressCount($user),
            'mode'     => $settings->qualify_on,
            'rewards'  => Coupon::where('owner_user_id', $user->id)
                ->where('source', 'referral_reward')
                ->where('is_active', true)
                ->where('used_count', 0)
                ->count(),
        ];
    }
}
