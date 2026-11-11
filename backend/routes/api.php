<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CustomerAddressController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Driver\DeliveryController;
use App\Http\Controllers\Api\CreditAccountController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\PayMongoController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\RoutingController;
use App\Http\Controllers\Api\Driver\RfidScanController;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/auth/register',
        [AuthController::class, 'register']
    )->middleware('throttle:auth-register');

    Route::post(
        '/auth/login',
        [AuthController::class, 'login']
    )->middleware('throttle:auth-login');
    Route::post('/auth/password/otp', [AuthController::class, 'sendPasswordOtp'])->middleware('throttle:password-otp');
    Route::post('/auth/password/verify-otp', [AuthController::class, 'verifyPasswordOtp'])->middleware('throttle:password-verify');
    Route::post('/auth/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');

    Route::post(
        '/auth/logout',
        [AuthController::class, 'logout']
    )->middleware('auth:sanctum');

    Route::get(
        '/auth/me',
        [AuthController::class, 'me']
    )->middleware('auth:sanctum');

    Route::patch(
        '/auth/profile',
        [AuthController::class, 'updateProfile']
    )->middleware('auth:sanctum');

    Route::post(
        '/auth/profile/photo',
        [AuthController::class, 'uploadProfilePhoto']
    )->middleware('auth:sanctum');

    Route::post(
        '/auth/change-password',
        [AuthController::class, 'changePassword']
    )->middleware('auth:sanctum');

    Route::post(
        '/auth/fcm-token',
        [AuthController::class, 'updateFcmToken']
    )->middleware('auth:sanctum');

    // Social OAuth routes (Google & Facebook)
    Route::get('/auth/social/{provider}/redirect', [SocialAuthController::class, 'redirect']);
    Route::get('/auth/social/{provider}/callback', [SocialAuthController::class, 'callback']);
    Route::post('/auth/social/{provider}/token', [SocialAuthController::class, 'verifyToken'])->middleware('throttle:auth-login');
    Route::post('/auth/social/link', [SocialAuthController::class, 'linkAccount'])->middleware('throttle:auth-login');
    Route::post('/auth/social/{provider}/link-current', [SocialAuthController::class, 'linkCurrentAccount'])->middleware('auth:sanctum');
    Route::delete('/auth/social/{provider}', [SocialAuthController::class, 'unlinkAccount'])->middleware('auth:sanctum');
    Route::get('/auth/social/accounts', [SocialAuthController::class, 'linkedAccounts'])->middleware('auth:sanctum');


    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    |
    | Authenticated customer, driver, admin, or staff
    |
    */

    // The product catalogue is browseable before authentication. Ordering it
    // remains inside the authenticated customer route group below.
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);


    /*
    |--------------------------------------------------------------------------
    | Customer Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'role:customer',
        'throttle:authenticated-write',
    ])->group(function () {

        /*
         * Firebase FCM token
         */
        Route::post(
            '/auth/fcm-token',
            [AuthController::class, 'updateFcmToken']
        );


        /*
         * Customer Addresses
         */

        Route::get(
            '/addresses',
            [CustomerAddressController::class, 'index']
        );

        Route::post(
            '/addresses',
            [CustomerAddressController::class, 'store']
        );

        Route::match(['put', 'patch'],
            '/addresses/{address}',
            [CustomerAddressController::class, 'update']
        );

        Route::delete(
            '/addresses/{address}',
            [CustomerAddressController::class, 'destroy']
        );

        Route::post(
            '/addresses/{address}/set-default',
            [CustomerAddressController::class, 'setDefault']
        );

        // The mobile customer app uses the customer-prefixed address API.
        // Keep these aliases alongside the shorter routes above for backwards
        // compatibility with both clients.
        Route::prefix('customer')->group(function () {
            Route::get('/addresses', [CustomerAddressController::class, 'index']);
            Route::post('/addresses', [CustomerAddressController::class, 'store']);
            Route::match(['put', 'patch'], '/addresses/{address}', [CustomerAddressController::class, 'update']);
            Route::delete('/addresses/{address}', [CustomerAddressController::class, 'destroy']);
            Route::post('/addresses/{address}/set-default', [CustomerAddressController::class, 'setDefault']);
        });


        /*
         * Customer Orders
         */

        Route::post(
            '/orders',
            [OrderController::class, 'store']
        );

        Route::get(
            '/orders',
            [OrderController::class, 'index']
        );

        Route::get(
            '/orders/{order}',
            [OrderController::class, 'show']
        );

        Route::post(
            '/orders/{order}/cancel',
            [OrderController::class, 'cancel']
        );
        Route::patch(
            '/orders/{order}/delivery-method',
            [OrderController::class, 'updateDeliveryMethod']
        );

        // Customer-prefixed order paths used by the mobile customer app.
        Route::get('/customer/orders', [OrderController::class, 'index']);
        Route::get('/customer/payment-options', [OrderController::class, 'paymentOptions']);
        Route::post('/customer/orders', [OrderController::class, 'store']);
        Route::get('/customer/orders/{order}', [OrderController::class, 'show']);
        Route::get('/customer/orders/{order}/tracking', [OrderController::class, 'tracking']);
        Route::post('/customer/orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::patch('/customer/orders/{order}/delivery-method', [OrderController::class, 'updateDeliveryMethod']);

    Route::post(
        '/customer/orders/{order}/payment/checkout',
        [PayMongoController::class, 'checkout']
    )->middleware('throttle:payment-checkout');
    Route::post(
        '/orders/{order}/payment/checkout',
        [PayMongoController::class, 'checkout']
    )->middleware('throttle:payment-checkout');

        Route::get(
            '/customer/orders/{order}/payment',
            [PayMongoController::class, 'paymentStatus']
        );
        Route::get(
            '/orders/{order}/payment',
            [PayMongoController::class, 'paymentStatus']
        );

        Route::post(
            '/customer/orders/{order}/payment/simulate-success',
            [PayMongoController::class, 'simulateSuccess']
        );
        Route::post(
            '/orders/{order}/payment/simulate-success',
            [PayMongoController::class, 'simulateSuccess']
        );

        /*
         * Customer Queue
         */
        Route::get(
            '/queue',
            [OrderController::class, 'activeQueue']
        );

        Route::get(
            '/orders/{order}/queue',
            [OrderController::class, 'queue']
        );

        Route::get(
            '/customer/orders/{order}/queue',
            [OrderController::class, 'queue']
        );

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::get('/customer/notifications', [NotificationController::class, 'index']);
        Route::patch('/customer/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/customer/notifications/read-all', [NotificationController::class, 'markAllRead']);

        Route::get(
            '/credit-account',
            [
                CreditAccountController::class,
                'show'
            ]
        );
        Route::get(
            '/customer/credit',
            [
                CreditAccountController::class,
                'show'
            ]
        );

        /*
         * Customer Payments
         */
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/summary', [PaymentController::class, 'summary']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
        Route::get('/customer/payments', [PaymentController::class, 'index']);
        Route::get('/customer/payments/summary', [PaymentController::class, 'summary']);
        Route::get('/customer/payments/{payment}', [PaymentController::class, 'show']);

        /*
         * Customer Complaints
         */
        Route::get('/complaints', [ComplaintController::class, 'index']);
        Route::post('/complaints', [ComplaintController::class, 'store']);
        Route::get('/complaints/{complaint}', [ComplaintController::class, 'show']);
        Route::get('/customer/complaints', [ComplaintController::class, 'index']);
        Route::post('/customer/complaints', [ComplaintController::class, 'store']);
        Route::get('/customer/complaints/{complaint}', [ComplaintController::class, 'show']);
        Route::get('/chat', [ChatController::class, 'show']);
        Route::post('/chat/messages', [ChatController::class, 'send']);
    });

    // PayMongo calls this endpoint directly; it is authenticated by its
    // webhook signature rather than a customer token.
    Route::post('/payments/paymongo/webhook', [PayMongoController::class, 'webhook'])
        ->middleware('throttle:public-api');

    /*
     * Public FAQ & Complaint Categories
     */
    Route::get('/faqs', [FaqController::class, 'index'])->middleware('throttle:public-api');
    Route::get('/customer/faqs', [FaqController::class, 'index'])->middleware('throttle:public-api');
    Route::get('/complaint-categories', [ComplaintController::class, 'categories'])->middleware('throttle:public-api');
    Route::get('/customer/complaint-categories', [ComplaintController::class, 'categories'])->middleware('throttle:public-api');

    Route::get('/station', [RoutingController::class, 'station'])->middleware('throttle:public-api');
    Route::get('/routing/station', [RoutingController::class, 'station'])->middleware('throttle:public-api');
    Route::get('/routing/route', [RoutingController::class, 'route'])
        ->middleware(['auth:sanctum', 'throttle:public-api']);
    Route::get('/deliveries/{delivery}/route', [RoutingController::class, 'delivery'])
        ->middleware(['auth:sanctum', 'throttle:public-api']);


    /*
|--------------------------------------------------------------------------
| Driver Routes
|--------------------------------------------------------------------------
|
| Only authenticated users with the DRIVER role
| can access these endpoints.
|
*/

    Route::middleware([
        'auth:sanctum',
        'role:driver',
        'throttle:authenticated-write',
    ])->group(function () {

        Route::post('/driver/deliveries/{delivery}/rfid-scans', [RfidScanController::class, 'store']);
        Route::post('/driver/orders/scan-qr', [DeliveryController::class, 'scanOrderQr']);
        Route::post('/driver/deliveries/out-for-delivery/bulk', [DeliveryController::class, 'bulkOutForDelivery']);

        /*
         * Assigned Deliveries
         */
        Route::get(
            '/driver/deliveries',
            [DeliveryController::class, 'index']
        );

        /*
         * Delivery History
         */
        Route::get(
            '/driver/deliveries/history',
            [DeliveryController::class, 'history']
        );

        /*
         * Delivery Details
         */
        Route::get(
            '/driver/deliveries/{delivery}',
            [DeliveryController::class, 'show']
        );

        /*
         * Delivery Status
         */
        Route::patch(
            '/driver/deliveries/{delivery}/status',
            [DeliveryController::class, 'updateStatus']
        );

        Route::post(
            '/driver/deliveries/{delivery}/location',
            [DeliveryController::class, 'updateLocation']
        )->middleware('throttle:authenticated-write');

        /*
         * Proof of Delivery
         */
        Route::post(
            '/driver/deliveries/{delivery}/proof',
            [DeliveryController::class, 'uploadProof']
        );

        /*
         * STEP 15 — Collect Payment
         */
        Route::patch(
            '/driver/deliveries/{delivery}/collect-payment',
            [
                DeliveryController::class,
                'collectPayment'
            ]
        );

        /*
         * STEP 19 — Report Delivery Issue
         */
        Route::post(
            '/driver/deliveries/{delivery}/issue',
            [
                DeliveryController::class,
                'reportIssue'
            ]
        );

        /*
         * Pickup Batches
         */
        Route::get(
            '/driver/pickups',
            [DeliveryController::class, 'pickupBatches']
        );
        Route::get(
            '/driver/pickup-batches',
            [DeliveryController::class, 'pickupBatches']
        );
        Route::get(
            '/driver/pickups/{batch}',
            [DeliveryController::class, 'showPickupBatch']
        );
        Route::get(
            '/driver/pickup-batches/{batch}',
            [DeliveryController::class, 'showPickupBatch']
        );
        Route::patch(
            '/driver/pickups/{batch}/status',
            [DeliveryController::class, 'updatePickupBatchStatus']
        );
        Route::patch(
            '/driver/pickup-batches/{batch}/status',
            [DeliveryController::class, 'updatePickupBatchStatus']
        );
        Route::post(
            '/driver/pickups/{batch}/stops/{item}/collect',
            [DeliveryController::class, 'collectPickupStop']
        );
        Route::post(
            '/driver/pickup-batches/{batch}/items/{item}/collect',
            [DeliveryController::class, 'collectPickupStop']
        );

        /*
         * Delivery Routes
         */
        Route::get(
            '/driver/routes',
            [DeliveryController::class, 'deliveryRoutes']
        );
        Route::get(
            '/driver/delivery-routes',
            [DeliveryController::class, 'deliveryRoutes']
        );
        Route::get(
            '/driver/routes/{route}',
            [DeliveryController::class, 'showDeliveryRoute']
        );
        Route::get(
            '/driver/delivery-routes/{route}',
            [DeliveryController::class, 'showDeliveryRoute']
        );
        Route::patch(
            '/driver/routes/{route}/status',
            [DeliveryController::class, 'updateDeliveryRouteStatus']
        );
        Route::patch(
            '/driver/delivery-routes/{route}/status',
            [DeliveryController::class, 'updateDeliveryRouteStatus']
        );
        Route::patch(
            '/driver/routes/{route}/stops/{stop}/status',
            [DeliveryController::class, 'updateDeliveryStopStatus']
        );
        Route::patch(
            '/driver/delivery-routes/{route}/stops/{stop}/status',
            [DeliveryController::class, 'updateDeliveryStopStatus']
        );

        /*
         * Driver Notifications
         */
        Route::get('/driver/notifications', [NotificationController::class, 'index']);
        Route::patch('/driver/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/driver/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::get('/driver/chat', [ChatController::class, 'show']);
        Route::post('/driver/chat/messages', [ChatController::class, 'send']);
    });

    /*
    |--------------------------------------------------------------------------
    | IoT Hardware & RFID Integration (ESP32)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['rfid.device', 'throttle:120,1'])->prefix('iot/rfid')->group(function () {
        Route::get('/registration-session/active', [\App\Http\Controllers\Api\Iot\RfidDeviceController::class, 'activeRegistrationSession']);
        Route::post('/registration-scan', [\App\Http\Controllers\Api\Iot\RfidDeviceController::class, 'registrationScan']);
        Route::post('/heartbeat', [\App\Http\Controllers\Api\Iot\RfidDeviceController::class, 'heartbeat']);
        Route::post('/scan', [\App\Http\Controllers\Api\Iot\RfidDeviceController::class, 'scan']);
    });

});

// Root /api/iot/rfid alias for direct hardware compatibility
Route::middleware(['rfid.device', 'throttle:120,1'])->prefix('iot/rfid')->group(function () {
    Route::get('/registration-session/active', [\App\Http\Controllers\Api\Iot\RfidDeviceController::class, 'activeRegistrationSession']);
    Route::post('/registration-scan', [\App\Http\Controllers\Api\Iot\RfidDeviceController::class, 'registrationScan']);
    Route::post('/heartbeat', [\App\Http\Controllers\Api\Iot\RfidDeviceController::class, 'heartbeat']);
    Route::post('/scan', [\App\Http\Controllers\Api\Iot\RfidDeviceController::class, 'scan']);
});

