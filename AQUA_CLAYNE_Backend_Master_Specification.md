# AQUA CLAYNE Water Refilling System
## Complete Backend Master Specification
### Laravel + Supabase PostgreSQL + Sanctum + Realtime + Storage + Firebase FCM

> **Document purpose:** This is the canonical backend blueprint for AQUA CLAYNE after the revised business-flow decision that customers normally provide their own empty gallons and the station performs a pickup → intake → refill → return-delivery workflow.
>
> **Frontend scope:** The Admin Web and Flutter applications consume this backend, but this document focuses on backend architecture, database design, APIs, services, authorization, state transitions, transactions, station-refill logic, notifications, security, testing, and deployment.

---

# 1. Backend Responsibilities

The Laravel backend is the single trusted business-logic layer for:

- Admin Web
- Customer Flutter app
- Driver Flutter app

No client is allowed to decide authorization, role, ownership, station processing status, order status, payment completion, or delivery completion on its own.

```text
ADMIN WEB (session auth) ───────┐
                                │
CUSTOMER FLUTTER (Sanctum) ─────┼──→ LARAVEL
                                │      │
DRIVER FLUTTER (Sanctum) ───────┘      ├── Authentication / Authorization
                                       ├── Validation
                                       ├── Business Services
                                       ├── Transactions
                                       ├── Audit
                                       ├── Notifications
                                       ├── Realtime Signals
                                       └── Storage Proxy
                                                │
                                      SUPABASE POSTGRESQL
```

Laravel migrations are the canonical database schema.

---

# 2. Technology Stack

```text
Backend Framework      Laravel
Web Authentication     Laravel session authentication
Mobile Authentication  Laravel Sanctum bearer tokens
Database               Supabase PostgreSQL
ORM                    Eloquent
Realtime               Supabase Realtime signal-table pattern
File Storage            Supabase Storage through Laravel proxy
Push Notifications      Firebase Cloud Messaging
API Testing             Postman
Version Control         Git / GitHub
```

Production must use HTTPS.

Secrets such as the Supabase service-role key, database password, Firebase credentials, and Laravel `APP_KEY` remain server-side only.

---

# 3. Roles

Canonical roles:

```text
ADMIN
CUSTOMER
DRIVER
```

Optional future admin permission levels may be added, but the core system requires these three.

## 3.1 Admin

Admin may:

- manage customers;
- create/deactivate drivers;
- manage products/services;
- confirm/reject/cancel orders;
- assign/reassign drivers;
- receive customer gallons at the station;
- manage station refill operations;
- start/finish refilling;
- verify/record payments;
- manage customer credit;
- manage RFID/station gallons;
- manage complaints and FAQs;
- send announcements;
- view reports;
- manage maintenance;
- view audit logs.

## 3.2 Customer

Customer may:

- self-register only as `CUSTOMER`;
- manage own profile and addresses;
- view available refill services;
- place own orders;
- view/cancel own orders when allowed;
- view own payments and balance;
- receive own delivery and order-status information;
- submit complaints;
- use FAQs;
- receive notifications.

## 3.3 Driver

Driver may:

- log in only to an Admin-created account;
- view only assigned deliveries;
- collect customer-owned empty gallons;
- confirm pickup;
- return gallons to station;
- later collect ready filled gallons;
- mark out-for-delivery;
- collect payment when applicable;
- upload proof of delivery;
- complete permitted delivery steps;
- perform authorized RFID/QR operations.

A Driver may never self-register.

---

# 4. Authentication Architecture

## 4.1 Admin Web

Use Laravel session authentication.

```text
POST /login
Auth::attempt(...)
session regenerate
redirect to /admin/dashboard
```

Protected routes:

```php
Route::middleware(['auth', 'role:admin'])->group(...);
```

## 4.2 Mobile

Use Laravel Sanctum.

```text
POST /api/register     CUSTOMER only
POST /api/login
POST /api/logout
GET  /api/me
```

Registration ignores/rejects any client-provided role and hard-codes:

```text
role = CUSTOMER
```

Driver creation is available only through the Admin backend.

---

# 5. Core Backend Layering

Use:

```text
Route
  ↓
Middleware
  ↓
Form Request / Validation
  ↓
Controller
  ↓
Service
  ↓
Model / Repository-style Eloquent query
  ↓
PostgreSQL
```

Controllers stay thin.

Business rules belong in services.

Recommended major services:

```text
AuthService
CustomerService
DriverService
ProductService
OrderService
OrderStatusService
ContainerIntakeService
RefillOperationService
DeliveryService
PaymentService
CreditService
RfidService
ComplaintService
FaqService
NotificationService
RealtimeEventService
SupabaseStorageService
ReportService
MaintenanceService
AuditService
```

