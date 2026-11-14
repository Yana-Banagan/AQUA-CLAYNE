# AQUA CLAYNE Admin-Side Audit

Reference: `Water_Refilling_System_Master_Documentation_UPDATED.md`.
This is a repository-level workflow summary; the configured production Supabase schema and migration state have not been independently verified.

| Feature | Frontend / Admin route | Supabase table(s) | Operation / related modules | Status |
|---|---|---|---|---|
| Admin authentication | `/admin/login`, `/admin/logout` | `users`, `sessions` | Session login, role/status validation, protected routes | ✅ Working |
| Dashboard | `/admin/dashboard` | orders, deliveries, station intake, customers, drivers | Aggregates live operational data | ✅ Working |
| Order review | `/admin/orders` | `orders`, `order_items`, `order_status_history` | Search/filter/detail and documented state transitions | ✅ Working |
| Driver assignment | `/admin/deliveries/assign` | orders, deliveries, delivery history | Assigns active driver, syncs order status, notifies driver | ⚠️ Fixed |
| Pickup through completion | Driver API + Admin operational views | orders, deliveries, intakes, payments | Enforced workflow, delivery proof and payment guards | ✅ Working |
| Physical intake | `/admin/orders/{order}/intake` | `order_container_intakes`, orders | Reconciles physical customer-owned containers before refilling | ✅ Working |
| Station refill operations | `/admin/orders/{order}/refill/*` | `order_container_intakes`, orders | Direct order-state transitions with a single-active-refill lock | ✅ Working |
| Payment management | `/admin/payments` | `payments`, `audit_logs` | Cash verification through transactional collection service | ⚠️ Fixed |
| Credit / debt | `/admin/credit`, customer credit pages | `credit_accounts`, `credit_transactions`, `audit_logs` | Live balances, limit enforcement, transactional repayment ledger | ⚠️ Fixed |
| Drivers | `/admin/drivers` | `users`, `drivers`, `audit_logs` | Admin-created accounts, deactivate/reactivate without deleting history | ⚠️ Fixed |
| Customers | `/admin/customers` | customers, users, orders, credit accounts, complaints | Profile, addresses, history and real credit balance | ⚠️ Fixed |
| Products / refill services | `/admin/products` | `products` | CRUD for active refill offerings | ✅ Working |
| Inventory / station gallons | `/admin/inventory` | `inventory`, `gallons`, `rfid_tags` | Read-only operational registry; customer-owned containers remain separate | ✅ Working |
| Reports | `/admin/reports` | orders, products, credit accounts, drivers | Revenue, order status, services, credit and driver data | ⚠️ Fixed |
| Complaints | `/admin/complaints` | complaints, complaint_history, notifications, audit_logs | Admin response, history, customer notification | ⚠️ Fixed |
| FAQ | `/admin/faq` | faq_categories, faqs | Create, update, delete content | ✅ Working |
| Admin notifications | `/admin/notifications` | notifications | Per-user list and ownership-protected read actions | ⚠️ Fixed |
| Maintenance | `/admin/maintenance` | maintenance, maintenance_records | Service record logging and due-date calculations | ✅ Working |
| Admin/staff accounts | `/admin/users` | users | Create and safely activate/deactivate staff | ✅ Working |
| Station settings | `/admin/settings` | No settings table in canonical migrations | Current controller presents static values and does not persist changes | ❌ Needs clarification |
| Customer complaint API | Flutter complaint screens | No matching API routes; schema has no complaint `order_id`/photo field | Mobile UI expects endpoints and fields absent from canonical backend schema | ❌ Needs clarification |

## Safety and workflow checks applied

- Admin web routes require authenticated active `admin` or `staff` accounts; API routes retain JSON authorization errors.
- Credit and cash operations lock the affected record, reject invalid duplicate/overpayment state, and write audit records.
- Container intake rejects unresolved count, damaged, or rejected-container discrepancies before refilling.
- Refill start validates intake and serializes active operations at the station.
- Complaint notifications persist even when Firebase credentials are absent; unexpected push-provider failures are reported rather than breaking the admin action.

## Explicit scope limits

- GCash/card, real RFID hardware, and password reset remain deferred by the master documentation.
- The settings and customer-complaint API entries above require a confirmed schema/API design before implementation; no tables, columns, or workflows were invented.
