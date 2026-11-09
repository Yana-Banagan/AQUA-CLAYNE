# AQUA CLAYNE --- Revised Operations, Driver, Customer Online Payment & Supabase Integration Specification

**Project:** AQUA CLAYNE Water Refilling Station Management System\
**Stack:** Laravel + Blade Admin Web + Flutter Customer/Driver Mobile
App + Supabase PostgreSQL/Realtime/Storage + Firebase FCM + PayMongo\
**Repository:** `C:\Projects\AQUA CLAYNE`\
**Primary database:** Supabase PostgreSQL\
**Drivers:** 2 active operational drivers\
**Document purpose:** Consolidate the revised two-driver
pickup/refill/delivery workflow without duplicating the existing
customer features, while adding PayMongo online payment on the existing
Customer Mobile checkout/order flow.

------------------------------------------------------------------------

## 1. IMPORTANT IMPLEMENTATION RULE

This document is a **revision/extension**, not a second system.

The existing AQUA CLAYNE Customer Mobile features, authentication,
products/refill services, addresses, cart, checkout, orders,
notifications, credit/debt, complaints, FAQ, and profile remain in
place.

Do **not** create duplicate:

-   customer modules;
-   customer order modules;
-   authentication modules;
-   product/service modules;
-   address modules;
-   cart modules;
-   notification modules;
-   payment modules;
-   user/customer tables;
-   order tables;
-   existing delivery tables if an equivalent already exists.

Instead:

1.  inspect the existing Laravel migrations/models/controllers/routes
    first;
2.  reuse existing tables and services;
3.  add only missing columns/tables/indexes;
4.  refactor existing Admin/Driver workflows where necessary;
5.  connect all new records to the existing `orders`, `users`,
    `customers`, and payment records;
6.  keep Supabase PostgreSQL as the source of truth.

The existing business revision remains authoritative: a normal order is
a **water refill service using customer-owned gallons**, not a sale of
station-owned gallons. Customer-owned gallons are physically collected,
received at the station, placed into the refill queue, refilled, and
returned to the customer. The queue begins only after physical station
intake. fileciteturn1file0L30-L61

------------------------------------------------------------------------

# 2. CURRENT SYSTEM THAT MUST REMAIN

## 2.1 Customer Mobile --- DO NOT REDESIGN THE BUSINESS FLOW

The Customer Mobile App continues to provide:

-   Registration
-   Login
-   Profile
-   Addresses
-   Products / Refill Services
-   Cart
-   Checkout
-   Orders
-   Payments
-   Credit / Debt
-   Delivery Tracking
-   Notifications
-   FAQ
-   Complaints

The customer still creates a normal refill order using the existing
checkout flow.

### Customer-owned gallon rule

Example:

``` text
5-Gallon Purified Water Refill
Quantity: 3
Container ownership: CUSTOMER_OWNED
Refill price: ₱50 each

Total = ₱150
```

The customer's physical gallons are not station inventory.
fileciteturn1file0L51-L61

------------------------------------------------------------------------

# 3. CUSTOMER FLOW --- ONLY ADD ONLINE PAYMENT

The customer-side flow is kept almost completely unchanged.

## 3.1 Existing order flow

``` text
Customer
   ↓
Select refill service
   ↓
Enter quantity
   ↓
Select customer address
   ↓
Checkout
   ↓
Choose payment method
   ├── Cash
   ├── Approved Credit
   └── Online Payment ← NEW
   ↓
Place Order
   ↓
PENDING
   ↓
Admin confirmation
   ↓
Pickup scheduling
   ↓
Driver pickup
   ↓
Station intake
   ↓
Refill queue
   ↓
Refilling
   ↓
Ready for delivery
   ↓
Delivery route
   ↓
Customer receives gallons
   ↓
Proof of delivery
   ↓
COMPLETED
```

The existing canonical order lifecycle remains:

``` text
PENDING
CONFIRMED
ASSIGNED
PICKED_UP
RECEIVED_AT_STATION
WAITING_IN_QUEUE
REFILLING
READY_FOR_DELIVERY
OUT_FOR_DELIVERY
DELIVERED
COMPLETED
```

Exception states remain:

``` text
REJECTED
CANCELLED
PICKUP_FAILED
DELIVERY_FAILED
```

Arbitrary status jumping is prohibited; backend services enforce
transitions. fileciteturn1file0L322-L349

------------------------------------------------------------------------

# 4. NEW CUSTOMER PAYMENT OPTION

Add one new payment method to the existing checkout:

``` text
PAYMENT METHOD

○ Cash
○ Credit
○ Pay Online
```

When `Pay Online` is selected:

``` text
Customer
   ↓
Review order
   ↓
Tap "Pay Online"
   ↓
Laravel creates PayMongo Checkout Session
   ↓
Flutter receives checkout URL
   ↓
Flutter opens PayMongo Hosted Checkout
   ↓
Customer chooses supported PayMongo payment method
   ↓
Customer pays
   ↓
PayMongo processes payment
   ↓
PayMongo webhook → Laravel
   ↓
Laravel verifies payment
   ↓
Existing payment record updated
   ↓
Order payment status = PAID
   ↓
Customer returns to AQUA CLAYNE
   ↓
Order remains connected to normal fulfillment
```

PayMongo's current Hosted Checkout API supports a backend-created
Checkout Session and returns a `checkout_url` for the customer. PayMongo
currently recommends the `/v2/checkout_sessions` API for new
integrations. citeturn1search0turn1search8

------------------------------------------------------------------------

# 5. PAYMONGO DESIGN

## 5.1 Recommended integration

Use:

``` text
Laravel API
    ↓
PayMongo Hosted Checkout
    ↓
Flutter opens checkout_url
```

Do not place the PayMongo secret key in Flutter.

### Secret key

Store only on Laravel:

``` env
PAYMONGO_SECRET_KEY=sk_test_xxxxxxxxx
PAYMONGO_PUBLIC_KEY=pk_test_xxxxxxxxx
PAYMONGO_WEBHOOK_SECRET=xxxxxxxxx
```

The secret key must never be committed to Git.

Flutter must never contain:

``` text
sk_test_...
sk_live_...
```

------------------------------------------------------------------------

# 6. PAYMONGO CHECKOUT SESSION

When the customer chooses online payment, Laravel creates one Checkout
Session for the existing order.

Conceptually:

``` text
Order
   ↓
Existing Payment Record
   ↓
PayMongo Checkout Session
```

