<?php

namespace App\Http\Middleware;

use App\Models\StudentNotification;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'info'    => $request->session()->get('info'),
            ],
            ...$this->badges($request),
        ];
    }

    /**
     * Role-scoped, lightweight aggregates that power the notification bell and
     * the Support unread badges across every page. Only computed for an
     * authenticated user, and never for guests.
     *
     * @return array<string, mixed>
     */
    protected function badges(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        if ($user->isAdmin()) {
            return [
                'admin_support_unread' => (int) SupportTicket::where('admin_unread', '>', 0)->count(),
            ];
        }

        return [
            'notifications_unread' => (int) StudentNotification::where('user_id', $user->id)->where('is_read', false)->count(),
            'recent_notifications' => StudentNotification::where('user_id', $user->id)
                ->latest()
                ->limit(8)
                ->get(['id', 'title', 'message', 'link', 'is_read', 'created_at']),
            'support_unread' => (int) SupportTicket::where('user_id', $user->id)->where('student_unread', '>', 0)->count(),
        ];
    }
}
