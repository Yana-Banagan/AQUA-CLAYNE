<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerPortalController extends Controller
{
    /**
     * Display the Customer Web Application Shell.
     */
    public function index(Request $request, ?string $view = null, ?string $id = null)
    {
        // Protected Customer Route: Require valid authenticated session
        if (!auth()->check()) {
            return redirect()->guest(route('admin.login'));
        }

        $user = auth()->user();
        $customer = $user->customer;

        // Auto-provision Customer record if missing for a customer-role user
        if (!$customer && $user->role === 'customer') {
            $customer = Customer::firstOrCreate(['user_id' => $user->id]);
        }

        $initialView = $view ?? $request->query('view', 'home');
        $initialId = $id ?? $request->query('id');

        // Preload active products for instantaneous storefront render (excluding alkaline)
        $products = Product::where('is_active', true)
            ->where('name', 'not like', '%alkaline%')
            ->where('name', 'not like', '%Alkaline%')
            ->with('inventory')
            ->orderBy('product_type')
            ->orderBy('price')
            ->get();

        // Calculate real customer dashboard stats
        $activeOrdersCount = 0;
        $inProgressCount = 0;
        $completedOrdersCount = 0;
        $outstandingBalance = 0.00;
        $customerGallons = [];

        if ($customer) {
            $activeOrdersCount = $customer->orders()
                ->whereIn('status', ['pending', 'confirmed', 'assigned_to_driver', 'out_for_delivery', 'waiting_in_queue', 'refilling'])
                ->count();

            $inProgressCount = $customer->orders()
                ->whereIn('status', ['confirmed', 'assigned_to_driver', 'waiting_in_queue', 'refilling', 'out_for_delivery'])
                ->count();

            $completedOrdersCount = $customer->orders()
                ->whereIn('status', ['delivered', 'completed'])
                ->count();

            if ($customer->creditAccount) {
                $outstandingBalance = (float) $customer->creditAccount->current_balance;
            }

            // Customer Registered Gallons & QR-Simulated RFID Tracking
            $customerOrders = $customer->orders()
                ->with(['items.product', 'containerIntake'])
                ->latest()
                ->get();

            foreach ($customerOrders as $ord) {
                $count = max((int)$ord->expected_empty_gallons, 1);
                for ($i = 1; $i <= $count; $i++) {
                    $tagUid = 'AC-GAL-' . str_pad($ord->order_number, 4, '0', STR_PAD_LEFT) . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                    $statusLabel = 'Verified & In Circulation';
                    $statusClass = 'badge-delivered';
                    if (in_array($ord->status, ['waiting_in_queue', 'refilling'])) {
                        $statusLabel = 'In Refill Station';
                        $statusClass = 'badge-refilling';
                    } elseif ($ord->status === 'out_for_delivery') {
                        $statusLabel = 'Out for Delivery';
                        $statusClass = 'badge-out_for_delivery';
                    } elseif (in_array($ord->status, ['pending', 'confirmed'])) {
                        $statusLabel = 'Awaiting Intake';
                        $statusClass = 'badge-pending';
                    }

                    $customerGallons[] = [
                        'tag_uid' => $tagUid,
                        'container_type' => '5-Gallon Standard Container',
                        'status' => $statusLabel,
                        'status_class' => $statusClass,
                        'order_number' => $ord->order_number,
                        'order_id' => $ord->id,
                        'order_status' => $ord->status,
                        'created_at' => $ord->created_at?->toIso8601String(),
                        'qr_data' => "AQUA_CLAYNE_GALLON:{$tagUid}:ORDER_{$ord->order_number}"
                    ];
                }
            }
        }

        // If no container history, show initial customer registration container
        if (empty($customerGallons)) {
            $customerGallons[] = [
                'tag_uid' => 'AC-GAL-' . substr($customer->id ?? $user->id, 0, 4) . '-01',
                'container_type' => '5-Gallon Standard Container',
                'status' => 'Verified Customer Container',
                'status_class' => 'badge-delivered',
                'order_number' => 'Initial',
                'order_id' => '',
                'order_status' => 'registered',
                'created_at' => now()->toIso8601String(),
                'qr_data' => 'AQUA_CLAYNE_GALLON:AC-GAL-INITIAL:' . $user->id
            ];
        }

        // Generate Sanctum Bearer token for client API communication
        $customerToken = $user->createToken('customer-web-session')->plainTextToken;

        $stationConfig = [
            'name' => config('services.station.name', 'Aqua Clayne Water Refilling Station'),
            'tagline' => 'Purified Drinking Water',
            'motto' => 'Clean Safe Convenient',
            'slogan' => 'Expect the Purest',
            'address' => config('services.station.address', 'Aqua Clayne Water Refilling Station, 007 Batisan St., Brgy. Gatid, Santa Cruz, Laguna'),
            'latitude' => (float) config('services.station.latitude', 14.265111),
            'longitude' => (float) config('services.station.longitude', 121.405602),
            'phone' => '+63 917 555 0199',
            'email' => 'support@aquaclayne.ph',
            'operating_hours' => 'Mon - Sat: 7:00 AM - 7:00 PM | Sun: 8:00 AM - 5:00 PM',
            'delivery_fee' => 5.00,
            'service_area' => [
                'name' => 'Gatid, Duhat, Bubukal, Santa Cruz, Laguna',
                'allowed_barangays' => ['Gatid', 'Duhat', 'Bubukal'],
                'min_lat' => 14.250,
                'max_lat' => 14.295,
                'min_lng' => 121.385,
                'max_lng' => 121.435,
            ],
        ];

        $realtimeConfig = [
            'url' => config('services.supabase.url'),
            'anon_key' => config('services.supabase.anon_key'),
        ];

        return view('customer.index', [
            'authenticatedUser' => $user,
            'customer' => $customer,
            'customerToken' => $customerToken,
            'initialView' => $initialView,
            'initialId' => $initialId,
            'preloadedProducts' => ProductResource::collection($products)->resolve(),
            'station' => $stationConfig,
            'realtime' => $realtimeConfig,
            'customerStats' => [
                'active_orders' => $activeOrdersCount,
                'in_progress' => $inProgressCount,
                'completed_orders' => $completedOrdersCount,
                'outstanding_balance' => $outstandingBalance,
                'registered_gallons' => count($customerGallons),
            ],
            'customerGallons' => $customerGallons,
        ]);
    }

    public function login(Request $request)
    {
        return redirect()->route('admin.login');
    }

    public function register(Request $request)
    {
        return redirect()->route('admin.login');
    }

    public function forgotPassword(Request $request)
    {
        return redirect()->route('admin.login');
    }

    public function orders(Request $request)
    {
        return $this->index($request, 'orders');
    }

    public function orderDetail(Request $request, string $order)
    {
        return $this->index($request, 'order_detail', $order);
    }

    public function tracking(Request $request, ?string $order = null)
    {
        return $this->index($request, 'tracking', $order);
    }

    public function gallons(Request $request)
    {
        return $this->index($request, 'gallons');
    }

    public function cart(Request $request)
    {
        return $this->index($request, 'cart');
    }

    public function order(Request $request)
    {
        return $this->index($request, 'catalog');
    }

    public function catalog(Request $request)
    {
        return $this->index($request, 'catalog');
    }

    public function checkout(Request $request)
    {
        return $this->index($request, 'checkout');
    }

    public function payments(Request $request)
    {
        return $this->index($request, 'payments');
    }

    public function support(Request $request)
    {
        return $this->index($request, 'complaints');
    }

    public function addresses(Request $request)
    {
        return $this->index($request, 'addresses');
    }

    public function notifications(Request $request)
    {
        return $this->index($request, 'notifications');
    }

    public function complaints(Request $request)
    {
        return $this->index($request, 'complaints');
    }

    public function faqs(Request $request)
    {
        return $this->index($request, 'faqs');
    }

    public function chat(Request $request)
    {
        return $this->index($request, 'chat');
    }

    public function profile(Request $request)
    {
        return $this->index($request, 'profile');
    }

    public function queue(Request $request)
    {
        return $this->index($request, 'queue');
    }

    /**
     * Log out customer session and redirect to unified login page.
     */
    public function logout(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            if (method_exists($user, 'tokens')) {
                try {
                    $user->tokens()->delete();
                } catch (\Throwable $e) {}
            }
            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'You have been logged out of Aqua Clayne.');
    }
}