Do not create a second AQUA CLAYNE order.

The existing order ID is the business reference.

Recommended PayMongo reference:

``` text
AC-ORDER-{order_id}
```

Example:

``` text
AC-ORDER-1042
```

The Checkout Session should include:

-   amount;
-   PHP currency;
-   refill service line item;
-   quantity;
-   order reference;
-   success URL;
-   cancel URL;
-   allowed payment methods;
-   metadata containing the AQUA CLAYNE payment/order reference.

PayMongo Checkout Sessions support line items, payment method types,
redirect URLs, reference numbers, and metadata.
citeturn1search0turn1search9

------------------------------------------------------------------------

# 7. ONLINE PAYMENT DATABASE RULE

Before creating anything, inspect the existing payment table.

If an existing table already represents customer payments, **reuse it**.

For example, if the current table is:

``` text
payments
```

do not create:

``` text
online_payments
```

unless the existing design genuinely cannot represent gateway
transactions.

Prefer adding missing fields to the existing payment record.

Recommended fields if missing:

``` text
payment_method
payment_status
gateway
gateway_reference
gateway_checkout_session_id
gateway_payment_intent_id
gateway_payment_id
gateway_fee
gateway_net_amount
paid_at
failure_reason
```

Possible values:

``` text
payment_method:
cash
credit
online

payment_status:
pending
paid
failed
cancelled
refunded
```

For PayMongo:

``` text
gateway = paymongo
```

------------------------------------------------------------------------

# 8. PAYMENT RECORD RELATIONSHIP

The relationship must remain:

``` text
users
  ↓
customers
  ↓
orders
  ↓
payments
  ↓
PayMongo transaction
```

The PayMongo transaction is an external payment gateway transaction.

It is not a replacement for the AQUA CLAYNE payment record.

------------------------------------------------------------------------

# 9. PAYMONGO PAYMENT FLOW

## 9.1 Create checkout endpoint

Recommended endpoint:

``` http
POST /api/v1/customer/orders/{order}/payment/checkout
```

Authentication:

``` text
auth:sanctum
role:customer
```

The backend must verify:

1.  the order belongs to the authenticated customer;
2.  the order is payable;
3.  the amount is calculated from the server-side order;
4.  there is no already-paid payment;
5.  the order is not cancelled/rejected;
6.  a new active checkout session is allowed.

The customer must never submit the final payment amount as a trusted
value.

------------------------------------------------------------------------

# 10. SERVER-SIDE AMOUNT CALCULATION

Never trust:

``` json
{
  "amount": 150
}
```

from Flutter.

Laravel calculates:

``` text
service price
×
quantity
+
approved applicable fees
-
approved discounts
=
server total
```

Then:

``` text
PHP 150.00
→
15000 centavos
```

PayMongo represents PHP amounts in the smallest currency unit; for
example, PHP 100.00 is `10000`. citeturn1search3turn1search4

------------------------------------------------------------------------

# 11. FLUTTER ONLINE PAYMENT UI

Do not replace the current checkout screen.

Add a payment-method option to the existing screen:

``` text
--------------------------------
CHECKOUT
--------------------------------

Refill Service
5-Gallon Purified Water

Quantity
3 gallons

Delivery Address
...

Order Total
₱150.00

Payment Method

( ) Cash
( ) Credit
(•) Pay Online

        [ PAY ₱150.00 ]
--------------------------------
```

After tapping Pay Online:

``` text
Creating secure checkout...
```

Then open the PayMongo hosted checkout URL.

The Flutter app should not implement its own card-number form unless a
future requirement specifically calls for a direct Payment Intent
integration.

Hosted Checkout keeps payment entry on PayMongo's checkout page.

------------------------------------------------------------------------

# 12. PAYMONGO RETURN FLOW

Use two URLs:

``` text
success_url
cancel_url
```

Example conceptual routes:

``` text
https://your-domain.com/payment/paymongo/success
https://your-domain.com/payment/paymongo/cancel
```

These routes do not decide that the payment succeeded.

The success page means:

``` text
Customer returned from checkout
```

It does NOT automatically mean:

``` text
Payment = PAID
```

The backend uses PayMongo's webhook/payment information as the
authoritative confirmation.

PayMongo's current Hosted Checkout documentation describes confirming
payment through webhook events and exposes payment/session identifiers
and payment status in the Checkout Session data.
citeturn1search0turn1search9

------------------------------------------------------------------------

# 13. PAYMONGO WEBHOOK

Create:

``` http
POST /api/v1/payments/paymongo/webhook
```

This endpoint must not require customer authentication.

Reason:

``` text
PayMongo → Laravel
```

not:

``` text
Customer → Laravel
```

The webhook must validate the request using the PayMongo webhook
mechanism configured for the account.

Process events such as:

``` text
payment.paid
payment.failed
```

PayMongo documents webhook confirmation for successful and failed
payments. citeturn1search1turn1search3

------------------------------------------------------------------------

# 14. WEBHOOK IDEMPOTENCY

The same webhook must not create duplicate payment records.

Use:

``` text
gateway_payment_id
```

or another unique PayMongo event/transaction identifier.

Before processing:

``` text
IF webhook/payment already processed
    return 200
ELSE
    process
```

This prevents:

``` text
one payment
   ↓
two payment records
```

------------------------------------------------------------------------

# 15. ONLINE PAYMENT STATUS RULE

Recommended:

``` text
Checkout created
    ↓
PENDING
    ↓
Customer pays
    ↓
PayMongo webhook
    ↓
PAID
```

If payment fails:

``` text
FAILED
```

If customer cancels:

``` text
CANCELLED / EXPIRED CHECKOUT
```

Do not mark a payment as paid merely because Flutter returned to the
success URL.

------------------------------------------------------------------------

# 16. PAYMENT + ORDER COMPLETION

Online payment does not bypass fulfillment.

The existing business rule requires delivery proof before completion.

For online payment:

``` text
DELIVERED
   +
PROOF OF DELIVERY
   +
VALID PAID PAYMENT
   ↓
COMPLETED
```

This follows the existing backend-enforced payment completion principle:
payment and proof are both required before completion.
fileciteturn1file0L222-L248

------------------------------------------------------------------------

# 17. CUSTOMER ORDER SCREEN

Do not create a new Order module.

Modify the existing order details screen to display:

``` text
Order #AC-1042

Status
READY FOR DELIVERY

Payment
PAID ONLINE

Payment Method
PayMongo

Amount Paid
₱150.00

Paid At
September 16, 2026 3:15 PM

Delivery
Driver assigned
```

