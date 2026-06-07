<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Payments/Index');
    }

    public function refund($payment)
    {
        return back()->with('info', 'Refund processing coming soon.');
    }
}
