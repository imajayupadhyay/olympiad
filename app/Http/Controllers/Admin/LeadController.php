<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search']);

        $leads = Lead::query()
            ->when($filters['search'] ?? null, function ($q, $v) {
                $q->where(function ($q) use ($v) {
                    $q->where('name', 'like', "%{$v}%")
                        ->orWhere('email', 'like', "%{$v}%")
                        ->orWhere('phone', 'like', "%{$v}%")
                        ->orWhere('message', 'like', "%{$v}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Lead $lead) => [
                'id'         => $lead->id,
                'name'       => $lead->name,
                'email'      => $lead->email,
                'phone'      => $lead->phone,
                'message'    => $lead->message,
                'created_at' => $lead->created_at,
            ]);

        return Inertia::render('Admin/Forms/Index', [
            'leads'   => $leads,
            'filters' => $filters,
            'stats'   => [
                'total' => Lead::count(),
                'month' => Lead::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
                'today' => Lead::whereDate('created_at', now()->toDateString())->count(),
            ],
        ]);
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return back()->with('success', 'Submission deleted.');
    }
}