If unpaid:

``` text
Payment
UNPAID

[ PAY ONLINE ]
```

If already paid:

``` text
Payment
PAID
```

Do not show a second payment workflow if the order is already paid.

------------------------------------------------------------------------

# 18. CUSTOMER NOTIFICATIONS

Reuse the existing notification module.

Add:

``` text
Online payment initiated
Online payment successful
Online payment failed
Online payment refunded
```

Existing order notifications remain connected:

``` text
Order confirmed
Driver assigned
Empty gallons picked up
Gallons received at station
Order entered refill queue
Refill started
Refill completed
Out for delivery
Delivered
Payment recorded
Balance updated
```

These notification events are already part of the revised business flow.
fileciteturn1file0L250-L268

------------------------------------------------------------------------

# 19. REVISED ADMIN OPERATIONS MODEL

The major Admin change is operational management.

The Admin must no longer manually treat every customer order as an
isolated driver assignment.

There are only:

``` text
2 Drivers
```

Therefore, the Admin manages:

``` text
UNASSIGNED ORDERS
       ↓
PICKUP BATCHES
       ↓
STATION INTAKE
       ↓
REFILL QUEUE
       ↓
READY ORDERS
       ↓
DELIVERY ROUTES
       ↓
2 DRIVERS
```

------------------------------------------------------------------------

# 20. ADMIN NAVIGATION

Keep existing modules.

Modify Operations to:

``` text
OPERATIONS
├── Orders
├── Dispatch Board
│   ├── Pickup
│   └── Delivery
├── Refill Queue
├── Delivery Routes
├── Driver Board
├── Payments
└── Credit / Debt
```

Do not create duplicate Orders, Payments, or Credit modules.

------------------------------------------------------------------------

# 21. ADMIN ORDERS PAGE

The existing Orders page remains.

Add operational fields:

``` text
Order #
Customer
Area
Quantity
Order Status
Payment Status
Pickup Status
Queue Status
Delivery Status
Assigned Driver / Route
Requested Schedule
```

The Admin can filter:

``` text
Pending Confirmation
Ready for Pickup
Unassigned
Picked Up
Waiting for Intake
Waiting in Refill Queue
Refilling
Ready for Delivery
Unassigned Delivery
Out for Delivery
Delivered
Payment Unpaid
Payment Paid
Exceptions
```

------------------------------------------------------------------------

# 22. DISPATCH BOARD

Create one operational page:

``` text
DISPATCH BOARD
```

with two sections.

## Pickup

``` text
UNASSIGNED PICKUP ORDERS
        ↓
PICKUP BATCHES
        ↓
DRIVER 1 / DRIVER 2
```

## Delivery

``` text
READY FOR DELIVERY
        ↓
DELIVERY ROUTES
        ↓
DRIVER 1 / DRIVER 2
```

------------------------------------------------------------------------

# 23. PICKUP BATCHING

The system should group multiple customer pickup orders.

Example:

``` text
Pickup Batch PB-001

Driver: Driver 1
Area: Lipa
Stops: 8
Total empty gallons: 24
Status: READY
```

Another:

``` text
Pickup Batch PB-002

Driver: Driver 2
Area: Malvar
Stops: 7
Total empty gallons: 20
Status: READY
```

The Admin approves the suggested batch before dispatch.

------------------------------------------------------------------------

# 24. PICKUP BATCH RULES

A pickup batch should consider:

-   driver availability;
-   vehicle capacity;
-   number of gallons;
-   geographic area;
-   customer requested schedule;
-   existing route;
-   current driver assignment;
-   operational priority.

Never create a batch exceeding configured capacity.

Example:

``` text
Vehicle capacity = 30 gallons

Current batch = 27 gallons
New order = 6 gallons

27 + 6 = 33

RESULT:
Do not add order to batch.
Create/suggest another batch.
```

------------------------------------------------------------------------

# 25. DRIVER BOARD

The Admin should see exactly two active operational drivers.

Example:

``` text
DRIVER BOARD

Driver 1
Status: AVAILABLE
Current Task: Pickup Batch PB-003
Area: Lipa
Stops: 6
Gallons: 18

Driver 2
Status: ON ROUTE
Current Task: Delivery Route DR-002
Area: Malvar
Stops Completed: 4 / 8
```

Possible driver states:

``` text
AVAILABLE
ASSIGNED
ON_PICKUP
RETURNING
AT_STATION
ON_DELIVERY
OFFLINE
UNAVAILABLE
```

------------------------------------------------------------------------

# 26. DRIVER WORK QUEUE

Each driver can perform multiple jobs during the day.

Example:

``` text
Driver 1

08:00 Pickup Batch PB-001
10:00 Return to Station
10:30 Pickup Batch PB-003
12:00 Station
13:30 Delivery Route DR-001
15:30 Delivery Route DR-004
```

The system does not assume:

``` text
1 driver = 1 customer
```

Instead:

``` text
1 driver
   ↓
multiple stops
   ↓
multiple batches/routes
   ↓
multiple jobs per day
```

------------------------------------------------------------------------

# 27. PICKUP ASSIGNMENT MODEL

A pickup assignment is separate from final delivery.

Conceptual relationship:

``` text
Order
   ↓
Pickup Assignment
   ↓
Pickup Batch
   ↓
Driver
```

Suggested data:

``` text
pickup_batches
pickup_batch_items
pickup_assignments
```

If an equivalent existing delivery-assignment table already exists,
extend/refactor it rather than creating duplicate assignment tables.

------------------------------------------------------------------------

# 28. PICKUP STATUS

Use a dedicated pickup status:

``` text
ASSIGNED
EN_ROUTE
ARRIVED
COLLECTED
RETURNING
RECEIVED
FAILED
```

The customer order status is updated by backend services based on
operational events.

Do not allow Flutter to directly set arbitrary order statuses.

------------------------------------------------------------------------

# 29. DRIVER PICKUP SCREEN

The existing Driver Mobile app remains.

Modify it to show:

``` text
DRIVER HOME

Today's Work
-----------------------

Pickup Tasks
2

Delivery Routes
1

Completed
8
```

------------------------------------------------------------------------

# 30. DRIVER PICKUP BATCH SCREEN

Example:

``` text
PICKUP BATCH PB-001

Area
Lipa

Stops
8

Expected Gallons
24

Driver
Driver 1

[ START PICKUP ]
```

