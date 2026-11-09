<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Services\CreditService;
use Illuminate\Http\Request;

class AdminCreditController extends Controller
{
    public function show(Customer $customer)
    {
        $customer->load([
            'user',
            'creditAccount.transactions',
        ]);

        return view(
            'admin.customers.credit',
            compact('customer')
        );
    }

    public function pay(
        Request $request,
        CreditAccount $creditAccount,
        CreditService $creditService
    ) {
        $data = $request->validate([
            'amount' =>
                'required|numeric|min:0.01',
        ]);

        $creditService->pay(
            $creditAccount,
            (float) $data['amount'],
            $request->user()
        );

        return back()->with(
            'success',
            'Credit payment recorded.'
        );
    }
}
