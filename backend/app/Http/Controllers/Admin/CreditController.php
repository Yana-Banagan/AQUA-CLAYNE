<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditAccount;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CreditController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $query = CreditAccount::with([
            'customer:id,user_id',
            'customer.user:id,name,phone,avatar_path',
            'customer.addresses:id,customer_id,address_line',
        ])
            ->where('balance', '>', 0)
            ->latest();

        if ($search) {
            $query->whereHas('customer.user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $accounts = $query->paginate(15)->withQueryString();

        $stats = Cache::store('file')->remember('admin:credit:stats', now()->addSeconds(20), function () {
            $row = CreditAccount::selectRaw("
                COALESCE(SUM(balance), 0) AS total_outstanding,
                SUM(CASE WHEN balance > 0 THEN 1 ELSE 0 END) AS customers_with_debt
            ")->first();

            return [
                'totalOutstanding' => (float) ($row?->total_outstanding ?? 0),
                'customersWithDebt' => (int) ($row?->customers_with_debt ?? 0),
            ];
        });

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
