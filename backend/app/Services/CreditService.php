<?php

namespace App\Services;

use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditService
{
    public function charge(
        Customer $customer,
        Order $order,
        float $amount
    ): void {
        $account = CreditAccount::query()
            ->where('customer_id', $customer->id)
            ->lockForUpdate()
            ->first();

        if (
            !$account ||
            (
                (float) $account->balance + $amount
            ) > (float) $account->credit_limit
        ) {
            throw ValidationException::withMessages([
                'payment_method' => [
                    'Available customer credit is insufficient.'
                ],
            ]);
        }

        $account->increment(
            'balance',
            $amount
        );

        CreditTransaction::create([
            'credit_account_id' => $account->id,
            'order_id' => $order->id,
            'type' => 'charge',
            'amount' => $amount,
        ]);

        \Illuminate\Support\Facades\Cache::store('file')->forget('admin:dashboard:station-kpis');
        \Illuminate\Support\Facades\Cache::store('file')->forget('admin:credit:stats');
        \Illuminate\Support\Facades\Cache::store('file')->forget('admin:customers:stats');
        \Illuminate\Support\Facades\Cache::store('file')->forget('admin_dashboard_stats_v2');
    }

    public function pay(
        CreditAccount $account,
        float $amount,
        ?User $actor = null
    ): CreditTransaction {
        return DB::transaction(function () use ($account, $amount, $actor) {
            $account = CreditAccount::query()->lockForUpdate()->findOrFail($account->id);

            if ($amount > (float) $account->balance) {
                throw ValidationException::withMessages([
                    'amount' => ['Payment cannot exceed the outstanding credit balance.'],
                ]);
            }

            $account->decrement('balance', $amount);

            $transaction = CreditTransaction::create([
                'credit_account_id' => $account->id,
                'type' => 'payment',
                'amount' => $amount,
            ]);

            AuditLog::create([
                'actor_id' => $actor?->id,
                'action' => 'credit.payment_recorded',
                'table_name' => 'credit_accounts',
                'record_id' => $account->id,
            ]);

            \Illuminate\Support\Facades\Cache::store('file')->forget('admin:dashboard:station-kpis');
            \Illuminate\Support\Facades\Cache::store('file')->forget('admin:credit:stats');
            \Illuminate\Support\Facades\Cache::store('file')->forget('admin:customers:stats');
            \Illuminate\Support\Facades\Cache::store('file')->forget('admin_dashboard_stats_v2');

            return $transaction;
        });
    }
}