---

# 6. Revised Business Model

The primary sale is a **refill service**.

Example:

```text
5-Gallon Purified Water Refill
Unit price: ₱50
Quantity: 3

Customer provides:
3 empty customer-owned gallons

Total:
₱150
```

Do not interpret this as the customer purchasing three new station-owned gallons.

Canonical field:

```text
container_ownership = CUSTOMER_OWNED
```

Future values may include:

```text
STATION_OWNED
MIXED
```

but `CUSTOMER_OWNED` is the default business flow described in this project.

---

# 7. Canonical Order Lifecycle

Normal refill order:

```text
PENDING
   ↓
CONFIRMED
   ↓
ASSIGNED
   ↓
PICKED_UP
   ↓
RECEIVED_AT_STATION
   ↓
REFILLING
   ↓
READY_FOR_DELIVERY
   ↓
OUT_FOR_DELIVERY
   ↓
DELIVERED
   ↓
COMPLETED
```

Exception/terminal states:

```text
REJECTED
CANCELLED
PICKUP_FAILED
DELIVERY_FAILED
```

Status transitions must be server-enforced.

Clients send an intended action; the backend decides whether the resulting transition is valid.

---

# 8. Order State Transition Matrix

| Current | Allowed Next | Actor |
|---|---|---|
| PENDING | CONFIRMED, REJECTED, CANCELLED | Admin / allowed customer cancellation |
| CONFIRMED | ASSIGNED, CANCELLED | Admin |
| ASSIGNED | PICKED_UP, PICKUP_FAILED, CANCELLED | Driver/Admin |
| PICKED_UP | RECEIVED_AT_STATION | Admin/authorized station staff |
| RECEIVED_AT_STATION | REFILLING | Admin/Station after valid intake |
| REFILLING | READY_FOR_DELIVERY | Admin/Station |
| READY_FOR_DELIVERY | OUT_FOR_DELIVERY | Assigned Driver |
| OUT_FOR_DELIVERY | DELIVERED, DELIVERY_FAILED | Driver |
| DELIVERED | COMPLETED | Backend after proof/payment rules |
| REJECTED | — | terminal |
| CANCELLED | — | terminal |
| COMPLETED | — | terminal |

`*` Cancellation after physical intake requires explicit operational handling and audit logging.

---

# 9. Customer Order Creation

## 9.1 Request

Example:

```json
{
  "address_id": "uuid",
  "items": [
    {
      "product_id": "uuid",
      "quantity": 3
    }
  ],
  "container_ownership": "CUSTOMER_OWNED",
  "expected_empty_gallons": 3,
  "payment_method": "CASH",
  "notes": "Please pick up after 2 PM"
}
```

## 9.2 OrderService::create()

Must run inside a DB transaction.

Steps:

```text
1. Resolve authenticated customer.
2. Confirm address belongs to customer.
3. Validate every selected refill service.
4. Validate quantity > 0.
5. Validate service availability/capacity.
6. Calculate prices server-side.
7. Ignore any client-submitted totals.
8. Create order.
9. Create order_items.
10. Create initial payment/credit state as applicable.
11. Create initial order status-history entry.
12. Emit safe realtime signal.
13. Create relevant notification.
14. Commit.
```

For `CUSTOMER_OWNED` refill orders:

> Do not decrement station-owned gallon inventory merely because quantity = number of gallons to refill.

If a separate water/capacity inventory is implemented, decrement/reserve only the correct refill consumable/capacity resource.

---

# 10. Customer-Owned Container Intake

A new backend concept is required because physical containers must be reconciled before refilling.

Recommended table:

```text
order_container_intakes
```

Recommended columns:

```text
id uuid PK
order_id uuid FK unique
expected_count integer
received_count integer
accepted_count integer
damaged_count integer default 0
rejected_count integer default 0
returned_count integer default 0
condition_notes text nullable
received_by uuid FK users
received_at timestamp
completed_at timestamp nullable
created_at
updated_at
```

Rules:

```text
accepted_count <= received_count
received_count >= 0
returned_count <= accepted_count
```

Refilling may begin only when the intake record is valid and finalized.

---

# 11. Station Refill Operations

## 11.1 Purpose

Station staff process an order directly after physical intake. The order remains
`RECEIVED_AT_STATION` until refilling starts; no separate station refill operation, position,
priority, or order status history is stored.

```text
PICKED_UP
   ↓
RECEIVED_AT_STATION
   ↓
INTAKE VERIFIED
   ↓
REFILLING
   ↓
READY_FOR_DELIVERY
```

## 11.2 Transaction Safety

