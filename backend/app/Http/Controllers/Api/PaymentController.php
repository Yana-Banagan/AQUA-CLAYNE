<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Get all payments for the authenticated customer.
     */
    public function index(Request $request)
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return $this->success([]);
        }

        $query = Payment::query()
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('orders.customer_id', $customer->id)
            ->select('payments.*')
            ->with(['order.items.product', 'order.delivery'])
            ->orderByDesc('orders.created_at');

        if ($request->filled('status')) {
            $status = strtolower($request->get('status'));
            $query->where('payments.status', $status);
        }

        if ($request->filled('method')) {
            $method = strtolower($request->get('method'));
            $query->where('payments.method', $method);
        }

        $payments = $query->get();

        return $this->success($payments);
    }

    /**
     * Get details of a single customer payment.
     */
    public function show(Request $request, Payment $payment)
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return $this->error('Customer account not found.', 404);
        }

        // Ensure this payment belongs to the customer's order
        abort_unless(
            $payment->order && $payment->order->customer_id === $customer->id,
            403,
            'You are not authorized to view this payment.'
        );

        $payment->load([
            'order.items.product',
            'order.delivery',
            'order.address',
            'order.containerIntake',
        ]);

        return $this->success($payment);
    }

    /**
     * Get the payment and balance summary for the authenticated customer.
     */
    public function summary(Request $request)
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return $this->success([
                'outstanding_balance' => 0.00,
                'credit_balance' => 0.00,
                'credit_limit' => 0.00,
                'available_credit' => 0.00,
                'has_credit_account' => false,
                'credit_status' => 'NONE',
                'pending_cash_total' => 0.00,
                'total_paid' => 0.00,
                'all_paid_up' => true,
            ]);
        }

        $creditAccount = $customer->creditAccount;
        $hasCredit = $creditAccount !== null;
        $creditLimit = $hasCredit ? (float) $creditAccount->credit_limit : 0.0;
        $creditBalance = $hasCredit ? (float) $creditAccount->balance : 0.0;
        $availableCredit = $hasCredit ? max(0.0, $creditLimit - $creditBalance) : 0.0;

        // Pending cash payments from customer orders
        $pendingCashTotal = (float) Payment::query()
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('orders.customer_id', $customer->id)
            ->where('payments.method', 'cash')
            ->where('payments.status', 'pending')
            ->sum('payments.amount');

        // Total paid amount
        $totalPaid = (float) Payment::query()
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('orders.customer_id', $customer->id)
            ->where('payments.status', 'paid')
            ->sum('payments.amount');

        // Total outstanding amount (used credit + unpaid cash on pending/delivered orders)
        $totalOutstanding = $creditBalance + $pendingCashTotal;

        return $this->success([
            'outstanding_balance' => round($totalOutstanding, 2),
            'credit_balance' => round($creditBalance, 2),
            'credit_limit' => round($creditLimit, 2),
            'available_credit' => round($availableCredit, 2),
            'has_credit_account' => $hasCredit,
            'credit_status' => $hasCredit ? 'ACTIVE' : 'NONE',
            'pending_cash_total' => round($pendingCashTotal, 2),
            'total_paid' => round($totalPaid, 2),
            'all_paid_up' => $totalOutstanding <= 0,
        ]);
    }
}