Then:

``` text
STOP 1

Customer
Juan Dela Cruz

Phone
09xxxxxxxxx

Address
...

Expected
3 gallons

[ MARK ARRIVED ]
[ RECORD COLLECTION ]
```

------------------------------------------------------------------------

# 31. PICKUP COLLECTION

Driver records:

``` text
Expected: 3
Received: 3
Damaged: 0
Accepted: 3
```

If:

``` text
Expected: 3
Received: 2
```

the driver must report the discrepancy.

Do not silently change the order quantity.

The existing business rule requires expected and actual quantities to be
reconciled before queue entry. fileciteturn1file0L185-L200

------------------------------------------------------------------------

# 32. STATION INTAKE

When the driver returns:

``` text
Pickup Batch
   ↓
Station
   ↓
Physical Intake
```

The station verifies:

``` text
Expected
Received
Accepted
Rejected/Damaged
```

Only accepted gallons enter the refill queue.

------------------------------------------------------------------------

# 33. REFILL QUEUE

The refill queue remains a station-side operational module.

It starts only after physical intake.

Default:

``` text
FIFO
```

meaning:

``` text
first verified at station
        ↓
first eligible for refill
```

The existing queue states remain:

``` text
WAITING
IN_PROGRESS
COMPLETED
REMOVED
```

Only one active queue entry should exist per refill order.
fileciteturn1file0L126-L159

------------------------------------------------------------------------

# 34. REFILL QUEUE ADMIN UI

Example:

``` text
REFILL QUEUE

#   Order      Customer       Qty   Status
1   AC-1042    Juan           3     WAITING
2   AC-1043    Maria          5     WAITING
3   AC-1044    Pedro          2     REFILLING
```

Show:

``` text
Queue position
Customer
Order number
Quantity
Received at
Waiting duration
Priority
Status
```

Actions:

``` text
START REFILL
MARK READY
VIEW HISTORY
CHANGE PRIORITY
```

The existing Admin queue requirements already specify these operational
fields/actions. fileciteturn1file0L270-L287

------------------------------------------------------------------------

# 35. QUEUE PRIORITY

Manual priority changes must be controlled.

Required audit fields:

``` text
Order
Old Position
New Position
Changed By
Reason
Timestamp
```

No silent queue manipulation.

fileciteturn1file0L161-L183

------------------------------------------------------------------------

# 36. DELIVERY ROUTES

After refilling:

``` text
REFILLING
   ↓
READY_FOR_DELIVERY
```

The system creates/suggests delivery routes.

Example:

``` text
Delivery Route DR-001

Driver: Driver 1
Area: Lipa
Stops: 7
Total Filled Gallons: 22
Status: READY
```

------------------------------------------------------------------------

# 37. DELIVERY ROUTE GROUPING

Group ready orders by:

1.  geographic area;
2.  customer requested schedule;
3.  vehicle capacity;
4.  driver availability;
5.  operational priority.

Example:

``` text
READY ORDERS

Lipa
 ├── AC-1001
 ├── AC-1005
 ├── AC-1012
 └── AC-1018

Malvar
 ├── AC-1002
 ├── AC-1007
 └── AC-1011
```

Suggested routes:

``` text
DR-001 → Lipa → Driver 1
DR-002 → Malvar → Driver 2
```

The Admin approves the suggested route.

------------------------------------------------------------------------

# 38. DELIVERY STOP

Each route contains multiple stops.

Conceptually:

``` text
Delivery Route
    ├── Stop 1 → Order A
    ├── Stop 2 → Order B
    ├── Stop 3 → Order C
    └── Stop 4 → Order D
```

Recommended stop data:

``` text
delivery_stops
- id
- delivery_route_id
- order_id
- stop_sequence
- status
- arrived_at
- delivered_at
- failure_reason
```

If the current database already has delivery-stop functionality, extend
it rather than duplicating it.

------------------------------------------------------------------------

# 39. DRIVER DELIVERY ROUTE SCREEN

Example:

``` text
ACTIVE DELIVERY ROUTE

DR-001
Lipa Area

Progress
3 / 7 completed

1. Juan Dela Cruz      ✓
2. Maria Santos        ✓
3. Pedro Cruz          ✓
4. Ana Reyes           →
5. Carlo Garcia
6. ...
```

The driver only sees the customer information needed for delivery:

``` text
Customer name
Phone
Address
Order number
Quantity
Instructions
Payment status when operationally necessary
```

Do not expose full customer history or unrelated payment/credit
information.

------------------------------------------------------------------------

# 40. DELIVERY STOP FLOW

``` text
ASSIGNED
   ↓
EN_ROUTE
   ↓
ARRIVED
   ↓
DELIVERED
```

If unsuccessful:

``` text
FAILED
```

A failed stop does not automatically cancel successful stops in the same
route.

Example:

``` text
Route:
7 stops

Delivered:
6

Failed:
1
```

The route can finish with an exception and the failed order can be
rescheduled.

------------------------------------------------------------------------

# 41. PROOF OF DELIVERY

The existing Supabase Storage proof-of-delivery feature remains.

Driver must provide required proof before the backend accepts:

``` text
DELIVERED
```

The file remains in the existing private Storage workflow.

Do not create another proof-of-delivery storage system.

------------------------------------------------------------------------

# 42. DELIVERY + PAYMENT

For Cash:

``` text
Delivered
+
Proof of Delivery
+
Cash Collection Recorded
↓
Completed
```

For Credit:

``` text
Delivered
+
Proof of Delivery
+
Valid Credit Transaction
↓
Completed
```

For Online:

``` text
Delivered
+
Proof of Delivery
+
PayMongo Payment = PAID
↓
Completed
```

The backend enforces all three conditions.

------------------------------------------------------------------------

# 43. TWO-DRIVER DAILY OPERATING MODEL

The system must support:

``` text
Driver 1
Driver 2
```

without requiring an Admin to manually assign every individual customer.

Example:

``` text
MORNING

Driver 1 → Pickup Batch Lipa
Driver 2 → Pickup Batch Malvar

MIDDAY

Both → Station Intake / Refill Operations

AFTERNOON

Driver 1 → Delivery Route Lipa
Driver 2 → Delivery Route Malvar
```

The actual schedule is determined by:

-   available orders;
-   capacity;
-   customer requested time;
-   current driver state;
-   route area;
-   station readiness.

------------------------------------------------------------------------

# 44. ADMIN DAILY DISPATCH BOARD

The Admin should see:

