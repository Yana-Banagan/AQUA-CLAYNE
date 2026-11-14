<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerAddress;
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
            'addresses' => fn ($query) => $query
                ->select('id', 'customer_id', 'address_line', 'barangay', 'is_default')
                ->orderByDesc('is_default'),
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
        $customer->load(['user', 'addresses']);

        if (request()->expectsJson()) {
            $address = $customer->addresses
                ->sortByDesc('is_default')
                ->first();

            return response()->json([
                'id' => $customer->id,
                'name' => $customer->user?->name,
                'email' => $customer->user?->email,
                'phone' => $customer->user?->phone,
                'status' => $customer->user?->status,
                'birthdate' => $customer->birthdate?->format('Y-m-d'),
                'address' => $address ? [
                    'id' => $address->id,
                    'address_line' => $address->address_line,
                    'barangay' => $address->barangay,
                    'city' => $address->city,
                    'province' => $address->province,
                    'zip_code' => $address->zip_code,
                ] : null,
            ]);
        }

        return view('admin.customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $phoneRule = [
            'nullable',
            'string',
            'regex:/^09\d{9}$/',
            Rule::unique('users', 'phone')->ignore($customer->user_id),
        ];

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($customer->user_id),
            ],
            'phone' => $phoneRule,
            'status' => 'required|in:active,inactive',
            'birthdate' => 'nullable|date|before_or_equal:today',
            'address_id' => ['nullable', 'uuid', 'exists:customer_addresses,id'],
            'address_line' => ['nullable', 'string', 'max:500', 'required_with:barangay'],
            'barangay' => ['nullable', 'string', 'max:100', 'required_with:address_line'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($customer, $data, $request) {
            $customer->user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'status' => $data['status'],
            ]);
            $customer->update(['birthdate' => $data['birthdate'] ?? null]);

            $hasAddressInput = collect([
                'address_line',
                'barangay',
                'city',
                'province',
                'zip_code',
            ])->contains(fn ($field) => array_key_exists($field, $data));

            if ($hasAddressInput) {
                $addressData = [
                    'address_line' => $data['address_line'] ?? null,
                    'barangay' => $data['barangay'] ?? null,
                    'city' => $data['city'] ?? null,
                    'province' => $data['province'] ?? null,
                    'zip_code' => $data['zip_code'] ?? null,
                ];

                if (!empty($data['address_id'])) {
                    $customer->addresses()
                        ->whereKey($data['address_id'])
                        ->firstOrFail()
                        ->update($addressData);
                } elseif (!empty($data['address_line'])) {
                    $addressData['label'] = 'Home';
                    $addressData['is_default'] = !$customer->addresses()->exists();
                    $customer->addresses()->create($addressData);
                }
            }

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'customer.updated',
                'table_name' => 'customers',
                'record_id' => $customer->id,
            ]);
        });

        Cache::store('file')->forget('admin:customers:stats');

        if ($request->expectsJson()) {
            $customer->load(['user', 'addresses']);
            $address = $customer->addresses->sortByDesc('is_default')->first();

            return response()->json([
                'success' => true,
                'message' => 'Customer profile updated.',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->user?->name,
                    'email' => $customer->user?->email,
                    'phone' => $customer->user?->phone,
                    'status' => $customer->user?->status,
                    'address' => $address
                        ? collect([$address->address_line, $address->barangay])->filter()->implode(', ')
                        : null,
                ],
            ]);
        }

        return redirect()
            ->route('admin.customers.show', array_merge(
                ['customer' => $customer],
                $request->query()
            ))
            ->with('success', 'Customer profile updated.');
    }
}
