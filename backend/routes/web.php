<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RefillQueueController;
use App\Http\Controllers\Admin\RefillOperationController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\DeliveryAssignmentController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\CreditController;
use App\Http\Controllers\Admin\AdminCreditController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\AdminStatusController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\CustomerPortalController;


/*
|--------------------------------------------------------------------------
| PUBLIC LANDING & CONTACT
|--------------------------------------------------------------------------
*/

Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::post('/contact', [LandingController::class, 'submitContact'])->name('contact.submit');
Route::get('/download/app', function () {
    $apkPath = public_path('downloads/AquaClayne.apk');
    if (file_exists($apkPath)) {
        return response()->download($apkPath, 'AquaClayne-Release.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
    return redirect('/#download')->with('download_notice', 'Mobile app build is currently being updated.');
})->name('app.download');


/*
|--------------------------------------------------------------------------
| CUSTOMER WEB APPLICATION ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('customer')->group(function () {
    Route::get('/', [CustomerPortalController::class, 'index'])->name('customer.index');
    Route::get('/login', [CustomerPortalController::class, 'login'])->name('customer.login');
    Route::get('/register', [CustomerPortalController::class, 'register'])->name('customer.register');
    Route::get('/forgot-password', [CustomerPortalController::class, 'forgotPassword'])->name('customer.forgot-password');
    Route::get('/order', [CustomerPortalController::class, 'order'])->name('customer.order');
    Route::get('/catalog', [CustomerPortalController::class, 'catalog'])->name('customer.catalog');
    Route::get('/orders', [CustomerPortalController::class, 'orders'])->name('customer.orders');
    Route::get('/orders/{order}', [CustomerPortalController::class, 'orderDetail'])->name('customer.order-detail');
    Route::get('/tracking/{order?}', [CustomerPortalController::class, 'tracking'])->name('customer.tracking');
    Route::get('/gallons', [CustomerPortalController::class, 'gallons'])->name('customer.gallons');
    Route::get('/cart', [CustomerPortalController::class, 'cart'])->name('customer.cart');
    Route::get('/checkout', [CustomerPortalController::class, 'checkout'])->name('customer.checkout');
    Route::get('/payments', [CustomerPortalController::class, 'payments'])->name('customer.payments');
    Route::get('/addresses', [CustomerPortalController::class, 'addresses'])->name('customer.addresses');
    Route::get('/notifications', [CustomerPortalController::class, 'notifications'])->name('customer.notifications');
    Route::get('/support', [CustomerPortalController::class, 'support'])->name('customer.support');
    Route::get('/complaints', [CustomerPortalController::class, 'complaints'])->name('customer.complaints');
    Route::get('/faqs', [CustomerPortalController::class, 'faqs'])->name('customer.faqs');
    Route::get('/chat', [CustomerPortalController::class, 'chat'])->name('customer.chat');
    Route::get('/profile', [CustomerPortalController::class, 'profile'])->name('customer.profile');
    Route::get('/queue', [CustomerPortalController::class, 'queue'])->name('customer.queue');
    Route::match(['get', 'post'], '/logout', [CustomerPortalController::class, 'logout'])->name('customer.logout');
});

Route::get('/order', [CustomerPortalController::class, 'catalog'])->name('customer.order-shortcut');
Route::get('/app', [CustomerPortalController::class, 'index'])->name('customer.app-shortcut');
Route::get('/portal', [CustomerPortalController::class, 'index'])->name('customer.portal-shortcut');



/*
|--------------------------------------------------------------------------
| PAYMONGO CUSTOMER RETURN PAGES
|--------------------------------------------------------------------------
|
| These pages ONLY tell the customer that they have returned from PayMongo.
| They DO NOT mark the payment as paid.
|
| The PayMongo webhook is responsible for confirming the payment.
|
*/

Route::get('/payment/paymongo/success', function (\Illuminate\Http\Request $request) {
    $orderId = $request->query('order_id');
    $orderNumber = '';
    $totalAmount = '';
    $isPaid = false;

    if (!empty($orderId)) {
        $order = \App\Models\Order::find($orderId);
        if ($order) {
            $orderNumber = $order->order_number;
            $totalAmount = '₱' . number_format((float) $order->total_amount, 2);
            $payment = $order->payment;
            if ($payment && $payment->method === 'online' && !empty($payment->gateway_checkout_session_id)) {
                try {
                    $updatedPayment = app(\App\Http\Controllers\Api\PayMongoController::class)->syncPaymentWithGateway(
                        $payment,
                        $order,
                        app(\App\Services\PayMongoService::class)
                    );
                    $isPaid = ($updatedPayment->status === 'paid');
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('PayMongo return sync failed: ' . $e->getMessage());
                }
            } elseif ($payment && $payment->status === 'paid') {
                $isPaid = true;
            }
        }
    }

    $orderPill = $orderNumber
        ? "<div style=\"display:inline-block;background:#EAF7FF;color:#0878C9;font-weight:800;padding:6px 16px;border-radius:12px;margin-bottom:16px;font-size:15px;border:1px solid #BCE3FD;\">Order #{$orderNumber} • {$totalAmount}</div>"
        : '';
    $statusBadge = $isPaid
        ? "<div style=\"display:inline-block;background:#D1FAE5;color:#059669;font-weight:800;padding:4px 12px;border-radius:8px;font-size:12px;margin-bottom:14px;\">STATUS: PAID &amp; CONFIRMED</div>"
        : "<div style=\"display:inline-block;background:#FEF3C7;color:#D97706;font-weight:800;padding:4px 12px;border-radius:8px;font-size:12px;margin-bottom:14px;\">STATUS: PROCESSING</div>";

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - AQUA CLAYNE</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #F0F7FC; color: #12344D; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .card { background: #FFFFFF; border-radius: 24px; padding: 40px 24px; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 10px 30px rgba(8, 120, 201, 0.08); border: 1px solid #D5EAF8; }
        .icon-circle { width: 72px; height: 72px; background: #E6F4EA; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: #137333; font-size: 36px; font-weight: bold; border: 2px solid #A5D6A7; }
        h1 { font-size: 22px; font-weight: 800; margin-bottom: 8px; color: #12344D; }
        p { font-size: 14px; line-height: 1.5; color: #52718B; margin-bottom: 24px; }
        .btn { display: inline-block; width: 100%; background: #0878C9; color: #FFFFFF; text-decoration: none; padding: 14px 20px; border-radius: 14px; font-weight: 700; font-size: 15px; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-circle">✓</div>
        <h1>Payment Successful!</h1>
        {$orderPill}
        <br>
        {$statusBadge}
        <p>Your payment via PayMongo has been confirmed. Please switch back to the <strong>AQUA CLAYNE</strong> app to view your updated order details.</p>
        <button class="btn" onclick="window.close()">Return to App</button>
    </div>
</body>
</html>
HTML;
    return response($html, 200)->header('Content-Type', 'text/html');
})->name('payment.paymongo.success');


Route::get('/payment/paymongo/cancel', [
    \App\Http\Controllers\Api\PayMongoController::class,
    'cancelReturn',
])->name('payment.paymongo.cancel');


/*
|--------------------------------------------------------------------------
| UNIFIED AUTHENTICATION (Customer, Admin, Staff)
|--------------------------------------------------------------------------
*/

// Primary Unified Login Page (Handles guests, account switching, and role redirects)
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');

// Admin Alias for backward compatibility
Route::get('/admin/login', [LoginController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [LoginController::class, 'login'])->name('admin.login.submit');

Route::middleware('guest')->group(function () {
    // Social Authentication (Google & Facebook)
    Route::get('/auth/{provider}/redirect', [LoginController::class, 'redirectToProvider'])->name('auth.social.redirect');
    Route::get('/auth/{provider}/callback', [LoginController::class, 'handleProviderCallback'])->name('auth.social.callback');
    Route::get('/api/v1/auth/social/{provider}/callback', [LoginController::class, 'handleProviderCallback'])->name('auth.social.callback.api-alias');
});

// Staff Dashboard Route Alias
Route::middleware(['auth', 'role:admin,staff'])->group(function () {
    Route::get('/staff/dashboard', function () {
        return redirect()->route('admin.dashboard');
    })->name('staff.dashboard');
});


/*
|--------------------------------------------------------------------------
| AUTHENTICATED ADMIN & STAFF WORKSPACE
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin,staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', function () {
            return redirect()->route('admin.dashboard');
        });

        Route::get('/station', [\App\Http\Controllers\Api\RoutingController::class, 'station'])
            ->name('station.info');
        Route::get('/routing/route', [\App\Http\Controllers\Api\RoutingController::class, 'route'])
            ->middleware('throttle:public-api')
            ->name('routing.route');
        Route::get('/deliveries/{delivery}/route', [\App\Http\Controllers\Api\RoutingController::class, 'delivery'])
            ->middleware('throttle:public-api')
            ->name('deliveries.route');


        /*
        |--------------------------------------------------------------------------
        | AUTHENTICATION
        |--------------------------------------------------------------------------
        */

        Route::post('/logout', [
            LoginController::class,
            'logout'
        ])->name('logout');


        /*
        |--------------------------------------------------------------------------
        | MAIN: DASHBOARD
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard', [
            DashboardController::class,
            'index'
        ])->name('dashboard');


        Route::get('/status-counts', [
            AdminStatusController::class,
            'counts'
        ])->name('status-counts');


        /*
        |--------------------------------------------------------------------------
        | OPERATIONS: ORDERS & REFILL QUEUE
        |--------------------------------------------------------------------------
        */

        Route::get('/orders', [
            OrderController::class,
            'index'
        ])->name('orders.index');


        /*
        | Must be before /orders/{order}
        */
        Route::get('/orders/pending-count', [
            OrderController::class,
            'pendingCount'
        ])->name('orders.pending-count');

        Route::post('/orders/confirm-all', [OrderController::class, 'confirmAll'])->name('orders.confirm-all');


        Route::get('/orders/{order}', [
            OrderController::class,
            'show'
        ])->name('orders.show');


        Route::post('/orders/{order}/transition', [
            OrderController::class,
            'transition'
        ])->name('orders.transition');


        Route::post('/orders/{order}/assign', [
            DeliveryAssignmentController::class,
            'store'
        ])->name('orders.assign');


        Route::post('/orders/{order}/intake', [
            RefillOperationController::class,
            'receive'
        ])->name('orders.intake');


        Route::post('/orders/walk-in', [
            OrderController::class,
            'storeWalkIn'
        ])->name('orders.walk-in.store');


        /*
        |--------------------------------------------------------------------------
        | REFILL QUEUE
        |--------------------------------------------------------------------------
        */

        Route::get('/refill-queue/status', [
            RefillQueueController::class,
            'status'
        ])->name('refill-queue.status');


        Route::get('/refill-queue', [
            RefillQueueController::class,
            'index'
        ])->name('refill-queue.index');


        Route::post('/refill-queue/{order}/start', [
            RefillOperationController::class,
            'start'
        ])->name('refill-queue.start');


        Route::post('/refill-queue/{order}/complete', [
            RefillOperationController::class,
            'complete'
        ])->name('refill-queue.complete');


        Route::post('/refill-queue/{order}/priority', [
            RefillOperationController::class,
            'priority'
        ])->name('refill-queue.priority');


        Route::post('/refill-queue/{order}/resolve-intake', [
            RefillOperationController::class,
            'resolveIntake'
        ])->name('refill-queue.resolve-intake');


        Route::post('/refill-queue/{order}/remove', [
            RefillOperationController::class,
            'remove'
        ])->name('refill-queue.remove');


        /*
        |--------------------------------------------------------------------------
        | OPERATIONS: DELIVERIES
        |--------------------------------------------------------------------------
        */

        Route::get('/deliveries/status', [
            DeliveryController::class,
            'status'
        ])->name('deliveries.status');


        Route::get('/deliveries', [
            DeliveryController::class,
            'index'
        ])->name('deliveries.index');


        Route::get('/deliveries/assign', [
            DeliveryAssignmentController::class,
            'index'
        ])->name('deliveries.assign');


        Route::post('/deliveries/assign', [
            DeliveryAssignmentController::class,
            'store'
        ])->name('deliveries.assign.store');


        Route::get('/deliveries/{delivery}', [
            DeliveryController::class,
            'show'
        ])->name('deliveries.show');

        Route::patch('/deliveries/{delivery}/address', [
            DeliveryController::class,
            'updateAddress'
        ])->name('deliveries.address.update');

        Route::post('/deliveries/{delivery}/address', [
            DeliveryController::class,
            'updateAddress'
        ])->name('deliveries.address.update.post');


        Route::post('/deliveries/batches', [
            DeliveryController::class,
            'storeBatch'
        ])->name('deliveries.batches.store');


        Route::post('/deliveries/routes', [
            DeliveryController::class,
            'storeRoute'
        ])->name('deliveries.routes.store');


        Route::patch('/deliveries/batches/{batch}/status', [
            DeliveryController::class,
            'updateBatchStatus'
        ])->name('deliveries.batches.status');


        Route::patch('/deliveries/routes/{route}/status', [
            DeliveryController::class,
            'updateRouteStatus'
        ])->name('deliveries.routes.status');


        /*
        |--------------------------------------------------------------------------
        | OPERATIONS: PAYMENTS & DEBT
        |--------------------------------------------------------------------------
        */

        Route::get('/payments', [
            PaymentController::class,
            'index'
        ])->name('payments.index');


        Route::post('/payments/{payment}/verify', [
            PaymentController::class,
            'verify'
        ])->name('payments.verify');


        /*
        |--------------------------------------------------------------------------
        | DEBT MANAGEMENT
        |--------------------------------------------------------------------------
        */

        Route::get('/debt', [
            CreditController::class,
            'index'
        ])->name('debt.index');


        Route::get('/debt/{customer}', [
            CreditController::class,
            'show'
        ])->name('debt.show');


        /*
        |--------------------------------------------------------------------------
        | CUSTOMER DEBT ACCOUNT
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/customers/{customer}/debt',
            [
                AdminCreditController::class,
                'show'
            ]
        )->name('customers.debt');


        Route::post(
            '/debt-accounts/{creditAccount}/pay',
            [
                AdminCreditController::class,
                'pay'
            ]
        )->name('debt-accounts.pay');


        /*
        |--------------------------------------------------------------------------
        | MANAGEMENT: CUSTOMERS & DRIVERS
        |--------------------------------------------------------------------------
        */

        Route::get('/customers', [
            CustomerController::class,
            'index'
        ])->name('customers.index');


        Route::get('/customers/{customer}', [
            CustomerController::class,
            'show'
        ])->name('customers.show');


        Route::resource('drivers', DriverController::class)
            ->except([
                'show',
                'destroy',
                'create',
            ]);


        Route::post('/drivers/{driver}/toggle-status', [
            DriverController::class,
            'toggleStatus'
        ])->name('drivers.toggle-status');


        /*
        |--------------------------------------------------------------------------
        | MANAGEMENT: PRODUCTS, INVENTORY, REPORTS
        |--------------------------------------------------------------------------
        */

        Route::resource('products', ProductController::class)
            ->except([
                'show',
                'destroy'
            ]);


        Route::get('/inventory', [
            InventoryController::class,
            'index'
        ])->name('inventory.index');


        /*
        |--------------------------------------------------------------------------
        | RFID & GALLON TRACKING
        |--------------------------------------------------------------------------
        */
        Route::get('/rfid', [\App\Http\Controllers\Admin\RfidController::class, 'index'])->name('rfid.index');
        Route::post('/rfid/registration-sessions', [\App\Http\Controllers\Admin\RfidController::class, 'createSession'])->name('rfid.sessions.create');
        Route::get('/rfid/registration-sessions/{session}/status', [\App\Http\Controllers\Admin\RfidController::class, 'checkSessionStatus'])->name('rfid.sessions.status');
        Route::post('/rfid/registration-sessions/{session}/cancel', [\App\Http\Controllers\Admin\RfidController::class, 'cancelSession'])->name('rfid.sessions.cancel');
        Route::post('/rfid/containers', [\App\Http\Controllers\Admin\RfidController::class, 'storeContainer'])->name('rfid.containers.store');
        Route::post('/rfid/tags/{tag}/replace', [\App\Http\Controllers\Admin\RfidController::class, 'replaceTag'])->name('rfid.tags.replace');
        Route::post('/rfid/tags/{tag}/deactivate', [\App\Http\Controllers\Admin\RfidController::class, 'deactivateTag'])->name('rfid.tags.deactivate');
        Route::post('/rfid/devices', [\App\Http\Controllers\Admin\RfidController::class, 'storeDevice'])->name('rfid.devices.store');
        Route::post('/rfid/devices/{device}/rotate-token', [\App\Http\Controllers\Admin\RfidController::class, 'rotateDeviceToken'])->name('rfid.devices.rotate-token');
        Route::post('/rfid/devices/{device}/toggle-status', [\App\Http\Controllers\Admin\RfidController::class, 'toggleDeviceStatus'])->name('rfid.devices.toggle-status');
        Route::get('/rfid/search-customers', [\App\Http\Controllers\Admin\RfidController::class, 'searchCustomers'])->name('rfid.customers.search');
        Route::get('/rfid/customers/{customer}/containers', [\App\Http\Controllers\Admin\RfidController::class, 'customerContainers'])->name('rfid.customers.containers');



        Route::get('/reports', [
            ReportController::class,
            'index'
        ])->name('reports.index');


        /*
        |--------------------------------------------------------------------------
        | SUPPORT: COMPLAINTS, FAQ, NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

        Route::get('/complaints', [
            ComplaintController::class,
            'index'
        ])->name('complaints.index');


        Route::get('/complaints/{complaint}', [
            ComplaintController::class,
            'show'
        ])->name('complaints.show');


        Route::post('/complaints/{complaint}/respond', [
            ComplaintController::class,
            'respond'
        ])->name('complaints.respond');


        Route::resource('faq', FaqController::class)
            ->except([
                'create',
                'show',
                'edit'
            ]);


        /*
        |--------------------------------------------------------------------------
        | CHAT
        |--------------------------------------------------------------------------
        */

        Route::get('/chat', [
            ChatController::class,
            'index'
        ])->name('chat.index');


        Route::get('/chat/unread-count', [
            ChatController::class,
            'unreadCount'
        ])->name('chat.unread-count');


        Route::post('/chat/{conversation}/reply', [
            ChatController::class,
            'reply'
        ])->name('chat.reply');


        /*
        |--------------------------------------------------------------------------
        | NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

        Route::get('/notifications', [
            NotificationController::class,
            'index'
        ])->name('notifications.index');

        Route::get('/notifications/recent', [
            NotificationController::class,
            'recent'
        ])->name('notifications.recent');


        Route::post('/notifications/{notification}/read', [
            NotificationController::class,
            'markAsRead'
        ])->name('notifications.read');


        Route::post('/notifications/read-all', [
            NotificationController::class,
            'readAll'
        ])->name('notifications.read-all');


        /*
        |--------------------------------------------------------------------------
        | SYSTEM: MAINTENANCE
        |--------------------------------------------------------------------------
        */

        Route::get('/maintenance', [
            MaintenanceController::class,
            'index'
        ])->name('maintenance.index');


        Route::post('/maintenance/equipment', [
            MaintenanceController::class,
            'storeEquipment'
        ])->name('maintenance.equipment.store');


        Route::post('/maintenance', [
            MaintenanceController::class,
            'store'
        ])->name('maintenance.store');


        /*
        |--------------------------------------------------------------------------
        | SYSTEM: USERS
        |--------------------------------------------------------------------------
        */

        Route::get('/users', [
            UserController::class,
            'index'
        ])->name('users.index');


        Route::post('/users', [
            UserController::class,
            'store'
        ])->name('users.store');


        Route::post('/users/{user}/toggle-status', [
            UserController::class,
            'toggleStatus'
        ])->name('users.toggle-status');


        /*
        |--------------------------------------------------------------------------
        | SYSTEM: SETTINGS
        |--------------------------------------------------------------------------
        */

        Route::get('/settings', [
            SettingController::class,
            'index'
        ])->name('settings.index');


        Route::post('/settings', [
            SettingController::class,
            'update'
        ])->name('settings.update');

    });
