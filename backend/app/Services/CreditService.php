<?php

namespace App\Services;

use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

class CreditService
{
    public function charge(Customer $customer, Order $order, float $amount): void
    {
        $account = CreditAccount::query()->where('customer_id', $customer->id)->lockForUpdate()->first();
        if (!$account || ((float) $account->balance + $amount) > (float) $account->credit_limit) {
            throw ValidationException::withMessages(['payment_method' => ['Available customer credit is insufficient.']]);
        }
        $account->increment('balance', $amount);
        CreditTransaction::create(['credit_account_id' => $account->id, 'order_id' => $order->id, 'type' => 'charge', 'amount' => $amount]);
    }
}