Starting a refill is transactional and uses a database advisory lock so only one
eligible order can be actively refilled at a time.

```text
DB transaction
  ↓
acquire station refill lock
  ↓
validate order.status = RECEIVED_AT_STATION
validate finalized intake and accepted_count > 0
  ↓
update order = REFILLING
  ↓
history + audit + realtime
  ↓
commit
```

---

# 12. Refilling Operations

## 12.1 Start Refill

Admin action:

```text
POST /admin/orders/{order}/refill/start
```

Preconditions:

```text
order.status = RECEIVED_AT_STATION
valid intake exists
accepted_count > 0
no other order is actively refilling
```

Effects:

```text
order.status = REFILLING
status history created
audit created
realtime signal emitted
customer notification optional/required by product decision
```

## 12.2 Finish Refill

Admin action:

```text
POST /admin/orders/{order}/refill/complete
```

Preconditions:

```text
order.status = REFILLING
```

Effects:

```text
order.status = READY_FOR_DELIVERY
history created
audit created
realtime signal
driver/customer notification
```

---

# 13. Delivery Model

The same order has two physical driver legs:

```text
LEG 1:
Station/Driver → Customer
Collect empty gallons
Return to station

LEG 2:
Station → Customer
Return refilled gallons
```

The existing `deliveries` table may continue to represent the overall delivery job, but the status workflow must support both pickup and return phases.

Recommended delivery statuses:

```text
ASSIGNED
EN_ROUTE_FOR_PICKUP
PICKED_UP
AT_STATION
READY_FOR_DELIVERY
OUT_FOR_DELIVERY
DELIVERED
FAILED
CANCELLED
```

If the existing schema cannot cleanly represent both legs, add a `delivery_events` or `delivery_legs` table later. The minimum implementation may use one delivery plus `delivery_status_history`.

---

# 14. Driver Rules

Every driver API query must scope through the authenticated driver's `driver_id`.

Example:

```php
Delivery::query()
    ->where('driver_id', $driver->id)
```

Never:

```php
Delivery::findOrFail($id);
```

without ownership validation.

Driver actions:

```text
View assigned delivery
Start pickup
Confirm empty-gallon pickup
View order quantity
View customer address/contact required for delivery
Mark returned-to-station / handoff
View ready-for-delivery assignments
Start outbound delivery
Record cash collection
Upload proof
Mark delivered
Report failure
```

---

# 15. Delivery Assignment

`DeliveryService::assign()` must:

```text
1. Verify order is CONFIRMED or otherwise assignable.
2. Verify selected driver exists and is ACTIVE.
3. Optionally verify availability.
4. Create/update delivery.
5. Set order status = ASSIGNED.
6. Write delivery status history.
7. Write audit log.
8. Emit realtime signal.
9. Notify driver.
10. Notify customer.
```

Use one DB transaction.

Reassignment requires reason and audit logging.

---

# 16. Payment Model

Supported project methods:

```text
CASH
CREDIT
```

GCash/card remain deferred unless a real gateway is integrated.

Recommended payment statuses:

```text
PENDING
PARTIAL
PAID
VERIFIED
REJECTED
FAILED
```

## 16.1 Cash

Cash may remain pending until driver/admin records collection.

For completion:

```text
required cash collected
+
proof-of-delivery rule satisfied
+
delivery delivered
=
order may complete
```

## 16.2 Credit

Credit must call `CreditService`.

Rules:

```text
customer has credit account
requested credit does not exceed limit
credit transaction written atomically
balance updated atomically
```

Partial payments create payment and/or credit transaction history.

Never calculate authoritative balances only in the frontend.

---

# 17. Completion Guard

`OrderService::complete()` or the relevant Delivery service must enforce:

```text
order.status = DELIVERED
valid proof-of-delivery exists
payment condition satisfied
accepted/returned gallon counts are operationally valid
```

Only then:

```text
order.status = COMPLETED
```

A UI button cannot bypass this rule.

---

# 18. Proof of Delivery

Uploads go through Laravel.

```text
Flutter
  ↓ multipart request
Laravel
  ↓ authorization + validation
SupabaseStorageService
  ↓ service-role upload
Private Supabase Storage
```

Store only object paths/metadata in DB.

Generate signed URLs only for authorized viewers.

Recommended proof metadata:

```text
delivery_id
photo_path
signature_path nullable
customer_confirmed boolean
captured_at
uploaded_by
```

Do not expose the Supabase service-role key to Flutter.

---

# 19. Products and Refill Services

`products` should describe sellable refill offerings.

Example:

```text
5-Gallon Purified Water Refill
3-Gallon Purified Water Refill
Alkaline Water Refill
```

Recommended fields:

```text
id
name
description
price
image_url
is_available
status
created_at
updated_at
```

If physical consumable inventory is needed, `inventory` should clearly document what its quantity represents.

Do not use station-owned gallon quantity as if it were the number of customer-owned refill requests available.

---

# 20. Station-Owned Gallons / RFID

Keep station assets separate.

Existing:

```text
gallons
rfid_tags
rfid_transactions
```

Use for station-owned or explicitly registered trackable containers.

Recommended gallon ownership column if the module is extended:

```text
ownership_type
```

Values:

```text
STATION
CUSTOMER_REGISTERED
```

The primary customer-owned refill flow does not require converting every customer gallon into a station asset.

QR simulation may continue to stand in for physical RFID hardware.

---

# 21. Order Status History

A dedicated order history table is strongly recommended for the revised workflow.

```text
order_status_history
```

Columns:

```text
id uuid
order_id uuid
from_status nullable
to_status
changed_by nullable
actor_role nullable
reason nullable
metadata jsonb nullable
created_at
```

This provides a reliable timeline for:

- Customer order tracking
- Admin order details
- Support investigation
- Reports
- Audit correlation

Do not rely only on `orders.updated_at`.

---

# 22. Database Schema — Revised Canonical Set

Existing business tables should remain, with the following revised/additional structures.

## Identity

```text
users
customers
customer_addresses
drivers
```

## Catalog

```text
products
inventory
```

## Orders

```text
orders
order_items
order_status_history        NEW/RECOMMENDED
order_container_intakes     NEW
```

## Payments / Credit

```text
payments
credit_accounts
credit_transactions
```

## Deliveries

```text
deliveries
delivery_status_history
```

## RFID / Gallons

```text
gallons
rfid_tags
rfid_transactions
```

## Customer Service

```text
complaint_categories
complaints
complaint_history
faq_categories
faqs
notifications
```

## Operations

```text
maintenance
maintenance_records
audit_logs
```

## Infrastructure

```text
realtime_events
personal_access_tokens      Sanctum
```

---

# 23. Recommended Orders Table Additions

Add fields equivalent to:

```text
container_ownership
expected_empty_gallons
payment_method
notes
confirmed_at
cancelled_at
completed_at
```

Do not duplicate data unnecessarily if an existing column already represents the same concept.

Recommended `container_ownership` default:

```text
CUSTOMER_OWNED
```

---

# 24. Core Relationships

```text
User
├── Customer
└── Driver

Customer
├── Addresses
├── Orders
├── Payments
├── CreditAccount
├── Complaints
└── Notifications

Order
├── Customer
├── Address
├── OrderItems
├── StatusHistory
├── ContainerIntake
├── Payment(s)
└── Delivery

Delivery
├── Driver
├── Order
└── StatusHistory
```

---

# 25. Customer API

Recommended routes:

```text
POST   /api/register
POST   /api/login
POST   /api/logout
GET    /api/me

GET    /api/products
GET    /api/products/{product}

GET    /api/customer/addresses
POST   /api/customer/addresses
PATCH  /api/customer/addresses/{id}
DELETE /api/customer/addresses/{id}

GET    /api/customer/orders
POST   /api/customer/orders
GET    /api/customer/orders/{id}
POST   /api/customer/orders/{id}/cancel

GET    /api/customer/payments
GET    /api/customer/credit
GET    /api/customer/notifications

GET    /api/customer/faqs

GET    /api/customer/complaints
POST   /api/customer/complaints
GET    /api/customer/complaints/{id}
```

Every customer resource endpoint must enforce ownership.

---

# 26. Customer Order Response

Useful response fields:

```json
{
  "id": "uuid",
  "order_number": "ORD-2026-00125",
  "status": "RECEIVED_AT_STATION",
  "container_ownership": "CUSTOMER_OWNED",
  "expected_empty_gallons": 3,
  "received_empty_gallons": 3,
  "accepted_empty_gallons": 3,
  "payment_status": "PENDING",
  "delivery": {
    "status": "AT_STATION",
    "eta": null
  }
}
```



---

# 27. Driver API

Recommended routes:

```text
GET  /api/driver/dashboard
GET  /api/driver/deliveries
GET  /api/driver/deliveries/history
GET  /api/driver/deliveries/{id}

POST /api/driver/deliveries/{id}/start-pickup
POST /api/driver/deliveries/{id}/confirm-pickup
POST /api/driver/deliveries/{id}/report-pickup-failure

POST /api/driver/deliveries/{id}/start-delivery
POST /api/driver/deliveries/{id}/collect-payment
POST /api/driver/deliveries/{id}/proof
POST /api/driver/deliveries/{id}/delivered
POST /api/driver/deliveries/{id}/report-failure
```

