# Water Refilling Station Management System
### Master Project Documentation — Phases 0 through 17

This document consolidates the full step-by-step build guide for the Water Refilling Station Management System into a single reference. It was built across 17 phases, each phase originally delivered as its own Word document, separated into a **Web Track** (Laravel Admin backend) and a **Mobile Track** (Flutter Customer/Driver app), with a **Shared** section wherever a decision affects both.

---

## 1. System Overview

**Components:**
- Admin Web Application (Laravel Blade, session auth)
- Customer Mobile App (Flutter, Sanctum token auth)
- Driver Mobile App (Flutter — same app as Customer, routed by role)
- Laravel REST API (the single backend for both Web and Mobile)
- Supabase (PostgreSQL cloud database, Realtime, Storage)
- Firebase Cloud Messaging (push notifications)
- Postman (API testing) · Git/GitHub (version control) · Laragon (local dev) · VS Code · Android Studio

**Architecture:**

```
                     WATER REFILLING SYSTEM
                              |
            +-----------------+-----------------+
            |                                   |
       ADMIN WEB                         FLUTTER MOBILE
       Laravel Blade                    Customer / Driver
       (session auth)                   (Sanctum token auth)
            |                                   |
            +-----------------+-----------------+
                              |
                        LARAVEL REST API
                              |
                        BUSINESS LOGIC (Services)
                              |
                     SUPABASE POSTGRESQL
                       (cloud database)
                              |
                 +------------+------------+
                 |                         |
         SUPABASE REALTIME         SUPABASE STORAGE
      (signal-table pattern)     (proxied via Laravel)
                              
                         LARAVEL
                            |
                     FIREBASE FCM
                            |
                   PUSH NOTIFICATIONS
                            |
                     FLUTTER APP
```

**Repository structure:**
```
WaterRefillingSystem/
├── backend/          Laravel project (REST API + Admin Web Blade views)
├── mobile/           Flutter project (Customer + Driver, one app, role-routed)
├── database/         sql/, erd/, documentation/
├── docs/             api/, architecture/, deployment/
└── README.md
```

**Branching model:** `main` (production-ready) → `develop` (integration) → `feature/*` (one per feature).

---

## 2. Key Architectural Decisions

These decisions recur throughout the phases and are worth understanding as a set:

| Decision | Made In | Why |
|---|---|---|
| One Flutter app for Customer **and** Driver, routed by role after login | Phase 1 | Shares API/service code; makes "no driver self-registration" a natural consequence of the UI having no Sign Up screen for that role |
| Laravel migrations are the canonical schema, not manual Supabase SQL edits | Phase 3 | Single source of truth, version-controlled |
| Two different auth mechanisms: session (Admin Web) vs Sanctum tokens (Mobile) | Phase 4 | Matches each client's nature — server-rendered vs native app |
| Driver accounts are Admin-created only; role is hard-coded server-side on registration | Phase 4 / 5 | Enforced at the API layer, not just hidden in the UI |
| **Realtime uses a dedicated `realtime_events` "signal table"**, never the real tables | Phase 9 | RLS relies on Supabase Auth (`auth.uid()`), which this project doesn't use (Laravel handles auth) — broadcasting real tables over the anon key would leak data. Clients get a non-sensitive "something changed" signal, then re-fetch full details via the authenticated Laravel API |
| **File uploads proxy through Laravel** (service-role key), not direct client-to-Storage writes | Phase 10 | Same root cause as the Realtime decision, opposite resolution — uploads are state-changing writes that deserve the same ownership checks as every other mutation |
| Notifications write a DB row **and** send an FCM push, from one service | Phase 11 | In-app history + reaches a closed app; Realtime vs FCM chosen per event based on urgency, not automatically both every time |
| RFID is simulated via QR-code scan, not real hardware | Phase 13 | No physical reader/SDK available; documented migration path if real hardware is later obtained |
| Cash + Credit only for payments; GCash/card explicitly deferred | Phase 12 | Real gateway integration needs real merchant credentials this project doesn't have |
| Admin modules are built "minimum to unblock," not fully polished, except one per phase built completely | Phase 5 onward | Keeps each phase shippable; every deferral is tracked in a scope table, not hidden |

