<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WalkInOrder;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');
        $method = $request->get('method');

        // The payments table intentionally has no Laravel created_at / updated_at
        // timestamps. Sort completed payments by their actual payment time and
        // leave unpaid records after them.
        $query = Payment::with([
            'order:id,order_number,customer_id',
            'order.customer:id,user_id',
            'order.customer.user:id,name,email,phone,avatar_path',
        ])
            ->orderByRaw('paid_at DESC NULLS LAST')
            ->orderBy('id');

        if ($status) {
            $query->where('status', $status);
        }

        if ($method) {
            $query->where('method', $method);
        }

        $payments = $query->paginate(5)->withQueryString();

        $stats = Cache::store('file')->remember('admin:payments:stats', now()->addSeconds(15), function () {
            $paymentRow = Payment::selectRaw("
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS total_pending,
                SUM(CASE WHEN method = 'cash' THEN 1 ELSE 0 END) AS cash_count,
                SUM(CASE WHEN method IN ('credit', 'debt') THEN 1 ELSE 0 END) AS debt_count
            ")->first();

            $walkInRow = WalkInOrder::selectRaw("
                COALESCE(SUM(CASE WHEN payment_method IN ('cash', 'online', 'gcash', 'paymongo') THEN amount ELSE 0 END), 0) AS total_paid,
                SUM(CASE WHEN payment_method = 'cash' THEN 1 ELSE 0 END) AS cash_count,
                SUM(CASE WHEN payment_method = 'debt' THEN 1 ELSE 0 END) AS debt_count
            ")->first();

            return [
                'totalPaid' => (float) (($paymentRow?->total_paid ?? 0) + ($walkInRow?->total_paid ?? 0)),
                'totalPending' => (float) ($paymentRow?->total_pending ?? 0),
                'cashCount' => (int) (($paymentRow?->cash_count ?? 0) + ($walkInRow?->cash_count ?? 0)),
                'debtCount' => (int) (($paymentRow?->debt_count ?? 0) + ($walkInRow?->debt_count ?? 0)),
            ];
        });

        return view('admin.payments.index', compact('payments', 'stats', 'status', 'method'));
    }

    public function verify(Request $request, Payment $payment, PaymentService $paymentService)
    {
        $paymentService->collectCash($payment, $request->user());
        Cache::store('file')->forget('admin:payments:stats');
        Cache::store('file')->forget('admin_dashboard_stats_v2');

        return back()->with('success', "Payment #{$payment->id} verified and marked as collected.");
    }
}