The driver must not set arbitrary `order.status` strings directly.

Use action endpoints or strict validated transitions.

---

# 28. Admin Web Routes

Recommended groups:

```text
/admin/dashboard

/admin/customers
/admin/drivers
/admin/products
/admin/inventory
/admin/orders
/admin/payments
/admin/credit
/admin/deliveries
/admin/gallons
/admin/rfid
/admin/complaints
/admin/faqs
/admin/notifications
/admin/reports
/admin/maintenance
/admin/audit
/admin/profile
/admin/settings
```

Important action routes:

```text
POST /admin/orders/{order}/confirm
POST /admin/orders/{order}/reject
POST /admin/orders/{order}/cancel

POST /admin/deliveries/{order}/assign
POST /admin/deliveries/{delivery}/reassign
POST /admin/deliveries/{delivery}/eta

POST /admin/orders/{order}/intake
POST /admin/orders/{order}/refill/start
POST /admin/orders/{order}/refill/complete
```

---

# 29. Container Intake API Contract

Admin submits:

```json
{
  "received_count": 3,
  "accepted_count": 3,
  "damaged_count": 0,
  "rejected_count": 0,
  "condition_notes": null
}
```

Backend:

```text
validate order = PICKED_UP
validate values
transaction
  create/update intake
  set order = RECEIVED_AT_STATION
  finalize intake
  retain order = RECEIVED_AT_STATION until refill starts
  history
  audit
  realtime
  notification
commit
```

This may be one atomic station-intake action.

---

# 30. Realtime Architecture

Do not broadcast sensitive operational rows directly over the anon key.

Use:

```text
realtime_events
```

Signal payload example:

```json
{
  "topic": "orders",
  "entity_id": "uuid",
  "event": "updated"
}
```

Avoid:

```json
{
  "customer_name": "...",
  "address": "...",
  "phone": "..."
}
```

Client behavior:

```text
Realtime signal arrives
        ↓
Client calls authenticated Laravel endpoint
        ↓
Laravel returns authorized fresh data
```

Recommended new signals:

```text
order.created
order.updated
refill.started
refill.completed
delivery.updated
payment.updated
```

---

# 31. FCM Notifications

`NotificationService` should:

```text
1. Create DB notification row.
2. Send FCM when appropriate.
3. Store safe payload IDs only.
4. Remove/update stale token on permanent token failure.
```

Recommended customer events:

```text
Order confirmed
Driver assigned
Empty gallons picked up
Gallons received at station
Received at station
Refill started
Ready for delivery
Out for delivery
Delivered
Payment recorded
Balance updated
Complaint response
```

Recommended driver events:

```text
New assignment
Assignment changed
Pickup cancelled
Order ready for return delivery
ETA/admin instruction
```

---

# 32. Audit Logging

Audit all sensitive/operational actions:

```text
Admin login-related security event where appropriate
Driver creation/deactivation
Customer status change
Product price/availability changes
Order confirm/reject/cancel
Driver assign/reassign
Station gallon intake
Station refill start/completion
Refill start/complete
Payment verification/change
Credit changes
RFID/gallon changes
Complaint response/status
Maintenance changes
Settings changes
```

Recommended audit columns:

```text
actor_user_id
action
module
entity_type
entity_id
before jsonb nullable
after jsonb nullable
reason nullable
ip_address nullable
user_agent nullable
created_at
```

Audit records should be append-only for ordinary admins.

---

# 33. Complaints

Customer can create only own complaint.

Admin can:

```text
categorize
assign
respond
change status
```

Optional image uploads use the same Laravel→Supabase Storage proxy pattern.

Complaint status:

```text
OPEN
ASSIGNED
IN_PROGRESS
WAITING_FOR_CUSTOMER
RESOLVED
CLOSED
```

Admin response triggers DB notification + FCM.

---

# 34. FAQ / Auto Reply

FAQ backend stores:

```text
category
question
answer
keywords
enabled
usage_count
```

Keyword matching may be simple deterministic matching.

Fallback response is a setting.

This is an FAQ auto-reply system, not an unrestricted autonomous AI agent.

---

# 35. Maintenance

Tables support:

```text
Equipment
Maintenance schedules
Maintenance records
```

Status:

```text
SCHEDULED
IN_PROGRESS
COMPLETED
OVERDUE
CANCELLED
```

Reports may calculate due-soon and overdue items.

---

# 36. Reports

Backend reports should aggregate authoritative DB values.

Report types:

```text
Daily / Weekly / Monthly / Custom Sales
Orders
Payments
Credit / Debt
Deliveries
Customers
Driver Performance
RFID / Gallons
Inventory
Maintenance
Station Refill Performance
```

