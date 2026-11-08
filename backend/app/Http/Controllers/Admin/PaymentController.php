<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WalkInOrder;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');
        $method = $request->get('method');

        // The payments table intentionally has no Laravel created_at / updated_at
        // timestamps. Sort completed payments by their actual payment time and
        // leave unpaid records after them.
        $query = Payment::with(['order.customer.user'])
            ->orderByRaw('paid_at DESC NULLS LAST')
            ->orderBy('id');

        if ($status) {
            $query->where('status', $status);
        }

        if ($method) {
            $query->where('method', $method);
        }

        $payments = $query->paginate(5)->withQueryString();

        $stats = [
            // Walk-in orders do not have Payment rows. Keep their cash/online
            // amounts in paid totals and track debt separately.
            'totalPaid' => Payment::where('status', 'paid')->sum('amount')
                + WalkInOrder::whereIn('payment_method', ['cash', 'online', 'gcash', 'paymongo'])->sum('amount'),
            'totalPending' => Payment::where('status', 'pending')->sum('amount'),
            'cashCount' => Payment::where('method', 'cash')->count()
                + WalkInOrder::where('payment_method', 'cash')->count(),
            'debtCount' => Payment::whereIn('method', ['credit', 'debt'])->count()
                + WalkInOrder::where('payment_method', 'debt')->count(),
        ];

        return view('admin.payments.index', compact('payments', 'stats', 'status', 'method'));
    }

    public function verify(Request $request, Payment $payment, PaymentService $paymentService)
    {
        $paymentService->collectCash($payment, $request->user());

        return back()->with('success', "Payment #{$payment->id} verified and marked as collected.");
    }
}