---

## 3. Phase-by-Phase Summary

### Phase 0 — Environment & Software Setup
Installed and verified: Git, VS Code, Laragon (PHP/Composer), Node.js/npm, Flutter SDK, Android Studio (SDK, emulator), Postman. Accounts created: GitHub, Supabase, Firebase. Every install step included a verification command and troubleshooting table.

### Phase 1 — Project Structure & Version Control
Created the `WaterRefillingSystem/` root with `backend/`, `mobile/`, `database/`, `docs/`. Initialized Git with a `.gitignore` covering both Laravel and Flutter artifacts. Set up `main`/`develop`/`feature/*` branching. Decided the one-app-two-roles Flutter structure (`lib/core`, `lib/shared_widgets`, `lib/customer`, `lib/driver`).

### Phase 2 — Database Design & Schema (Supabase PostgreSQL)
Documented entity decisions (why `roles`/`permissions` became a single enum column, why `gallons`/`rfid_tags` are separate from `products`/`inventory`). Produced a 21-table normalized schema across 8 groups (Identity & Roles, Catalog & Inventory, Orders & Payments, Credit, Deliveries, RFID/Gallons, Complaints/FAQ/Notifications, Maintenance/Audit), created via SQL Editor, with Row Level Security policies and Realtime enabled on key tables.

### Phase 3 — Laravel ↔ Supabase Integration
Connected Laravel to Supabase Postgres (direct connection, port 5432). Reset the SQL-Editor-created tables and rebuilt them as 24 version-controlled Laravel migrations (SQL→Schema Builder conversion reference included). Built all Eloquent models with `HasUuids` and relationships. Verified everything with a live Tinker test creating a User → Customer → Order.

### Phase 4 — Laravel REST API & Authentication
Installed Sanctum. Built the clean architecture layering (Route → Middleware → Controller → Service → Model). Built a reusable `EnsureRole` middleware and a shared `AuthController` where `role` is hard-coded server-side on registration (never trusts client input). Admin Web got classic session auth (`Auth::attempt`); Customer/Driver got Sanctum bearer tokens from the same shared login endpoint. Closed with a Security Test Summary table.

### Phase 5 — Admin Web Modules & Driver Account Creation
Established the reusable 10-part Admin module blueprint (table → model → controller → validation → authorization → routes → views → API-if-needed → realtime-if-needed → testing) and a master 16-module scope table. Built the **Drivers module completely**: `DriverService::create()` (transactional, generates a one-time temp password), deliberately **no delete** — only deactivate — to preserve delivery history.

### Phase 6 — Flutter Project Setup & API Integration Layer
Established the API base URL plan for emulator/device/production. Built `ApiClient` (timeout handling, no silent auto-retry on state-changing calls), `TokenStorage` (flutter_secure_storage), and `AuthRepository`. Proved the whole stack with a live login round-trip and a Manual Test Matrix (valid login, wrong password, backend down, airplane mode, token persistence).

### Phase 7 — Customer Mobile: Catalog, Cart & Checkout
Built new Laravel endpoints: `ProductController`, `CustomerAddressController` (with ownership checks), and `OrderService::create()` — a DB transaction validating stock, decrementing inventory, and creating the order atomically. Built the Flutter side: repositories, a local `CartProvider`, Catalog/Address/Cart/Checkout/Order History screens. Checkout was Cash on Delivery only at this stage.