``` text
TODAY'S OPERATIONS

------------------------------------------------
DRIVER 1
------------------------------------------------
08:00 Pickup PB-001
10:00 Station Intake
13:00 Delivery DR-001
15:00 Delivery DR-004

------------------------------------------------
DRIVER 2
------------------------------------------------
08:00 Pickup PB-002
10:30 Station Intake
13:30 Delivery DR-002
16:00 Delivery DR-005
------------------------------------------------
```

This is a dispatch view, not a second order-management system.

------------------------------------------------------------------------

# 45. SYSTEM-GENERATED BATCH SUGGESTION

The system may suggest:

``` text
Suggested Pickup Batch
```

based on:

``` text
unassigned orders
+
area
+
requested schedule
+
gallon quantity
+
driver availability
+
vehicle capacity
```

Admin approves:

``` text
[ APPROVE BATCH ]
```

or adjusts:

``` text
[ EDIT ]
```

The system must never exceed vehicle capacity.

------------------------------------------------------------------------

# 46. DATABASE ARCHITECTURE

The source of truth remains:

``` text
Supabase PostgreSQL
```

Conceptual relationship:

``` text
users
  │
  ├── customers
  │      │
  │      └── orders
  │              │
  │              ├── payments
  │              │      └── PayMongo reference
  │              │
  │              ├── pickup assignment
  │              │      └── pickup batch
  │              │             └── driver
  │              │
  │              ├── physical intake
  │              │
  │              ├── refill queue
  │              │
  │              └── delivery stop
  │                     └── delivery route
  │                            └── driver
  │
  └── drivers
```

------------------------------------------------------------------------

# 47. NEW/ADAPTED DATABASE COMPONENTS

Only add what is missing.

Potential operational tables:

``` text
pickup_batches
pickup_batch_items
physical_intakes
physical_intake_items
refill_queue
delivery_routes
delivery_stops
```

Potential payment additions to the existing `payments` table:

``` text
gateway
gateway_reference
gateway_checkout_session_id
gateway_payment_intent_id
gateway_payment_id
gateway_fee
gateway_net_amount
```

Do not create duplicate versions of existing tables.

------------------------------------------------------------------------

# 48. DATABASE MIGRATION STRATEGY

Before migration:

``` bash
php artisan migrate:status
```

Inspect:

``` text
backend/database/migrations/
backend/app/Models/
backend/app/Services/
backend/app/Http/Controllers/
backend/routes/
```

Then determine:

``` text
Does equivalent table already exist?
    ↓
YES → alter/reuse
NO  → create
```

Never create:

``` text
payments_v2
orders_v2
drivers_v2
deliveries_v2
customers_v2
```

------------------------------------------------------------------------

# 49. RECOMMENDED FOREIGN KEYS

All operational records must remain connected.

Examples:

``` text
pickup_batches.driver_id
pickup_batch_items.pickup_batch_id
pickup_batch_items.order_id

physical_intakes.order_id
physical_intake_items.physical_intake_id

refill_queue.order_id

delivery_routes.driver_id
delivery_stops.delivery_route_id
delivery_stops.order_id

payments.order_id
```

Use UUID types consistently with the existing AQUA CLAYNE schema.

Do not mix:

``` text
bigint
```

and:

``` text
uuid
```

for related primary/foreign keys.

------------------------------------------------------------------------

# 50. SUPABASE REALTIME

Use Supabase Realtime for operational visibility where already
configured.

Useful live updates:

``` text
Order status changed
Pickup assignment changed
Pickup batch status changed
Physical intake confirmed
Queue position changed
Refill started
Refill completed
Delivery route changed
Delivery stop completed
Payment status changed
```

Do not broadcast sensitive customer information through public/anonymous
channels.

------------------------------------------------------------------------

# 51. CUSTOMER REALTIME EXPERIENCE

Customer remains connected to the existing order.

Example:

``` text
Customer Order
      ↓
Supabase Realtime
      ↓
Order status update
      ↓
Flutter UI refresh
```

Customer may see:

``` text
Confirmed
Driver Assigned
Picked Up
Received at Station
Waiting in Queue
Refilling
Ready for Delivery
Out for Delivery
Delivered
Completed
```

The queue position is calculated/validated by the backend.
fileciteturn1file0L250-L268

------------------------------------------------------------------------

# 52. DRIVER REALTIME EXPERIENCE

Driver app receives only relevant assigned work.

``` text
Driver 1
   ↓
assigned pickup batches
assigned delivery routes
assigned stops
```

Driver 2 receives only:

``` text
Driver 2
   ↓
assigned pickup batches
assigned delivery routes
assigned stops
```

A driver must not retrieve another driver's private operational workload
by changing an ID in the request.

Backend ownership checks are mandatory.

------------------------------------------------------------------------

# 53. DRIVER API OWNERSHIP

For driver endpoints:

``` text
authenticated user
      ↓
user.driver
      ↓
driver.id
      ↓
only assigned records
```

Never accept:

``` text
driver_id
```

from Flutter as the source of authorization.

The authenticated user determines the driver identity.

------------------------------------------------------------------------

# 54. CUSTOMER API OWNERSHIP

For customer order/payment endpoints:

``` text
authenticated user
      ↓
user.customer
      ↓
customer.id
      ↓
only own orders
```

A customer must receive:

``` text
403
```

if attempting to access another customer's order/payment.

------------------------------------------------------------------------

# 55. ADMIN AUTHORIZATION

Only authorized Admin users can:

-   confirm/reject orders;
-   create pickup batches;
-   assign drivers;
-   modify dispatch plans;
-   confirm station intake where permitted;
-   start/finish refill operations where permitted;
-   modify queue priority;
-   create delivery routes;
-   reassign routes;
-   view payment operations;
-   manage exceptions.

All sensitive actions must be audited.

------------------------------------------------------------------------

# 56. AUDIT LOG

Continue using the existing audit system.

Log:

``` text
order confirmation
order rejection
pickup batch creation
pickup batch assignment
driver reassignment
intake confirmation
quantity discrepancy
queue priority change
refill start
refill completion
delivery route creation
delivery route reassignment
payment status changes
refunds
manual corrections
```

Do not create a second audit table if an existing audit table already
handles this.

------------------------------------------------------------------------

# 57. PAYMENT SECURITY

Never put these in Flutter:

``` text
PAYMONGO_SECRET_KEY
database service-role key
Supabase service-role key
webhook secret
```

Flutter may receive:

``` text
checkout_url
```

and open it.

The Laravel backend communicates with PayMongo using the secret key.

PayMongo's current documentation requires the secret API key for
server-side Payment Intent/Checkout Session creation.
citeturn1search2turn1search6

------------------------------------------------------------------------

# 58. PAYMONGO TEST MODE

Start with:

``` text
sk_test_...
pk_test_...
```

Do not use live keys during development.

Test:

``` text
successful payment
failed payment
customer cancellation
webhook delivery
duplicate webhook
expired checkout
incorrect order ownership
already-paid order
cancelled order
```

PayMongo provides test-mode payment scenarios and test credentials in
its documentation. citeturn1search1turn1search6

------------------------------------------------------------------------

# 59. ONLINE PAYMENT ACCEPTANCE

Recommended first implementation:

``` text
PayMongo Hosted Checkout
```

This avoids building a separate payment UI for each method.

Depending on the PayMongo account/configuration, the hosted checkout can
expose supported payment methods such as cards, GCash, and QR Ph.
citeturn1search0turn1search4

The exact available methods should be determined by the PayMongo account
and current merchant configuration.

------------------------------------------------------------------------

# 60. BACKEND SERVICES

Reuse existing services where possible.

Recommended responsibilities:

``` text
OrderService
    ↓
Order lifecycle

DispatchService
    ↓
Pickup batching
Delivery route planning

PickupService
    ↓
Pickup status

PhysicalIntakeService
    ↓
Expected vs received

RefillQueueService
    ↓
Queue
Priority
Start refill
Finish refill

DeliveryRouteService
    ↓
Routes
Stops
Delivery status

PaymentService
    ↓
Existing payment records

PayMongoService
    ↓
Checkout Session
Webhook verification
Gateway status

NotificationService
    ↓
Customer/driver/admin notifications
```

If equivalent services already exist, extend them instead of creating
duplicates.

------------------------------------------------------------------------

# 61. RECOMMENDED PAYMONGO SERVICE METHODS

Conceptually:

``` php
createCheckoutSession(Order $order): array

handleWebhook(array $payload): void

markPaymentPaid(...): void

markPaymentFailed(...): void

getCheckoutStatus(...): array
```

The service should be the only application layer responsible for
communicating with PayMongo.

------------------------------------------------------------------------

# 62. PAYMENT CONTROLLER

Recommended endpoints:

``` http
POST /api/v1/customer/orders/{order}/payment/checkout

GET /api/v1/customer/orders/{order}/payment

POST /api/v1/payments/paymongo/webhook
```

The existing payment endpoints must be reused where equivalent endpoints
already exist.

------------------------------------------------------------------------

# 63. FLUTTER PAYMENT REPOSITORY

Extend the existing customer payment/order repository.

Do not create a second customer payment repository if one already
exists.

Conceptual method:

``` dart
Future<String> createPayMongoCheckout(String orderId)
```

The API returns:

``` json
{
  "checkout_url": "https://checkout.paymongo.com/..."
}
```

Flutter opens:

``` text
checkout_url
```

using the existing approved URL-launching mechanism.

------------------------------------------------------------------------

# 64. PAYMENT RETURN HANDLING

After PayMongo redirects back:

``` text
Flutter
   ↓
Refresh order/payment
   ↓
GET existing payment status
   ↓
Display actual backend status
```

Example:

``` text
Payment
PAID

Order
CONFIRMED
```

or:

``` text
Payment
FAILED

[ TRY AGAIN ]
```

The Flutter app must not assume success based solely on navigation.

------------------------------------------------------------------------

# 65. RETRY RULE

If a PayMongo payment fails:

``` text
payment_status = failed
```

Customer may select:

``` text
[ PAY ONLINE AGAIN ]
```

This creates/reuses an appropriate new checkout attempt linked to the
same order.

Do not create another order.

------------------------------------------------------------------------

# 66. DUPLICATE-PAYMENT PROTECTION

Before creating a new online checkout:

``` text
IF existing payment is PAID
    do not create another payment
```

If an active checkout exists:

``` text
reuse or expire according to the gateway/application rules
```

If previous checkout failed:

``` text
allow another attempt
```

All attempts remain linked to the same order.

------------------------------------------------------------------------

# 67. REFUND HANDLING

If a refund feature is later required:

``` text
PayMongo refund
      ↓
existing payment record
      ↓
payment_status = refunded
      ↓
audit log
      ↓
customer notification
```

Do not create a separate refund system unless the existing payment
module does not support refunds.

------------------------------------------------------------------------

# 68. ADMIN PAYMENTS PAGE

Keep the existing Payments page.

Add filters:

``` text
Cash
Credit
Online
Paid
Pending
Failed
Refunded
```

Show:

``` text
Order #
Customer
Amount
Payment Method
Payment Status
Gateway
Gateway Reference
Paid At
```

For PayMongo:

``` text
Gateway = PayMongo
```

Admin does not manually mark a PayMongo payment as paid unless a
controlled reconciliation procedure explicitly requires it.

------------------------------------------------------------------------

# 69. CUSTOMER CREDIT IS NOT REMOVED

The existing Credit/Debt feature remains.

Payment methods remain:

``` text
Cash
Credit
Online
```

Credit continues to use the existing credit rules.

Online payment is an additional payment method, not a replacement for
credit.

------------------------------------------------------------------------

# 70. INVENTORY RULE

Do not change customer-owned gallon ownership into inventory.

Normal refill:

``` text
Customer gallon
     ↓
Pickup
     ↓
Station intake
     ↓
Refill
     ↓
Return
```

It does not mean:

``` text
station inventory sale
```

The existing master documentation explicitly states that customer-owned
gallons must not blindly decrement the station `gallons` table.
fileciteturn1file0L202-L220

------------------------------------------------------------------------

# 71. END-TO-END FINAL FLOW

