<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CreditAccountController extends Controller
{
    public function show(Request $request)
    {
        $customer = $request->user()->customer;
        $account = $customer ? $customer->creditAccount()->with('transactions')->first() : null;

        return $this->success($account);
    }
}
