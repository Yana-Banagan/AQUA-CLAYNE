<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditAccount;
use App\Models\Customer;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $query = CreditAccount::with(['customer.user', 'customer.addresses'])
            ->where('balance', '>', 0)
            ->latest();

        if ($search) {
            $query->whereHas('customer.user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $accounts = $query->paginate(15)->withQueryString();

        $stats = [
            'totalOutstanding' => CreditAccount::sum('balance'),
            'customersWithDebt' => CreditAccount::where('balance', '>', 0)->count(),
        ];

        return view('admin.credit.index', compact('accounts', 'stats', 'search'));
    }

    public function show(Customer $customer)
    {
        $account = CreditAccount::where('customer_id', $customer->id)
            ->with(['transactions' => function ($q) {
                $q->latest();
            }])
            ->firstOrFail();

        $runningBalance = (float) $account->balance;
        $transactions = $account->transactions->map(function ($transaction) use (&$runningBalance) {
            $transaction->balance_after = $runningBalance;
            $runningBalance += $transaction->type === 'charge'
                ? -(float) $transaction->amount
                : (float) $transaction->amount;
            return $transaction;
        });

        return view('admin.credit.show', compact('customer', 'account', 'transactions'));
    }
}
