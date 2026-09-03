<?php

namespace App\Http\Middleware;

use App\Models\StudentNotification;
use App\Models\SupportTicket;
use App\Support\AdminPermissions;
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
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'meta_purchase' => $request->session()->get('meta_purchase'),
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
                'admin_permissions' => AdminPermissions::matrixForUser($user),
                'admin_modules' => AdminPermissions::moduleOptions(),
                'admin_role' => $user->adminRole?->only(['id', 'name', 'slug']),
                'admin_is_super' => $user->isSuperAdmin(),
                'admin_support_unread' => AdminPermissions::allows($user, 'support', 'read')
                    ? (int) SupportTicket::where('admin_unread', '>', 0)->count()
                    : 0,
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