``` text
CUSTOMER
   │
   ├── Select refill service
   ├── Quantity
   ├── Address
   ├── Checkout
   └── Payment Method
          │
          ├── Cash
          ├── Credit
          └── Pay Online
                 │
                 ▼
            PayMongo Checkout
                 │
                 ▼
            Payment Webhook
                 │
                 ▼
            Payment = PAID
                 │
                 ▼
            ORDER = PENDING
                 │
                 ▼
ADMIN
   │
   ├── Confirms order
   └── Dispatch Board
          │
          ▼
PICKUP BATCH
          │
          ▼
DRIVER 1 / DRIVER 2
          │
          ▼
CUSTOMER
          │
          ▼
EMPTY GALLONS COLLECTED
          │
          ▼
STATION INTAKE
          │
          ▼
REFILL QUEUE
          │
          ▼
REFILLING
          │
          ▼
READY FOR DELIVERY
          │
          ▼
DELIVERY ROUTE
          │
          ▼
DRIVER
          │
          ├── Stop 1
          ├── Stop 2
          ├── Stop 3
          └── Stop N
          │
          ▼
CUSTOMER RECEIVES
          │
          ▼
PROOF OF DELIVERY
          │
          ▼
PAYMENT VERIFIED
          │
          ▼
COMPLETED
```

------------------------------------------------------------------------

# 72. COMPLETE DATABASE CONNECTION

Every layer must connect to the same Supabase PostgreSQL database:

``` text
Flutter Customer
       │
       ▼
Laravel API
       │
       ▼
Supabase PostgreSQL
       ▲
       │
Laravel Admin Web

Flutter Driver
       │
       ▼
Laravel API
       │
       ▼
Supabase PostgreSQL
```

External services:

``` text
Laravel
 ├── Supabase PostgreSQL
 ├── Supabase Storage
 ├── Supabase Realtime
 ├── Firebase FCM
 └── PayMongo
```

------------------------------------------------------------------------

# 73. NO DUPLICATION RULE

Before writing code, always search the project.

Examples:

``` bash
php artisan route:list
php artisan migrate:status
```

Search:

``` text
Order
Payment
Delivery
Driver
Notification
Audit
Queue
```

If an equivalent component exists:

``` text
MODIFY / EXTEND
```

If it does not exist:

``` text
CREATE
```

Never blindly create another:

``` text
Controller
Model
Migration
Repository
Service
Route
Table
```

with the same responsibility.

------------------------------------------------------------------------

# 74. IMPLEMENTATION ORDER

## Step 1 --- Backup and branch

``` bash
git status
git checkout develop
git pull
git checkout -b feature/revised-dispatch-paymongo
```

------------------------------------------------------------------------

## Step 2 --- Inspect current database

``` bash
php artisan migrate:status
```

Inspect Supabase tables.

Record existing:

``` text
users
customers
drivers
orders
payments
deliveries
notifications
audit_logs
```

and any existing queue/route/intake tables.

------------------------------------------------------------------------

## Step 3 --- Inspect existing customer modules

Confirm:

``` text
Customer checkout
Customer orders
Customer payment
Customer notifications
Customer addresses
```

Do not rebuild them.

------------------------------------------------------------------------

## Step 4 --- Inspect existing Driver modules

Confirm:

``` text
Driver authentication
Driver home
Driver delivery
Driver status
Proof of delivery
```

Refactor the existing delivery concept into:

``` text
Pickup work
Delivery routes
Delivery stops
```

where required.

------------------------------------------------------------------------

## Step 5 --- Add/alter database structures

Only create missing tables.

Use UUIDs consistently.

Run:

``` bash
php artisan migrate
```

Then verify in Supabase.

------------------------------------------------------------------------

## Step 6 --- Implement dispatch services

Implement/extend:

``` text
DispatchService
PickupService
PhysicalIntakeService
RefillQueueService
DeliveryRouteService
```

------------------------------------------------------------------------

## Step 7 --- Update Admin

Implement:

``` text
Dispatch Board
Driver Board
Pickup Batches
Delivery Routes
Refill Queue
```

without removing the existing Orders/Payments/Credit modules.

------------------------------------------------------------------------

## Step 8 --- Update Driver

Implement:

``` text
Pickup Tasks
Pickup Batch
Collection
Return to Station
Delivery Routes
Delivery Stops
Proof of Delivery
```

------------------------------------------------------------------------

## Step 9 --- Configure PayMongo

Create/configure PayMongo test credentials.

Laravel `.env`:

``` env
PAYMONGO_SECRET_KEY=sk_test_xxxxxxxxx
PAYMONGO_PUBLIC_KEY=pk_test_xxxxxxxxx
PAYMONGO_WEBHOOK_SECRET=xxxxxxxxx
```

Do not commit `.env`.

------------------------------------------------------------------------

## Step 10 --- Implement PayMongo backend

Implement:

``` text
PayMongoService
Checkout endpoint
Webhook endpoint
Payment status endpoint
```

------------------------------------------------------------------------

## Step 11 --- Connect Customer Mobile

Modify existing:

``` text
Checkout
Payment Method
Order Details
Payment Status
```

Add:

``` text
Pay Online
```

Do not create a new customer order system.

------------------------------------------------------------------------

## Step 12 --- Test payment

Test:

``` text
Success
Failure
Cancel
Retry
Duplicate webhook
Already paid
Wrong customer
Cancelled order
```

------------------------------------------------------------------------

## Step 13 --- Test operations

Test:

``` text
Many customer orders
↓
Pickup batching
↓
2 drivers
↓
Station intake
↓
Queue
↓
Refilling
↓
Delivery route
↓
Multiple stops
↓
Proof of delivery
↓
Payment
↓
Completed
```

------------------------------------------------------------------------

# 75. REQUIRED TEST SCENARIO --- MANY CUSTOMERS

Example:

``` text
20 customer orders
2 drivers
```

System should NOT require:

``` text
Admin manually assigns 20 orders
```

Instead:

``` text
20 orders
   ↓
System groups by area/capacity
   ↓
Pickup Batch 1
Pickup Batch 2
Pickup Batch 3
...
   ↓
2 Drivers execute batches
   ↓
Station intake
   ↓
Refill queue
   ↓
Ready orders
   ↓
Delivery Route 1
Delivery Route 2
Delivery Route 3
...
```

Admin supervises the dispatch board and handles exceptions.

------------------------------------------------------------------------

# 76. REQUIRED TEST SCENARIO --- ONLINE PAYMENT

``` text
Customer places order
        ↓
Selects Pay Online
        ↓
Laravel creates PayMongo Checkout Session
        ↓
Flutter opens checkout_url
        ↓
Customer completes payment
        ↓
PayMongo webhook
        ↓
Laravel validates event
        ↓
Existing payment record = PAID
        ↓
Customer order shows PAID
        ↓
Normal pickup/refill/delivery continues
        ↓
Driver delivers
        ↓
Proof of delivery
        ↓
Backend verifies payment + proof
        ↓
COMPLETED
```

------------------------------------------------------------------------

# 77. REQUIRED SECURITY TESTS

