<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $query = Customer::with(['user:id,name,email,phone,status,avatar_path', 'addresses', 'creditAccount'])
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

        $stats = \Illuminate\Support\Facades\Cache::remember('admin:customers:stats', now()->addSeconds(15), function () {
            $withCredit = 0;
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('credit_accounts')) {
                    $withCredit = Customer::whereHas('creditAccount', fn($q) => $q->where('balance', '>', 0))->count();
                }
            } catch (\Throwable) {
                $withCredit = 0;
            }

            return [
                'total' => Customer::count(),
                'active' => Customer::whereHas('user', fn($q) => $q->where('status', 'active'))->count(),
                'withCredit' => $withCredit,
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
            }
        ]);

        return view('admin.customers.show', compact('customer'));
    }
}