Useful station refill metrics:

```text
Average time from station intake to refill start
Average refill duration
Gallons received at station
Orders completed per day
Longest station-processing delay
Refill throughput
```

Exports may support:

```text
CSV
Excel
PDF
Print-oriented HTML
```

---

# 37. Validation Rules

Examples:

## Order

```text
address belongs to authenticated customer
items not empty
quantity integer >= 1
product active and available
container_ownership allowed enum
expected_empty_gallons >= 1
```

## Intake

```text
order is PICKED_UP
received_count >= 0
accepted_count >= 0
accepted_count <= received_count
damaged_count + rejected_count <= received_count
```

## Refill

```text
valid finalized intake exists
accepted_count > 0
order status is RECEIVED_AT_STATION
no other order is actively refilling
```

## Payment

```text
amount > 0
cannot over-collect unless explicitly supported
payment method allowed
credit limit enforced
```

---

# 38. Database Transactions

Mandatory transactional operations:

```text
Customer order creation
Order confirmation where inventory/capacity reservation changes
Driver assignment/reassignment
Station intake
Station refill start
Station refill completion
Payment recording
Credit debit/repayment
Order completion
RFID issue/return
Complaint response if notification history is coupled
```

Never perform half of a business operation and leave the rest committed.

---

# 39. Concurrency Rules

Critical race conditions:

```text
Concurrent station refill start attempts
Duplicate intake submission
Duplicate driver assignment
Double payment submission
Double credit transaction
Double RFID issue/return
Driver double-tapping Delivered
```

Use combinations of:

- DB transactions;
- row locking;
- unique constraints;
- status preconditions;
- idempotency keys for appropriate API calls;
- server-side generated references.

---

# 40. Idempotency

For mobile calls that can be retried after network interruption, consider an `Idempotency-Key`.

Especially useful for:

```text
Create order
Record payment
Upload/confirm proof
Confirm pickup
Mark delivered
```

Backend stores/recognizes the key so the same operation is not duplicated.

---

# 41. Error Response Standard

Recommended JSON:

```json
{
  "success": false,
  "message": "This order is not eligible to start refilling.",
  "errors": {
    "status": [
      "Expected RECEIVED_AT_STATION."
    ]
  }
}
```

Do not expose:

- stack traces;
- SQL;
- DB credentials;
- filesystem paths;
- service-role keys.

Production errors should be logged server-side.

---

# 42. API Success Standard

Example:

```json
{
  "success": true,
  "message": "Refill started successfully.",
  "data": {
    "order_id": "uuid",
    "status": "REFILLING"
  }
}
```

Keep response conventions consistent.

---

# 43. Authorization Policies

Backend must enforce:

```text
Customer → own addresses/orders/payments/complaints only
Driver   → assigned deliveries only
Admin    → admin modules according to role/permission
```

Do not assume a hidden UI button is security.

---

# 44. Security Requirements

```text
HTTPS in production
Hash passwords with Laravel Hash
Sanctum tokens for mobile
Session regeneration on Admin login
CSRF protection for Admin forms
Rate limit auth endpoints
Validate MIME/type/size for uploads
Private storage for proof/complaint images
Signed URLs
No PII in FCM payloads
No service-role key in frontend
No arbitrary role input
No arbitrary status input
Audit sensitive actions
Disable debug in production
```

---

# 45. Laravel Backend Folder Structure

Recommended:

```text
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   └── Api/
│   │   │       ├── Customer/
│   │   │       └── Driver/
│   │   ├── Middleware/
│   │   └── Requests/
│   │       ├── Admin/
│   │       ├── Customer/
│   │       └── Driver/
│   ├── Models/
│   ├── Policies/
│   ├── Services/
│   ├── Enums/
│   └── Exceptions/
│
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
│
├── routes/
│   ├── web.php
│   └── api.php
│
├── resources/views/
│   └── admin/
│
├── config/
└── tests/
```

---

# 46. Recommended Enums

Use PHP enums or equivalent centralized constants.

```text
UserRole
OrderStatus
DeliveryStatus
PaymentStatus
PaymentMethod
ContainerOwnership
ComplaintStatus
GallonStatus
MaintenanceStatus
```

Avoid repeating raw strings across services.

---

# 47. Suggested New Migrations

The revised flow likely requires migrations equivalent to:

```text
add_refill_fields_to_orders_table
create_order_status_history_table
create_order_container_intakes_table
add_pickup_refill_status_support_to_deliveries
```

Never edit an already-deployed old migration to change production history. Add new migrations.

---

# 48. Migration Constraints

Important DB constraints:

```text
orders.expected_empty_gallons >= 1
intake counts >= 0
unique intake per order
foreign keys indexed
payment amount >= 0
credit balances constrained as business rules permit
```

Use DB constraints as a second line of defense under application validation.

---

# 49. Order Number Generation

Use a server-generated public reference separate from UUID.

Example:

```text
ORD-20260904-00125
```

Internal primary key:

```text
UUID
```

Same principle:

```text
DEL-...
PAY-...
CMP-...
Q-...
```

Never rely on sequential public IDs as authorization.

---

# 50. Station Refill Concurrency

The application permits only one active refill operation at a time. A PostgreSQL advisory lock serializes start requests; order state and finalized intake are revalidated inside the transaction.

---

# 51. Station Capacity

Recommended setting:

```text
max_concurrent_refill_orders
```

or:

```text
max_concurrent_refill_gallons
```

The backend can prevent starting more work than configured.

This can begin with a simple value of `1` and later become configurable.

---

# 52. Cancellation Rules

Suggested:

```text
PENDING
Customer may cancel if policy permits.

CONFIRMED
Customer cancellation may require Admin approval.

ASSIGNED / PICKED_UP
Restricted because driver operation has begun.

RECEIVED_AT_STATION and later
Admin-controlled cancellation only with reason and physical-container handling.
```

The backend determines eligibility.

---

# 53. Rejection Rules

Admin rejection requires reason.

Examples:

```text
Service unavailable
Address outside delivery area
Invalid order
Operational capacity issue
Other
```

Create order status history + notification.

---

# 54. Failed Pickup

Driver reports:

```text
Customer unavailable
Wrong address
No empty gallons available
Customer cancelled on arrival
Other
```

Store reason.

Order may become `PICKUP_FAILED` or return to a controlled admin-review state according to final policy.

---

# 55. Failed Delivery

Driver reports failure with reason and optional proof.

Examples:

```text
Customer unavailable
Address inaccessible
Customer refused delivery
Payment issue
Other
```

Do not mark customer gallons as returned/completed until physically resolved.

---

# 56. Customer Gallon Reconciliation

Before completion, the business should be able to answer:

```text
How many empty gallons were expected?
How many were received?
How many were accepted for refill?
How many filled gallons were returned?
```

Recommended invariant for ordinary successful orders:

```text
returned_count = accepted_count
```

Any mismatch should block automatic completion or require an explicit admin override with audit reason.

---

# 57. Admin Dashboard Backend Metrics

Provide aggregate queries/endpoints for:

```text
Total Sales
Total Orders
Pending Orders
Orders Received at Station
Gallons Awaiting Refill
Currently Refilling
Ready for Delivery
Active Deliveries
Completed Deliveries
Total Customers
Total Drivers
Outstanding Credit
Pending Payments
Open Complaints
Low Inventory/Capacity
```

Cache expensive aggregate queries briefly if necessary, but never cache user-specific authorization incorrectly.

---

# 58. Search

Admin global search can query:

```text
Customer name/email/phone
Order number
Delivery number
Payment number
Driver
RFID
Gallon
Complaint
```

Use indexed columns where appropriate.

Never expose unauthorized records to non-admin roles.

---

# 59. Notifications Table

Recommended columns:

```text
id
user_id
type
title
body
data jsonb
read_at nullable
created_at
```

`data` should contain safe references such as IDs/status, not unnecessary sensitive information.

---

# 60. Realtime Events Table

Recommended:

```text
id
topic
event
entity_id nullable
created_at
```

Keep payload intentionally non-sensitive.

Consider retention cleanup so the signal table does not grow indefinitely.

---

# 61. Storage Buckets

Recommended private buckets:

```text
delivery-proofs
complaint-attachments
```

Laravel controls:

```text
upload
authorization
delete if allowed
signed URL generation
```

Validate:

```text
MIME type
file size
extension
ownership
```

---

# 62. Logging

Use structured Laravel logs for:

```text
API failures
FCM failures
Storage failures
unexpected status-transition attempts
payment failures
database transaction failures
security events
```

Never log plaintext passwords or full authorization tokens.

---

# 63. Testing Strategy

The existing project used manual test matrices, but the revised backend should add automated coverage where possible.

Minimum high-value feature tests:

```text
Customer cannot register DRIVER
Customer cannot view another customer's order
Driver cannot view another driver's delivery
Order total ignores forged client total
Intake cannot be submitted before PICKED_UP
Two simultaneous start-refill calls do not both succeed
Refill cannot finish before start
Out-for-delivery cannot happen before READY_FOR_DELIVERY
Delivered cannot complete without proof
Cash order cannot complete without collection
Credit limit cannot be exceeded
Customer-owned refill does not decrement station-owned gallon inventory
Audit record exists after priority change
```

