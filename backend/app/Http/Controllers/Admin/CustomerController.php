<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $query = Customer::with([
            'user:id,name,email,phone,status,avatar_path',
            'addresses:id,customer_id,address_line',
            'creditAccount:id,customer_id,balance',
        ])
            ->withCount('orders')
            ->latest();

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate(5)->withQueryString();

        $stats = Cache::store('file')->remember('admin:customers:stats', now()->addSeconds(30), function () {
            $row = Customer::leftJoin('users', 'customers.user_id', '=', 'users.id')
                ->leftJoin('credit_accounts', 'customers.id', '=', 'credit_accounts.customer_id')
                ->selectRaw("
                    COUNT(DISTINCT customers.id) AS total,
                    COUNT(DISTINCT CASE WHEN users.status = 'active' THEN customers.id END) AS active,
                    COUNT(DISTINCT CASE WHEN credit_accounts.balance > 0 THEN customers.id END) AS with_credit
                ")
                ->first();

            return [
                'total' => (int) ($row?->total ?? 0),
                'active' => (int) ($row?->active ?? 0),
                'withCredit' => (int) ($row?->with_credit ?? 0),
            ];
        });

        return view('admin.customers.index', compact('customers', 'stats', 'search'));
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'user',
            'addresses',
            'creditAccount.transactions',
            'orders' => function ($q) {
                $q->latest()->take(10)->with(['items.product', 'delivery.driver.user']);
            },
            'complaints' => function ($q) {
                $q->latest();
            },
        ]);

        return view('admin.customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        $customer->load('user');

        return view('admin.customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($customer->user_id),
            ],
            'phone' => 'nullable|string|max:30',
            'status' => 'required|in:active,inactive',
            'birthdate' => 'nullable|date|before_or_equal:today',
        ]);

        DB::transaction(function () use ($customer, $data, $request) {
            $customer->user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'status' => $data['status'],
            ]);
            $customer->update(['birthdate' => $data['birthdate'] ?? null]);

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'customer.updated',
                'table_name' => 'customers',
                'record_id' => $customer->id,
            ]);
        });

        Cache::store('file')->forget('admin:customers:stats');

        return redirect()
            ->route('admin.customers.show', array_merge(
                ['customer' => $customer],
                $request->query()
            ))
            ->with('success', 'Customer profile updated.');
    }
}