### Customer

``` text
Customer A cannot view Customer B order
Customer A cannot pay Customer B order
Customer cannot modify payment amount
Customer cannot mark payment as paid
```

### Driver

``` text
Driver 1 cannot access Driver 2 route
Driver cannot access unassigned route
Driver cannot mark another driver's stop delivered
Driver cannot modify payment status
```

### Admin

``` text
Unauthorized user cannot access Admin dispatch
Unauthorized user cannot reorder queue
```

### PayMongo

``` text
Secret key never reaches Flutter
Webhook cannot create duplicate payment
Invalid webhook rejected
Already-paid order cannot be charged again accidentally
```

------------------------------------------------------------------------

# 78. DEFINITION OF DONE

This revision is complete only when:

-   [ ] Existing Customer registration works.
-   [ ] Existing Customer login works.
-   [ ] Existing Customer profile works.
-   [ ] Existing Customer addresses work.
-   [ ] Existing Customer catalog/refill services work.
-   [ ] Existing Customer cart works.
-   [ ] Existing Customer checkout works.
-   [ ] Existing Customer orders work.
-   [ ] Existing Customer credit works.
-   [ ] Existing Customer notifications work.
-   [ ] Existing complaints/FAQ work.
-   [ ] Customer can choose Cash.
-   [ ] Customer can choose Credit.
-   [ ] Customer can choose Pay Online.
-   [ ] PayMongo test checkout opens.
-   [ ] PayMongo payment webhook works.
-   [ ] Successful PayMongo payment updates the existing payment record.
-   [ ] Failed payment does not become PAID.
-   [ ] Duplicate webhook does not duplicate payment.
-   [ ] Customer can retry failed online payment.
-   [ ] Customer cannot pay another customer's order.
-   [ ] Existing order is not duplicated.
-   [ ] Existing payment module is not duplicated.
-   [ ] Admin can confirm orders.
-   [ ] Admin can create/manage pickup batches.
-   [ ] Admin can assign the two drivers.
-   [ ] Admin can view Driver 1 and Driver 2.
-   [ ] Driver can view assigned pickup work.
-   [ ] Driver can record collected gallons.
-   [ ] Station can confirm physical intake.
-   [ ] Orders enter refill queue only after intake.
-   [ ] FIFO queue works.
-   [ ] Controlled queue priority works.
-   [ ] Priority changes are audited.
-   [ ] Admin can manage delivery routes.
-   [ ] One route can contain multiple customers.
-   [ ] Driver can complete multiple delivery stops.
-   [ ] Proof of delivery remains required.
-   [ ] Online-paid orders cannot be completed without delivery proof.
-   [ ] Supabase PostgreSQL stores all persistent records.
-   [ ] Supabase Realtime updates operational/customer status where
    configured.
-   [ ] Supabase Storage continues handling private proof files.
-   [ ] Firebase FCM continues notifications.
-   [ ] Audit logs contain important operational/payment changes.
-   [ ] No duplicate tables/modules were introduced.
-   [ ] All migrations succeed.
-   [ ] API authorization tests pass.
-   [ ] Flutter customer tests pass.
-   [ ] Flutter driver tests pass.
-   [ ] Admin tests pass.
-   [ ] End-to-end test passes.

------------------------------------------------------------------------

# 79. FINAL ARCHITECTURE

``` text
                         ┌──────────────────────┐
                         │   CUSTOMER FLUTTER   │
                         │                      │
                         │ Existing Features    │
                         │ + Pay Online         │
                         └──────────┬───────────┘
                                    │
                                    ▼
                         ┌──────────────────────┐
                         │     LARAVEL API      │
                         │                      │
                         │ Auth                 │
                         │ Orders               │
                         │ Payments             │
                         │ Dispatch             │
                         │ Pickup               │
                         │ Intake               │
                         │ Refill Queue         │
                         │ Delivery Routes      │
                         │ Notifications        │
                         └──────┬───────┬───────┘
                                │       │
                 ┌──────────────┘       └─────────────────┐
                 ▼                                        ▼
       ┌──────────────────┐                    ┌──────────────────┐
       │ SUPABASE         │                    │ PAYMONGO         │
       │                  │                    │                  │
       │ PostgreSQL       │                    │ Hosted Checkout  │
       │ Realtime         │                    │ Payment Gateway  │
       │ Storage          │                    │ Webhooks         │
       └──────────────────┘                    └──────────────────┘
                 ▲
                 │
       ┌─────────┴─────────┐
       │                   │
       │                   │
┌──────┴────────┐  ┌───────┴─────────┐
│ ADMIN WEB     │  │ DRIVER FLUTTER  │
│               │  │                 │
│ Orders        │  │ Pickup Batches  │
│ Dispatch      │  │ Intake          │
│ Driver Board  │  │ Delivery Routes │
│ Refill Queue  │  │ Stops           │
│ Routes        │  │ Proof of Delivery│
│ Payments      │  │ Profile         │
└───────────────┘  └─────────────────┘

             ┌─────────────────────────┐
             │       FIREBASE FCM      │
             │                         │
             │ Customer Notifications  │
             │ Driver Notifications    │
             └─────────────────────────┘
```

------------------------------------------------------------------------

# 80. FINAL BUSINESS RULE

AQUA CLAYNE is not changing the Customer Mobile application into a new
system.

The final design is:

``` text
CUSTOMER
    ↓
Existing order/check-out
    ↓
Optional PayMongo online payment
    ↓
Existing order
    ↓
ADMIN DISPATCH
    ↓
2 DRIVERS
    ↓
BATCHED PICKUP
    ↓
PHYSICAL STATION INTAKE
    ↓
REFILL QUEUE
    ↓
REFILL
    ↓
BATCHED DELIVERY ROUTES
    ↓
MULTIPLE CUSTOMER STOPS
    ↓
PROOF OF DELIVERY
    ↓
PAYMENT VERIFIED
    ↓
COMPLETED
```

The customer continues using the existing Customer Mobile application.
The main operational change is on **Admin Web + Driver Mobile**, where
individual orders are organized into pickup batches and delivery routes
so two drivers can handle many customers efficiently.

The only new customer-facing capability in this revision is **PayMongo
online payment**, which is connected to the existing order/payment
records rather than creating a separate payment system.

All persistent data remains connected to the existing Supabase
PostgreSQL database, while Supabase Realtime, Storage, Firebase FCM, and
the existing Laravel API continue to serve their current
responsibilities.
