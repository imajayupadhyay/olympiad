<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()->studentNotifications()
            ->latest()
            ->paginate(20)
            ->through(fn (StudentNotification $n) => [
                'id'         => $n->id,
                'title'      => $n->title,
                'message'    => $n->message,
                'link'       => $n->link,
                'is_read'    => $n->is_read,
                'created_at' => $n->created_at,
            ]);

        return Inertia::render('Student/Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, StudentNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true, 'read_at' => now()]);
        }

        return back();
    }

    public function markAllRead(Request $request)
    {
        $request->user()->studentNotifications()
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return back();
    }
}