### Phase 8 — Driver Mobile: Assigned Deliveries
Closed a gap open since Phase 2: nothing connected an order to a driver. Built `DeliveryService::assign()`/`updateStatus()` (transactional, keeps `orders.status` in sync), a minimal Admin assignment page, and a driver-scoped `Api/Driver/DeliveryController` where every method checks delivery ownership. Built Driver Dashboard, Assigned Deliveries, Delivery Details, Delivery History screens. Tested isolation (Driver X never sees Driver Y's delivery).

### Phase 9 — Supabase Realtime Integration
The first "direct-to-Supabase" exception: created the `realtime_events` signal table (public-readable, anon-key-writable = denied) so Admin/Customer/Driver clients get live "something changed" signals without ever seeing sensitive data over the anon key. Wired `RealtimeEventService::emit()` into Order/Delivery services. Built live-updating Admin dashboard (supabase-js) and Flutter screens (supabase_flutter) with proper `dispose()` cleanup. Closed with a Realtime-vs-FCM explainer.

### Phase 10 — Supabase Storage Integration
Completed Phase 8's deferred proof-of-delivery capture. Decided uploads proxy through Laravel (service-role key) rather than direct anon-key writes, for the same reason as Realtime. Built `SupabaseStorageService` (upload, signed URLs), an `uploadProof()` endpoint, and a business rule: **Mark Delivered is blocked without a proof photo**. Built Flutter photo/signature capture with `image_picker`/`signature`, gated client-side to match the server rule.

### Phase 11 — Firebase Setup & FCM Push Notifications
Delivered on Phase 9's promise: Realtime only refreshes an open screen; FCM reaches a closed app. Registered the Firebase Android app, installed `kreait/firebase-php`, built `NotificationService` (writes a `notifications` row **and** sends a push, clearing stale tokens on failure). Wired into the same two Delivery triggers as Phase 9. Flutter side handled all three app states: foreground, background, terminated — with a payload-safety rule (IDs only, never PII).

### Phase 12 — Payments & Credit/Debt
Activated `credit_accounts`/`credit_transactions` (unused since Phase 2). Built `CreditService` (credit-limit enforcement) and `PaymentService` (Cash → pending until collected; Credit → paid immediately). Extended the Mark Delivered guard from Phase 10 to **also** require cash collection before completion. GCash/card explicitly deferred (no real gateway credentials).

### Phase 13 — RFID/Gallon Tracking
Activated `gallons`/`rfid_tags`/`rfid_transactions` (unused since Phase 2). Documented the hardware-substitution decision: QR-code scanning stands in for a real RFID reader, with a stated migration path. Built `RfidService` (status-transition validation preventing double-issue/return), driver scan endpoints, a minimal Admin gallon registry, and a Flutter scan-to-issue/return flow using `mobile_scanner`.

### Phase 14 — Complaints & FAQ
Closed the last two items on Phase 7's Customer scope table. FAQ = simple content (Admin writes, Customer reads). Complaints = a workflow reusing Phase 10's Storage pattern (optional photo) and Phase 11's notification pattern (push on Admin response). Explicitly chose to skip Realtime for complaints (not time-critical), consistent with the Phase 9 Realtime-vs-FCM guidance.

### Phase 15 — Admin Reports, Maintenance & Audit Trail
Closed the last three modules from Phase 5's scope table. The interesting one: `audit_logs` existed since Phase 2 but nothing wrote to it — this phase retrofitted `AuditService::log()` calls into five already-built services (Driver creation, Delivery assignment/status, Credit payment, Complaint response). Built Maintenance (equipment + due-soon flag) and Reports (revenue, orders by status, top products, outstanding credit, active drivers) as new read-only modules. No mobile work required this phase.

### Phase 16 — End-to-End Integration & Security Testing
No new features — proved the whole system works together. A single continuous 24-step script run across Admin Web, Customer app, and Driver app simultaneously, each step traced back to its originating phase. Added a global Laravel exception handler to *guarantee* no stack trace or SQL error ever leaks. Compiled a 12-row Security Test Checklist from every phase's individual security test. Ran DB constraint tests and a forced-failure transaction-rollback proof. Mobile side: an error-handling sweep, a two-device Realtime/FCM sanity check, and a subscription-leak revisit. Closed with a consolidated Known Limitations table.

### Phase 17 — Deployment Preparation
The final phase. Master environment-variable audit of every secret introduced since Phase 3, with a hard rule: rotate everything before real use, since dev-time credentials have been visible throughout. Laravel hosting guidance (PHP 8.2+, `composer install --no-dev`, cache commands, HTTPS enforcement — mandatory given Sanctum tokens travel in headers). Android release signing (keystore generation, `key.properties`, signed AAB/APK build) with a reminder that Customer and Driver ship as one app. Closed with the Final Integration Checklist — one row per phase, 0 through 17.

---

## 4. Master Environment Variable Reference

| Variable | Introduced | Secret? | Mobile-Safe? |
|---|---|---|---|
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` | Phase 3 | No | Never used directly |
| `DB_USERNAME` / `DB_PASSWORD` | Phase 3 | **Yes** | Never |
| `SUPABASE_URL` | Phase 3 | No | Yes (Realtime only) |
| `SUPABASE_ANON_KEY` | Phase 9 | No (by design) | Yes — but only for Realtime signal subscriptions |
| `SUPABASE_SERVICE_ROLE_KEY` | Phase 10 | **Yes** | Never |
| `FIREBASE_CREDENTIALS` (service account JSON) | Phase 11 | **Yes** | Never |
| `APP_KEY` | Phase 3 | **Yes** | Never |
| `API_BASE_URL` (Flutter) | Phase 6 | No | Yes — compiled into the app |
| `google-services.json` | Phase 11 | No | Yes — bundled into the Android app |

---

## 5. Final Database Schema (21 Tables)

**Identity & Roles:** `users`, `customers`, `customer_addresses`, `drivers`
**Catalog & Inventory:** `products`, `inventory`
**Orders & Payments:** `orders`, `order_items`, `payments`
**Credit:** `credit_accounts`, `credit_transactions`
**Deliveries:** `deliveries`, `delivery_status_history`
**RFID/Gallons:** `gallons`, `rfid_tags`, `rfid_transactions`
**Complaints/FAQ/Notifications:** `complaint_categories`, `complaints`, `complaint_history`, `faq_categories`, `faqs`, `notifications`
**Maintenance/Audit:** `maintenance`, `maintenance_records`, `audit_logs`

Plus one infrastructure table added in Phase 9: `realtime_events` (the Realtime signal table).

---

## 6. Known Limitations (Consolidated from Phase 16)

| Item | Status | Reason |
|---|---|---|
| GCash / card payments | Not implemented | Needs real payment-gateway merchant credentials |
| Real RFID hardware | Simulated via QR scan | No physical reader/SDK available |
| Full Admin Orders/Products modules | Minimal versions only | Scoped down to unblock dependent phases |
| `in_transit` gallon state | Schema supports it, unused | No route-optimization feature built |
| Password reset flow | Not implemented | Not in the original module list |
| Automated test suite (PHPUnit/Flutter test) | Manual test matrices only | This entire project was manual-QA-driven by design |

---

## 7. Final Integration Checklist

| Phase | Verified |
|---|---|
| 0 — Environment | Git, VS Code, Laragon/PHP/Composer, Flutter/Android Studio/Emulator, Postman all installed & verified |
| 1 — Structure & Git | `backend/` and `mobile/` under one repo; `main`/`develop`/`feature` branches in use |
| 2 — Database | 21 tables live in Supabase; RLS enabled; Realtime publication configured |
| 3 — Laravel ↔ Supabase | Migrations are canonical; models + relationships verified via Tinker |
| 4 — API & Auth | Sanctum working; role middleware enforced; no driver self-registration path exists |
| 5 — Admin Drivers Module | Admin-only driver creation; deactivate (not delete) working |
| 6 — Flutter Integration Layer | ApiClient, TokenStorage, AuthRepository proven with a real login round-trip |
| 7 — Customer Catalog/Cart/Checkout | Stock-aware ordering working end to end |
| 8 — Driver Deliveries | Assignment, status updates, and data isolation all verified |
| 9 — Realtime | Signal-table pattern live; no sensitive data ever broadcast over anon key |
| 10 — Storage | Proof photo required before Mark Delivered; private bucket confirmed inaccessible without a signed URL |
| 11 — FCM | Push notifications received in all three app states; payloads contain no PII |
| 12 — Payments & Credit | Cash and Credit both functional; credit-limit rule enforced |
| 13 — RFID/Gallon | QR-simulated scan-to-issue/return working; double-scan blocked |
| 14 — Complaints & FAQ | Complaint workflow + optional photo + notification on response, all working |
| 15 — Reports/Maintenance/Audit | Audit log populated by real actions; reports numerically verified |
| 16 — Integration & Security Testing | Full 24-step script and Security Test Checklist both passed |
| 17 — Deployment | Secrets rotated; HTTPS enforced; signed release build produced |

---

## 8. Closing Note

This guide took the Water Refilling Station Management System from an empty machine (Phase 0) to a deployed Laravel API, a live Supabase database with Realtime and Storage, push notifications, and a signed Android app serving both Customer and Driver roles (Phase 17). Every deferred item is documented above rather than hidden, and every security boundary introduced along the way was re-verified together in Phase 16 before deployment. From here, the system is ready for user acceptance testing and real-world use.

*Individual phase documents (Phase 0 – Phase 17), each with full step-by-step instructions, code, and test matrices, are available as separate Word files.*

---

# 9. 2026 Business-Flow Revision — Customer-Owned Gallon Refill + Station Operations

> **This section supersedes the earlier generic “product sale / stock decrement” interpretation for normal refill orders.**
> AQUA CLAYNE’s primary service flow is now defined as a **refill service using customer-owned empty gallons**. The station receives the customer’s own containers, refills them, and returns the same quantity to the customer.

## 9.1 Primary Business Model

A normal customer order represents:

```text
Customer requests water refill service
        +
Customer supplies empty gallon/container(s)
```

It does **not** normally mean:

```text
Customer purchases new station-owned gallon(s)
```

Example:

```text
Service: 5-Gallon Purified Water Refill
Quantity: 3
Container ownership: CUSTOMER_OWNED
Refill price: ₱50 each
Service total: ₱150
```

For customer-owned refill orders, AQUA CLAYNE charges for the refill service. The customer’s gallon itself is not deducted from station gallon inventory.

## 9.2 Revised End-to-End Order Flow

```text
CUSTOMER PLACES REFILL ORDER
        ↓
PENDING
        ↓
ADMIN REVIEWS / CONFIRMS
        ↓
CONFIRMED
        ↓
ADMIN ASSIGNS DRIVER
        ↓
ASSIGNED
        ↓
DRIVER GOES TO CUSTOMER
        ↓
DRIVER COLLECTS EMPTY CUSTOMER-OWNED GALLONS
        ↓
PICKED_UP
        ↓
DRIVER RETURNS GALLONS TO STATION
        ↓
STATION COUNTS / INSPECTS / ACCEPTS GALLONS
        ↓
RECEIVED_AT_STATION
        ↓
INTAKE VERIFIED
        ↓
STATION STAFF START REFILL
        ↓
REFILLING
        ↓
STATION FINISHES / SEALS GALLONS
        ↓
READY_FOR_DELIVERY
        ↓
DRIVER COLLECTS FILLED GALLONS
        ↓
OUT_FOR_DELIVERY
        ↓
CUSTOMER RECEIVES FILLED GALLONS
        ↓
DELIVERED
        ↓
PAYMENT + PROOF-OF-DELIVERY RULES VERIFIED
        ↓
COMPLETED
```

Exception states include:

```text
REJECTED
CANCELLED
PICKUP_FAILED
DELIVERY_FAILED
```

## 9.3 Station Refill Operations

After intake, the order remains at the station until staff start refilling it. The order status is the source of truth. Only one order may be actively refilled at a time.

## 9.4 Refill Start and Completion

Starting a refill requires finalized intake and at least one accepted gallon. A station-level database lock prevents concurrent active refills. Completing the refill advances the order to `READY_FOR_DELIVERY` and retains normal status-history and audit records.

## 9.5 Physical Gallon Intake Rule

When empty gallons arrive at the station, the expected quantity and actual received quantity must be reconciled.

Example:

```text
Expected empty gallons: 3
Received empty gallons: 3
Accepted for refill: 3
Damaged / rejected: 0
```

The order cannot enter `REFILLING` until intake is confirmed.

If counts differ, the station must resolve the discrepancy before refilling can begin.

## 9.6 Revised Product / Inventory Meaning

For the normal customer-owned refill workflow:

- `products` represent refill services or water offerings;
- the order quantity represents the number of gallons to refill;
- customer-owned gallons are not station sale inventory;
- station-owned gallons/RFID assets remain a separate asset-tracking concern;
- a refill order must not blindly decrement the `gallons` table.

If inventory is used for refill-service availability, it should track an appropriate consumable/capacity concept such as water/refill availability rather than treating the customer’s physical container as a sold unit.

## 9.7 RFID / Gallon Clarification

The existing RFID/Gallon module remains useful for **station-owned reusable gallons** or station assets.

Customer-owned containers in the primary refill workflow are handled through order intake counts. They do not automatically become station-owned RFID assets.

If the business later wants individual customer containers permanently tagged, that should be implemented as a separate customer-container feature rather than mixing ownership with station inventory.

## 9.8 Payment Completion Rule

For cash orders:

```text
Delivered
   +
Required proof of delivery
   +
Cash collection recorded
   ↓
Completed
```

For approved credit orders:

```text
Delivered
   +
Required proof of delivery
   +
Valid credit transaction
   ↓
Completed
```

The backend, not the UI, enforces these conditions.

## 9.9 Notification Events Added by This Revision

Customer notifications may now include:

```text
Order confirmed
Driver assigned for empty-gallon pickup
Empty gallons picked up
Gallons received at station
Station refill started
Refill started
Refill completed / ready for delivery
Out for delivery
Delivered
Payment recorded
Balance updated
```

The mobile UI displays order status from the backend and does not calculate station processing position.

## 9.10 Admin Web Additions

The Admin Web must include station refill operations showing:

- Orders received at the station
- Orders currently refilling
- Ready-for-delivery orders
- Customer
- Order number
- Quantity
- Received-at time
- Start Refill action
- Mark Refill Ready action

Recommended navigation:

```text
OPERATIONS
├── Orders
├── Station Refill Operations
├── Deliveries
├── Payments
└── Credit / Debt
```

## 9.11 Backend Revision Required

The backend implementation must add or adapt:

- customer-owned container metadata on orders;
- physical intake records;
- direct station refill workflow;
- revised order status transitions;
- intake-confirmation endpoint;
- start-refill endpoint;
- finish-refill endpoint;
- new realtime events;
- new notifications;
- audit logging for refill transitions;
- revised order creation logic so customer-owned gallons are not treated as station gallon sales.

The separate `AQUA_CLAYNE_Backend_Master_Specification.md` defines these backend changes in full.

---

## 10. Revised Canonical Order Statuses

The canonical normal refill-order lifecycle is:

```text
PENDING
CONFIRMED
ASSIGNED
PICKED_UP
RECEIVED_AT_STATION
REFILLING
READY_FOR_DELIVERY
OUT_FOR_DELIVERY
DELIVERED
COMPLETED
```

Terminal/exception states:

```text
REJECTED
CANCELLED
PICKUP_FAILED
DELIVERY_FAILED
```

Do not permit arbitrary status jumping. Valid transitions must be enforced by backend services.

---

## 11. Revision Note

Where an earlier phase says that checkout behaves like a normal product sale and directly decrements product inventory, interpret that behavior in light of this revision:

- the primary AQUA CLAYNE transaction is a **water refill service**;
- the customer normally supplies the gallon;
- the station receives, verifies, refills, and returns those customer-owned gallons;
- refilling begins only after physical station intake;
- station-owned gallon/RFID assets remain separately managed.

This revision should be used as the current business rule when continuing backend and frontend development.