---

# 64. Revised End-to-End Integration Test

A canonical test scenario:

```text
1. Admin logs in.
2. Admin creates/activates Driver A.
3. Customer registers/logs in.
4. Customer creates address.
5. Customer views refill service.
6. Customer orders 3 customer-owned gallon refills.
7. Backend creates PENDING order.
8. Admin sees order.
9. Admin confirms.
10. Admin assigns Driver A.
11. Driver A receives assignment.
12. Driver A starts pickup.
13. Driver A confirms 3 empty gallons collected.
14. Admin/station receives 3 gallons.
15. Backend creates intake.
16. Backend finalizes intake.
17. Customer order remains RECEIVED_AT_STATION until refilling starts.
18. Admin starts refill.
19. Order becomes REFILLING.
20. Admin completes refill.
21. Order becomes READY_FOR_DELIVERY.
22. Driver A starts return delivery.
23. Customer receives out-for-delivery notification.
24. Driver records required payment.
25. Driver uploads proof.
26. Driver marks delivered.
27. Backend verifies returned gallon count.
28. Backend marks COMPLETED.
29. Customer sees completed order.
30. Admin reports/dashboard reflect sale and completion.
31. Audit trail contains critical actions.
```

---

# 65. Environment Variables

```text
APP_ENV
APP_KEY
APP_DEBUG
APP_URL

DB_CONNECTION=pgsql
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD

SUPABASE_URL
SUPABASE_ANON_KEY
SUPABASE_SERVICE_ROLE_KEY

FIREBASE_CREDENTIALS
```

Mobile-safe values are configured in Flutter separately.

Server-only secrets never enter the mobile repository.

---

# 66. Deployment

Production checklist:

```text
APP_ENV=production
APP_DEBUG=false
HTTPS enabled
Secrets rotated
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
background worker configured if background jobs used
scheduler configured if maintenance/cleanup jobs used
logs monitored
database backups configured
```

---

# 67. Background Jobs Recommended

Use Laravel jobs for non-transaction-critical external work:

```text
Send FCM notification
Generate large report/export
Clean old realtime_events
Process non-critical storage cleanup
```

The core DB transaction should commit business state first; external notification failure should not corrupt the order.

Use an outbox/job pattern if stronger delivery guarantees are required.

---

# 68. Scheduler Recommended

Examples:

```text
Clean old realtime signal rows
Flag overdue maintenance
Send scheduled announcements
Recalculate/monitor delayed deliveries
Optional station-processing delay alerts
```

---

# 69. Backup and Recovery

Supabase/PostgreSQL backup policy should cover:

```text
orders
payments
credit
delivery history
intake records
order status history
RFID transactions
complaints
maintenance
audit logs
```

Test restore procedures before real production use.

---

# 70. Canonical Backend Principles

The final AQUA CLAYNE backend follows these rules:

1. **Laravel is the authority.**
2. **Customer orders are refill-service requests, not automatically gallon sales.**
3. **Customer-owned gallons are physically received and reconciled.**
4. **Station refilling starts only after verified intake.**
5. **Only one order is actively refilled at a time.**
7. **Status transitions are server-enforced.**
8. **Drivers only see assigned work.**
9. **Customers only see their own data.**
10. **Driver accounts are Admin-created only.**
11. **Payments and credit are transactional.**
12. **Proof/payment/gallon reconciliation can block completion.**
13. **Realtime sends safe change signals, then clients re-fetch through Laravel.**
14. **Files are uploaded through Laravel to private Supabase Storage.**
15. **FCM payloads contain no unnecessary PII.**
16. **Sensitive operations are audited.**
17. **Station-owned RFID/gallons remain distinct from ordinary customer-owned refill containers.**

---

# 71. Final Backend Flow Summary

```text
CUSTOMER
Places refill order using own empty gallons
        ↓
LARAVEL
Validates + creates PENDING order
        ↓
ADMIN
Confirms + assigns driver
        ↓
DRIVER
Collects customer empty gallons
        ↓
STATION / ADMIN
Receives and verifies gallon count
        ↓
LARAVEL
Creates intake and makes the order eligible for station refill
        ↓
STATION REFILL OPERATIONS
        ↓
STATION
Starts refill
        ↓
REFILLING
        ↓
STATION
Marks refill finished
        ↓
READY_FOR_DELIVERY
        ↓
DRIVER
Returns filled customer-owned gallons
        ↓
PAYMENT + PROOF
        ↓
LARAVEL
Validates completion rules
        ↓
COMPLETED
        ↓
REPORTS + AUDIT + NOTIFICATIONS
```

This is the revised canonical backend behavior for continuing development of AQUA CLAYNE.
