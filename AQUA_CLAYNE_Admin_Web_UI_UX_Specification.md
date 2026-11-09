# AQUA CLAYNE — Admin Web Frontend / UI & UX Specification

> **Scope:** Admin Web only  
> **Excluded for now:** Customer Mobile and Driver Mobile frontend  
> **Design direction:** Modern admin dashboard inspired by the provided references — dark left sidebar, bright/light content area, rounded cards, soft shadows, clear data tables, status badges, compact filters, notification/profile dropdowns, and a violet-to-blue accent system adapted to AQUA CLAYNE.

---

# 1. Product Overview

The AQUA CLAYNE Admin Web is the central operational dashboard for managing the water refilling business. It allows authorized administrators to monitor sales, customers, drivers, products, orders, payments, balances, deliveries, RFID-tagged gallons, complaints, FAQ/chatbot content, notifications, reports, maintenance, and audit logs.

The interface should prioritize:

- fast operational decision-making;
- clear visibility of urgent items;
- minimal clicks for common admin actions;
- consistent layouts across modules;
- safe confirmation for destructive or sensitive actions;
- readable tables and detail views;
- responsive behavior for desktop and tablet;
- strong status visibility using badges, progress indicators, and activity timelines.

---

# 2. Overall Visual Direction

## 2.1 Design Style

Use a clean modern dashboard style inspired by the provided screenshots.

Core visual traits:

- Dark navy/purple vertical sidebar.
- White or very light gray main workspace.
- Rounded cards and containers.
- Soft, subtle shadows instead of hard borders.
- Large, bold page titles with short descriptions.
- Small colored icons inside rounded icon containers.
- Violet/purple as the primary interaction color.
- Aqua/blue accents to match the AQUA CLAYNE water business identity.
- Green for success/active/completed.
- Amber/orange for pending/warning.
- Red/pink for rejected/cancelled/failed/damaged.
- Light blue for informational states.
- Compact pill badges for status.
- Consistent 12–16 px border radius for cards, buttons, filters, dropdowns, and modal windows.

## 2.2 Suggested Color Tokens

```text
Primary Purple:       #7C3AED
Primary Purple Dark:  #5B21B6
Primary Purple Light: #EDE9FE

Aqua Blue:            #0EA5E9
Aqua Light:           #E0F2FE

Sidebar:              #17112F
Sidebar Hover:        #241A45
Sidebar Active:       #7C3AED

Background:           #F7F7FB
Surface:              #FFFFFF
Surface Soft:         #F4F2F8

Text Primary:         #151329
Text Secondary:       #77718B
Text Muted:           #A6A1B3

Success:              #10B981
Warning:              #F59E0B
Danger:               #F43F5E
Info:                 #0EA5E9
Border:               #E9E6F0
```

## 2.3 Typography

Recommended:

```text
Font Family:
Inter, Poppins, or Plus Jakarta Sans

Page Title:
28–32 px, 700–800 weight

Section Title:
18–22 px, 600–700 weight

Card Main Value:
28–32 px, 700–800 weight

Body Text:
14–16 px, 400–500 weight

Table Header:
12–14 px, 600 weight, uppercase optional

Small Metadata:
12–13 px, 400–500 weight
```

---

# 3. Global Admin Layout

The admin web should use one reusable global layout.

```text
┌───────────────────┬────────────────────────────────────────────┐
│ Left Sidebar      │ Top Header                                 │
│                   ├────────────────────────────────────────────┤
│                   │ Page Title / Breadcrumb / Actions          │
│                   │                                            │
│                   │ Main Content                               │
│                   │                                            │
│                   │ Cards / Tables / Charts / Forms            │
│                   │                                            │
└───────────────────┴────────────────────────────────────────────┘
```

---

# 4. Left Sidebar

## 4.1 Sidebar Behavior

Desktop:

- Fixed left sidebar.
- Width: approximately 260–280 px.
- Full height.
- Independently scrollable if menu exceeds viewport.
- Logo at top.
- Admin identity card fixed or sticky at bottom.
- Current module highlighted with a purple rounded background.
- Parent modules can expand/collapse into submenus.
- Collapse mode reduces sidebar to icons only.

Tablet:

- Sidebar collapses into drawer mode.

Mobile browser fallback:

- Sidebar becomes an off-canvas drawer.
- Admin web is primarily designed for desktop and tablet use.

## 4.2 AQUA CLAYNE Branding Area

Replace the sample “TrendyAdmin” brand with:

```text
AQUA CLAYNE
Water Refilling System
```

Suggested icon:

- water droplet;
- gallon;
- wave;
- simplified AQ monogram.

## 4.3 Sidebar Navigation Structure

```text
OVERVIEW
├── Dashboard

OPERATIONS
├── Customers
├── Drivers
├── Products & Inventory
├── Orders
├── Payments
├── Credit / Debt
├── Deliveries
├── RFID & Gallons

CUSTOMER SERVICE
├── Complaints
├── FAQ Chatbot
├── Notifications

ANALYTICS
├── Sales & Reports

SYSTEM
├── Maintenance
├── Audit Trail
├── Admin Profile
└── Settings
```

Each module may expand to show 2–6 relevant subpages where helpful.

---

# 5. Top Header

## 5.1 Header Content

The fixed or sticky header should contain:

Left side:

- sidebar toggle button;
- quick module launcher icon;
- global search field.

Right side:

- optional “Quick Create” button;
- theme toggle;
- full-screen toggle;
- activity/messages shortcut if used;
- notification bell with unread counter;
- admin avatar;
- admin name;
- dropdown caret.

## 5.2 Global Search

Placeholder:

```text
Search customers, orders, drivers, gallons...
```

The global search should be able to search:

- customer name;
- customer email/phone;
- order number;
- payment reference;
- driver name;
- RFID tag;
- gallon number;
- complaint number.

Search results should display grouped categories.

Example:

```text
Customers
  Maria Santos
  Juan Dela Cruz

Orders
  ORD-2026-00182
  ORD-2026-00179

Gallons
  GAL-00091
```

---

# 6. Admin Profile Dropdown

Clicking the admin profile should open a dropdown similar to the provided design.

Content:

```text
[Avatar]
Admin Name
admin@email.com
ADMINISTRATOR

My Profile
Change Password
Activity Log
Settings
----------------
Sign Out
```

The sign-out action should use a danger-tinted background on hover.

---

# 7. Notification Dropdown

Clicking the bell should open a compact notification panel.

Header:

```text
You have X new notifications
Mark all as read
```

Notification examples:

- New order received
- Order requires confirmation
- Payment waiting for verification
- Customer has outstanding balance
- Driver assigned
- Delivery delayed
- Gallon marked lost/damaged
- New complaint received
- Maintenance schedule due
- Low inventory alert

Each row should include:

- icon;
- title;
- short message;
- relative time;
- unread dot.

Footer:

```text
View all notifications
Notification settings
```

---

# 8. Global UX Standards

## 8.1 Page Header Pattern

Every module should start with:

```text
Page Title
Short description

[Primary Action] [Secondary Action]
```

Example:

```text
Customers
Manage customer accounts, balances, and order activity.

[Export] [Add Customer]
```

## 8.2 Data Table Pattern

All major management pages should use the same table conventions.

Table features:

- search;
- filter;
- sort;
- pagination;
- page size selector;
- row checkbox;
- bulk action when applicable;
- status badges;
- action menu;
- sticky table header for long tables.

Recommended action menu:

```text
View
Edit
View History
Activate / Deactivate
Delete
```

Only show actions allowed for the selected record and logged-in role.

## 8.3 Filter Bar Pattern

Typical filters:

```text
Search...
Status
Date Range
Category
Sort By
Reset
Apply Filter
```

For frequently used status filters, use pill tabs:

```text
All | Active | Inactive | Pending
```

## 8.4 Status Badges

Use consistent badge colors across the entire system.

```text
ACTIVE / COMPLETED / VERIFIED / SETTLED
Green

PENDING / WAITING / PARTIAL / PROCESSING
Amber

OUT FOR DELIVERY / ASSIGNED / INFO
Blue

REJECTED / CANCELLED / FAILED / DAMAGED / LOST
Red

INACTIVE / ARCHIVED
Gray
```

## 8.5 Empty States

Every page must have a friendly empty state.

Example:

```text
No orders found.
Orders will appear here once customers place an order.

[Refresh]
```

## 8.6 Loading States

Use:

- skeleton cards;
- skeleton table rows;
- inline spinner inside buttons during submission.

Never leave the page blank while loading.

## 8.7 Error States

Use contextual error messages.

Example:

```text
Unable to load orders.
Please check your connection and try again.

[Retry]
```

## 8.8 Toast Messages

Use small top-right or bottom-right toast notifications.

Examples:

```text
Customer updated successfully.
Driver assigned successfully.
Payment verified.
Order rejected.
Gallon marked as damaged.
```

---

# 9. Confirmation Dialogs

Dangerous or irreversible actions should require confirmation.

Examples:

- deactivate user;
- delete driver;
- delete FAQ;
- reject order;
- cancel order;
- mark gallon lost;
- mark gallon damaged;
- settle balance;
- sign out.

Example confirmation:

```text
Deactivate Customer?

This customer will no longer be able to place new orders.

[Cancel] [Deactivate]
```

Danger button should use red.

---

# 10. Dashboard

## 10.1 Dashboard Purpose

The dashboard should immediately answer:

- How much did we sell?
- How many orders are pending?
- What deliveries are active?
- Are any deliveries delayed?
- Is there any payment/credit issue?
- Is inventory running low?
- Are there urgent complaints or maintenance tasks?

## 10.2 Dashboard Hero Banner

Inspired by the sample gradient banner.

Suggested content:

```text
DASHBOARD

Good morning, Admin 👋
Here is what is happening at AQUA CLAYNE today.

[View Reports]
```

Right-side mini summary:

```text
Today’s Sales
New Orders
Active Deliveries
```

## 10.3 Dashboard KPI Cards

Required KPI cards:

```text
Total Sales
Total Orders
Pending Orders
Active Deliveries
Completed Deliveries
Total Customers
Total Drivers
```

Optional additional operational cards:

```text
Pending Payments
Outstanding Credit
Low Stock Products
Open Complaints
```

Each card should contain:

- icon;
- main number/value;
- label;
- small comparison percentage where useful;
- mini trend sparkline or visual indicator.

## 10.4 Sales Overview Chart

Chart types:

- line chart for sales over time;
- bar chart for orders;
- optional donut chart for order status.

Tabs:

```text
Week | Month | Year
```

## 10.5 Recent Orders Table

Columns:

```text
Order #
Customer
Product / Quantity
Amount
Payment
Order Status
Delivery Status
Date
Actions
```

## 10.6 Delivery Overview

Compact section showing:

```text
Pending Assignment
Assigned
Out for Delivery
Delayed
Completed Today
Failed Today
```

## 10.7 Inventory Alert Widget

Show top products that are low in stock.

Example:

```text
5-Gallon Purified Water
18 remaining
Low Stock
```

## 10.8 Live Activity Feed

Examples:

```text
New order received #ORD-2026-0012
Driver Mark assigned to #ORD-2026-0010
Payment verified for Maria Santos
Gallon GAL-0048 returned
Complaint CMP-0031 resolved
```

---

# 11. Admin Account & Role

Features covered:

- Admin Login/Logout
- Role-Based Access Control
- Admin Profile
- Password Management
- Audit Trail

## 11.1 Admin Login

Page layout:

```text
Left: Aqua Clayne branding / water-themed illustration
Right: Login card
```

Form:

```text
Email Address
Password
Remember Me
Forgot Password?
[Sign In]
```

UX:

- show/hide password;
- enter-key submission;
- validation messages;
- lock/disable after submission;
- show invalid credentials error;
- redirect authenticated admin to Dashboard.

## 11.2 Role-Based Access Control

Create a role/permission page if more than one admin role is supported.

Suggested roles:

```text
Super Admin
Operations Admin
Cashier / Payment Admin
Inventory Admin
Customer Service Admin
```

Permission categories:

```text
Customers
Drivers
Products
Orders
Payments
Credit
Deliveries
RFID/Gallons
Complaints
FAQ
Notifications
Reports
Maintenance
Audit
Settings
```

Use checkbox matrix.

## 11.3 Admin Profile

Fields:

```text
Profile Photo
Full Name
Email
Phone Number
Role
Account Status
Last Login
Created Date
```

Actions:

```text
Edit Profile
Change Password
```

## 11.4 Change Password

Fields:

```text
Current Password
New Password
Confirm New Password
```

Include password strength hint.

---

# 12. Customer Management

Features covered:

- View Customer List
- Search/Filter Customers
- View Customer Profile
- Edit Customer Information
- Activate/Deactivate Customer
- View Customer Order History
- View Customer Payment History
- View Customer Credit/Debt

## 12.1 Customer List

Page header:

```text
Customers
Manage customer accounts, balances, and transaction history.

[Export Customers]
```

Summary cards:

```text
Total Customers
Active
Inactive
Customers With Balance
```

Tabs:

```text
All
Active
Inactive
With Balance
```

Table columns:

```text
Customer
Phone
Address
Account Status
Orders
Balance
Last Order
Joined
Actions
```

## 12.2 Customer Profile

Use a two-column layout.

Left profile card:

```text
Avatar
Full Name
Email
Phone
Status
Joined Date
```

Main content tabs:

```text
Overview
Addresses
Orders
Payments
Credit / Debt
Activity
```

Overview cards:

```text
Total Orders
Completed Orders
Total Spent
Outstanding Balance
```

## 12.3 Edit Customer

Editable fields:

```text
Full Name
Email
Phone
Delivery Address
Account Status
```

The admin should not directly edit:

- customer password;
- customer role.

## 12.4 Activate / Deactivate

Require confirmation.

When inactive:

- customer can be prevented from placing new orders;
- profile and historical records remain visible.

---

# 13. Driver Management

Features covered:

- Add Driver
- Edit Driver
- Delete Driver
- View Driver Profile
- View Driver Delivery History
- View Driver Performance

## 13.1 Driver List

Summary cards:

```text
Total Drivers
Active
Available
On Delivery
Inactive
```

Table:

```text
Driver
Phone
Status
Availability
Active Delivery
Completed Today
Performance
Actions
```

## 13.2 Add Driver

Primary button:

```text
+ Add Driver
```

Form:

```text
Full Name
Email
Phone
Temporary Password
License / ID Reference (optional, if needed)
Status
```

System rule:

> Driver accounts can only be created by an authorized admin.

When created:

```text
Role = DRIVER
Status = ACTIVE
```

Customer-created accounts must never be able to set themselves as DRIVER.

## 13.3 Driver Profile

Tabs:

```text
Overview
Current Delivery
Delivery History
Performance
Activity
```

Performance metrics:

```text
Total Deliveries
Completed
Failed
Average Delivery Time
On-Time Rate
Cash Collected
```

## 13.4 Driver Deletion

Prefer soft-delete/deactivate over permanent deletion if the driver already has delivery history.

---

# 14. Product & Inventory Management

Features covered:

- Add Water Product
- Edit Product
- Delete/Deactivate Product
- Set Product Price
- Set Product Availability
- Monitor Product Inventory

## 14.1 Product List

View toggle:

```text
Table | Cards
```

Product card content:

```text
Product Image
Product Name
Size / Type
Price
Stock
Availability
Status
```

Table columns:

```text
Product
Category
Price
Inventory
Availability
Status
Updated
Actions
```

## 14.2 Add / Edit Product Form

Fields:

```text
Product Name
Description
Product Type
Container Size
Price
Product Image
Stock Quantity
Low-Stock Threshold
Availability
Status
```

## 14.3 Inventory Indicators

Use:

```text
In Stock
Low Stock
Out of Stock
```

Progress indicator:

```text
Stock: 18 / 100
```

Low stock should appear on:

- dashboard;
- product list;
- notification center.

---

# 15. Order Management

Features covered:

- View All Orders
- View Order Details
- Confirm Order
- Reject Order
- Cancel Order
- Update Order Status
- Search/Filter Orders
- View Order History

## 15.1 Order List

Summary cards:

```text
Total Orders
Pending
Confirmed
Out for Delivery
Completed
Cancelled / Rejected
```

Tabs:

```text
All
Pending
Confirmed
Processing
Out for Delivery
Completed
Cancelled
Rejected
```

Filters:

```text
Order Number
Customer
Status
Payment Status
Date Range
Driver
```

Table:

```text
Order #
Customer
Items
Total
Payment
Order Status
Delivery
Driver
Created
Actions
```

## 15.2 Order Detail

Header:

```text
Order #ORD-2026-00182
[Confirm] [Reject] [Cancel] [Print]
```

Sections:

```text
Customer Information
Delivery Address
Order Items
Order Notes
Payment Summary
Credit / Balance
Delivery Information
Status Timeline
```

Order item table:

```text
Product
Qty
Unit Price
Subtotal
```

## 15.3 Order Timeline

Example:

```text
Order Placed
Confirmed
Assigned to Driver
Out for Delivery
Delivered
Completed
```

Display date/time and admin/driver responsible.

## 15.4 Confirm Order

Confirmation modal should show:

```text
Confirm this order?
Check stock availability and payment details before confirming.
```

## 15.5 Reject Order

Require a rejection reason:

```text
Out of stock
Invalid address
Customer request
Payment issue
Other
```

## 15.6 Cancel Order

Require reason and show whether cancellation is allowed based on current status.

---

# 16. Payment Management

Features covered:

- Record Payment
- View Payment Records
- Verify Payment
- Payment History
- Payment Status
- Search/Filter Payments

## 16.1 Payment List

Summary cards:

```text
Total Collected
Pending Verification
Verified
Partial Payments
Failed / Rejected
```

Filters:

```text
Payment Reference
Order Number
Customer
Method
Status
Date Range
```

Table:

```text
Payment #
Order #
Customer
Method
Amount
Status
Recorded By
Date
Actions
```

## 16.2 Payment Detail

Sections:

```text
Payment Information
Customer
Related Order
Amount Due
Amount Paid
Remaining Balance
Payment Method
Proof / Reference
Verification History
```

Actions:

```text
Verify Payment
Reject Payment
Edit Record
Print Receipt
```

## 16.3 Record Payment

Form:

```text
Customer
Order
Amount Due
Amount Collected
Payment Method
Reference Number
Notes
Date / Time
```

When partial:

```text
Remaining Balance = Amount Due - Amount Collected
```

---

# 17. Credit / Debt Management

Features covered:

- View Customer Balance
- Record Partial Payment
- View Credit History
- Mark Balance as Settled

## 17.1 Credit Dashboard

Summary cards:

```text
Total Outstanding
Customers With Balance
Overdue Accounts
Settled This Month
```

Table:

```text
Customer
Order #
Original Amount
Paid
Remaining
Last Payment
Status
Actions
```

Status:

```text
Open
Partial
Settled
Overdue
```

## 17.2 Credit Detail

Show:

```text
Original Order Amount
Payments Made
Remaining Balance
Transaction Timeline
```

Actions:

```text
Record Partial Payment
Mark as Settled
View Order
```

Marking as settled should require confirmation.

---

# 18. Delivery Management

Features covered:

- View Pending Deliveries
- Assign Driver
- Reassign Driver
- View Active Deliveries
- View Delivery Status
- Set/Update ETA
- View Delayed Deliveries
- View Completed Deliveries
- View Failed Deliveries

## 18.1 Delivery Dashboard

Cards:

```text
Pending Assignment
Assigned
Out for Delivery
Delayed
Completed Today
Failed
```

Tabs:

```text
Pending
Assigned
Active
Delayed
Completed
Failed
```

Table:

```text
Delivery #
Order #
Customer
Address
Driver
Status
ETA
Last Update
Actions
```

## 18.2 Assign Driver Modal

Show order information at top.

Form:

```text
Driver
Driver Availability
Current Active Deliveries
Estimated ETA
Notes
```

Driver options should indicate:

```text
Available
Busy
Offline / Inactive
```

Primary button:

```text
Assign Driver
```

## 18.3 Reassign Driver

Show current driver and reason field.

```text
Current Driver
New Driver
Reason for Reassignment
```

## 18.4 Delivery Detail

Sections:

```text
Customer
Address
Driver
ETA
Delivery Status
Payment Due
Gallons Assigned
Proof of Delivery
Activity Timeline
```

## 18.5 ETA Update

Quick action:

```text
Set ETA
Update ETA
```

When ETA changes, the system should make it clear that the customer may receive a notification.

## 18.6 Delayed Delivery

Delayed delivery card/row should be visually emphasized with warning colors.

---

# 19. RFID & Gallon Management

Features covered:

- Register Gallon
- Register RFID Tag
- Link RFID → Gallon
- View Gallon Information
- View Gallon Status
- View RFID/Gallon History
- Mark Gallon as Returned
- Mark Gallon as Damaged
- Mark Gallon as Lost
- View RFID Transactions

## 19.1 RFID / Gallon Dashboard

Summary:

```text
Total Gallons
Available
In Delivery
With Customers
Returned Today
Damaged
Lost
Unlinked RFID
```

## 19.2 Gallon List

Columns:

```text
Gallon ID
RFID Tag
Type / Size
Current Status
Customer / Location
Last Scan
Last Transaction
Actions
```

## 19.3 Gallon Statuses

Suggested:

```text
Available
Assigned
In Transit
With Customer
Returned
Damaged
Lost
Inactive
```

## 19.4 Register Gallon

Form:

```text
Gallon ID
Type
Size
Purchase / Registration Date
Initial Status
Notes
```

## 19.5 Register RFID

Form:

```text
RFID Tag ID
Tag Type
Status
Notes
```

## 19.6 Link RFID to Gallon

Use a two-column selector:

```text
Select RFID Tag
Select Gallon
[Link]
```

Display a warning if either is already linked.

## 19.7 Gallon Detail

Sections:

```text
Gallon Information
RFID Information
Current Status
Current Location / Customer
Transaction History
Delivery History
Condition History
```

Actions:

```text
Mark Returned
Mark Damaged
Mark Lost
Unlink RFID
```

Damage/lost actions require reason and confirmation.

## 19.8 RFID Transactions

Table:

```text
Timestamp
RFID
Gallon
Event
Order / Delivery
Customer
Processed By
```

---

# 20. Complaint Management

Features covered:

- View Complaints
- Categorize Complaints
- Assign Complaint
- Respond to Complaint
- Update Complaint Status
- View Complaint History

## 20.1 Complaint Dashboard

Cards:

```text
Open
Assigned
Waiting for Customer
Resolved
Overdue
```

Tabs:

```text
All
Open
Assigned
In Progress
Resolved
Closed
```

Table:

```text
Complaint #
Customer
Category
Subject
Priority
Assigned To
Status
Created
Actions
```

## 20.2 Complaint Detail

Layout:

Left:

```text
Customer
Order / Delivery Reference
Category
Priority
Created Date
Attachments
```

Right:

```text
Conversation / Response Thread
```

Bottom response area:

```text
Reply text box
Attachment optional
[Send Response]
```

## 20.3 Complaint Status

Suggested:

```text
Open
Assigned
In Progress
Waiting for Customer
Resolved
Closed
```

---

# 21. FAQ Chatbot Management

Features covered:

- Add FAQ
- Edit FAQ
- Delete FAQ
- Enable/Disable FAQ
- Categorize FAQ
- Add Keywords
- Configure Auto-Reply
- Configure Fallback Response
- View Frequently Asked Questions

## 21.1 FAQ List

Cards or table:

```text
Question
Category
Keywords
Status
Usage Count
Last Updated
Actions
```

Tabs:

```text
All
Enabled
Disabled
Most Used
```

## 21.2 Add / Edit FAQ

Fields:

```text
Question
Answer
Category
Keywords / Triggers
Auto-Reply Enabled
Status
```

## 21.3 Keyword Management

Use tag chips:

```text
price
delivery fee
operating hours
cancel order
```

## 21.4 Fallback Response

Settings card:

```text
Default Fallback Response
Response when no FAQ matches
```

Example:

```text
I’m sorry, I could not find an exact answer. Please contact AQUA CLAYNE support or submit a complaint.
```

## 21.5 FAQ Analytics

Show:

```text
Most Asked Questions
Unmatched Questions
Top Keywords
Total Chatbot Queries
```

---

# 22. Customer Notifications

Features covered:

- Order Confirmation
- Order Status Notification
- Driver Assignment Notification
- ETA Notification
- Delivery Completion Notification
- Payment Notification
- Credit/Balance Notification
- System Announcements

## 22.1 Notification Center

Tabs:

```text
All
Orders
Deliveries
Payments
Credit
Announcements
```

Table:

```text
Notification
Recipient
Type
Channel
Status
Sent At
Actions
```

## 22.2 Notification Template Management

Templates:

```text
Order Confirmed
Order Rejected
Driver Assigned
Out for Delivery
ETA Updated
Delivered
Payment Recorded
Balance Updated
```

Each template should allow placeholders:

```text
{customer_name}
{order_number}
{driver_name}
{eta}
{amount}
{remaining_balance}
```

## 22.3 System Announcement

Form:

```text
Title
Message
Audience
Start Date
End Date
Priority
[Send / Schedule]
```

Audience:

```text
All Customers
Selected Customers
Customers With Balance
```

---

# 23. Sales & Reports

Features covered:

- Daily Sales Report
- Weekly Sales Report
- Monthly Sales Report
- Custom Date Range
- Order Report
- Payment Report
- Credit/Debt Report
- Delivery Report
- Customer Report
- Driver Performance Report
- Gallon/RFID Report
- Inventory Report
- Maintenance Report

## 23.1 Reports Landing Page

Report cards:

```text
Sales
Orders
Payments
Credit / Debt
Deliveries
Customers
Drivers
RFID / Gallons
Inventory
Maintenance
```

## 23.2 Report Filters

Standard filter bar:

```text
Today
This Week
This Month
Custom Range
```

Additional filters as needed:

```text
Product
Customer
Driver
Status
Payment Method
```

## 23.3 Report Visuals

Use charts where useful:

- sales line chart;
- orders bar chart;
- payment method donut chart;
- delivery completion trend;
- driver performance ranking;
- inventory movement chart.

## 23.4 Report Table

Every report should also provide detailed table data.

Actions:

```text
Export CSV
Export Excel
Export PDF
Print
```

---

# 24. Maintenance Management

The feature document includes maintenance reporting and maintenance service concepts, so the admin frontend should include a maintenance module.

## 24.1 Maintenance Dashboard

Cards:

```text
Equipment
Scheduled Maintenance
Due Soon
Overdue
Completed This Month
```

## 24.2 Equipment List

Table:

```text
Equipment
Category
Status
Last Maintenance
Next Maintenance
Assigned Technician
Actions
```

## 24.3 Maintenance Record

Fields:

```text
Equipment
Maintenance Type
Scheduled Date
Completed Date
Performed By
Description
Cost
Status
Notes
Attachment
```

Statuses:

```text
Scheduled
In Progress
Completed
Overdue
Cancelled
```

---

# 25. Audit Trail

Audit trail should be read-only for normal admins.

Filters:

```text
Admin / User
Module
Action
Date Range
IP Address
```

Table:

```text
Date / Time
Actor
Role
Module
Action
Record
Description
IP Address
```

Examples:

```text
Admin confirmed order ORD-00182
Admin changed driver assignment
Admin verified payment PAY-00021
Admin changed product price
Admin marked gallon GAL-0048 as lost
Admin updated complaint status
```

Audit log details should show:

```text
Before
After
```

where technically available.

---

# 26. Settings

Settings page may contain:

```text
Business Information
Operating Hours
Contact Information
Delivery Settings
Order Settings
Payment Settings
Credit Settings
Notification Settings
FAQ / Chatbot Settings
Security Settings
```

## 26.1 Business Information

Fields:

```text
Business Name
Business Address
Phone
Email
Logo
Operating Hours
```

## 26.2 Order Settings

Examples:

```text
Allow Customer Cancellation
Cancellation Time Limit
Minimum Order
```

## 26.3 Delivery Settings

Examples:

```text
Default Delivery ETA
Delivery Area
Delivery Fee
```

---

# 27. Quick Create Button

The purple `+ Create` button in the top header should open a dropdown.

Suggested quick actions:

```text
New Order
Add Customer
Add Driver
Add Product
Record Payment
Register Gallon
Register RFID
Create Announcement
```

Only show actions allowed by the user’s permissions.

---

# 28. Page-Level Breadcrumbs

Use breadcrumbs on deep pages.

Example:

```text
Orders / ORD-2026-00182
Customers / Maria Santos
Drivers / Mark Reyes
RFID & Gallons / GAL-0048
```

---

# 29. Modal Standards

Recommended modal sizes:

```text
Small: confirmation
Medium: simple form
Large: detailed assignment / payment / complaint action
```

Modal anatomy:

```text
Title
Short explanation
Form / content
Footer actions
```

Footer:

```text
[Cancel] [Primary Action]
```

---

# 30. Forms UX

All forms must provide:

- clear labels;
- required field indicators;
- inline validation;
- helper text when needed;
- disabled submit while saving;
- visible success/error feedback;
- consistent spacing;
- no placeholder-only labels.

Example:

```text
Customer Phone *
[09XXXXXXXXX]
Must be a valid Philippine mobile number.
```

---

# 31. Responsive Behavior

Primary target:

```text
1440 px and above
1366 px laptop
1024 px tablet landscape
```

Desktop:

- full sidebar;
- 3–4 KPI cards per row;
- full tables.

Tablet:

- collapsible sidebar;
- 2 KPI cards per row;
- horizontal scrolling for large tables.

Small width:

- cards stack;
- tables become horizontally scrollable;
- filters collapse into “Filters” drawer.

---

# 32. Accessibility

Minimum requirements:

- keyboard-accessible navigation;
- visible focus indicators;
- semantic labels;
- sufficient text contrast;
- do not rely on color alone for status;
- buttons include text or accessible labels;
- forms include error descriptions;
- modal focus trapping;
- escape key closes non-destructive modals;
- icons include tooltip where meaning is not obvious.

---

# 33. Security-Oriented UI Rules

The frontend must never trust user-modified hidden fields for authorization.

Frontend rules should support, but not replace, backend authorization.

Important UI restrictions:

- only admins with proper permission may add drivers;
- customers cannot create driver accounts;
- delete/deactivate actions require confirmation;
- payment verification requires explicit action;
- settled balance updates require confirmation;
- audit trail entries are not editable;
- sensitive record changes should be logged;
- sign-out should invalidate the active session.

---

# 34. Standard Status Vocabulary

Use the same status labels everywhere.

## Orders

```text
Pending
Confirmed
Processing
Assigned
Out for Delivery
Delivered
Completed
Rejected
Cancelled
```

## Payments

```text
Pending
Verified
Partial
Paid
Rejected
Failed
```

## Deliveries

```text
Pending Assignment
Assigned
Out for Delivery
Delayed
Delivered
Completed
Failed
Cancelled
```

## Customers

```text
Active
Inactive
```

## Drivers

```text
Active
Inactive
Available
Busy
```

## Gallons

```text
Available
Assigned
In Transit
With Customer
Returned
Damaged
Lost
Inactive
```

## Complaints

```text
Open
Assigned
In Progress
Waiting for Customer
Resolved
Closed
```

---

# 35. Suggested Laravel Blade View Structure

The frontend can be organized like this:

```text
resources/views/
│
├── layouts/
│   └── admin.blade.php
│
├── components/
│   ├── sidebar.blade.php
│   ├── topbar.blade.php
│   ├── page-header.blade.php
│   ├── stat-card.blade.php
│   ├── status-badge.blade.php
│   ├── data-table.blade.php
│   ├── filter-bar.blade.php
│   ├── modal.blade.php
│   ├── toast.blade.php
│   ├── empty-state.blade.php
│   └── pagination.blade.php
│
├── admin/
│   ├── dashboard/
│   ├── customers/
│   ├── drivers/
│   ├── products/
│   ├── orders/
│   ├── payments/
│   ├── credits/
│   ├── deliveries/
│   ├── gallons/
│   ├── complaints/
│   ├── faqs/
│   ├── notifications/
│   ├── reports/
│   ├── maintenance/
│   ├── audit/
│   ├── profile/
│   └── settings/
│
└── auth/
    ├── login.blade.php
    └── forgot-password.blade.php
```

---

# 36. Suggested Frontend Asset Structure

```text
resources/
├── css/
│   ├── admin.css
│   ├── components.css
│   └── utilities.css
│
├── js/
│   ├── admin.js
│   ├── sidebar.js
│   ├── dropdowns.js
│   ├── modals.js
│   ├── tables.js
│   └── charts.js
│
└── views/
```

If Tailwind CSS is used, keep most styling in reusable utility/component classes and avoid duplicating long class strings across every page.

---

# 37. Reusable UI Components

Build these first:

```text
AdminLayout
Sidebar
TopBar
Breadcrumb
PageHeader
PrimaryButton
SecondaryButton
DangerButton
IconButton
SearchInput
FilterSelect
DateRangePicker
StatusBadge
StatCard
ChartCard
DataTable
Pagination
Dropdown
NotificationPanel
ProfileMenu
Tabs
Modal
ConfirmDialog
Drawer
Toast
EmptyState
LoadingSkeleton
Timeline
Avatar
Tooltip
```

---

# 38. UX Priorities by Module

## High-Frequency Admin Tasks

These should require the fewest clicks:

```text
Confirm order
Assign driver
Verify payment
Record partial payment
Update ETA
Mark delivery completed
Search customer
View outstanding balance
Register / update gallon
Respond to complaint
```

## Recommended Maximum Interaction Depth

Common actions:

```text
Dashboard → action
or
Module → row action
```

Avoid forcing the admin through many nested screens for routine operations.

---

# 39. Dashboard Navigation Shortcuts

Below the KPI cards, include optional quick action tiles:

```text
Confirm Pending Orders
Assign Pending Deliveries
Verify Payments
View Customer Balances
Check Low Inventory
Review Complaints
```

Each tile should display a count.

---

# 40. Desktop Screen Composition Example

```text
┌────────────────────────────────────────────────────────────────────────┐
│ Sidebar │ Topbar                                                      │
│         ├───────────────────────────────────────────────────────────────┤
│         │ Customers                                  [Export] [Add]    │
│         │ Manage customer accounts and transaction history.            │
│         │                                                               │
│         │ [Total] [Active] [Inactive] [With Balance]                  │
│         │                                                               │
│         │ [All] [Active] [Inactive]      [Search] [Filter]            │
│         │ ┌───────────────────────────────────────────────────────────┐ │
│         │ │ Customer │ Phone │ Status │ Orders │ Balance │ Actions │ │
│         │ ├───────────────────────────────────────────────────────────┤ │
│         │ │ ...                                                       │ │
│         │ └───────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────────┘
```

---

# 41. Admin Experience Flow

A typical admin session should feel like:

```text
Login
  ↓
Dashboard
  ↓
Review urgent counts
  ↓
Open pending order
  ↓
Confirm order
  ↓
Assign driver
  ↓
Verify payment if needed
  ↓
Monitor delivery
  ↓
Review completion
```

The dashboard should surface tasks that need attention instead of making the admin search manually.

---

# 42. Recommended Dashboard Alerts

Priority alerts:

```text
HIGH
- Failed delivery
- Lost gallon
- Payment inconsistency
- Overdue maintenance

MEDIUM
- Delayed delivery
- New complaint
- Outstanding customer balance
- Low inventory

LOW
- New customer
- Completed delivery
- FAQ usage update
```

---

# 43. Recommended Table Actions by Module

## Customers

```text
View
Edit
Orders
Payments
Credit
Activate / Deactivate
```

## Drivers

```text
View
Edit
Current Delivery
History
Performance
Deactivate
```

## Products

```text
View
Edit
Update Stock
Change Availability
Deactivate
```

## Orders

```text
View
Confirm
Reject
Cancel
Assign Driver
```

## Payments

```text
View
Verify
Reject
Print Receipt
```

## Deliveries

```text
View
Assign / Reassign
Update ETA
Update Status
```

## Gallons

```text
View
View Transactions
Mark Returned
Mark Damaged
Mark Lost
```

---

# 44. UI Text Tone

Use concise, professional system language.

Good:

```text
Driver assigned successfully.
Payment verified.
No delayed deliveries.
3 customers have outstanding balances.
```

Avoid:

```text
Awesome!
Oops!
Something went terribly wrong!
```

---

# 45. Recommended Final Admin Navigation

```text
AQUA CLAYNE

OVERVIEW
Dashboard

OPERATIONS
Customers
Drivers
Products & Inventory
Orders
Payments
Credit / Debt
Deliveries
RFID & Gallons

CUSTOMER SERVICE
Complaints
FAQ Chatbot
Notifications

ANALYTICS
Sales & Reports

SYSTEM
Maintenance
Audit Trail
Admin Profile
Settings
```

---

# 46. Feature Coverage Checklist

This admin UI/UX specification covers the requested Admin Web features:

```text
✓ Admin Login / Logout
✓ Role-Based Access Control
✓ Admin Profile
✓ Password Management
✓ Audit Trail

✓ Total Sales
✓ Total Orders
✓ Pending Orders
✓ Active Deliveries
✓ Completed Deliveries
✓ Total Customers
✓ Total Drivers

✓ Customer Management
✓ Product Management
✓ Order Management
✓ Payment Management
✓ Credit / Debt Management
✓ Driver Management
✓ Delivery Management
✓ RFID & Gallon Management
✓ Complaint Management
✓ FAQ Chatbot Management
✓ Customer Notifications
✓ Sales & Reports
✓ Maintenance
✓ Audit Service / Administrative Activity
```

---

# 47. Implementation Order Recommendation

Build the admin frontend in this order:

```text
1. Admin layout
2. Sidebar and topbar
3. Reusable UI components
4. Login
5. Dashboard
6. Customers
7. Drivers
8. Products & Inventory
9. Orders
10. Payments
11. Credit / Debt
12. Deliveries
13. RFID & Gallons
14. Complaints
15. FAQ Chatbot
16. Notifications
17. Reports
18. Maintenance
19. Audit Trail
20. Profile / Settings
```

This order ensures the design system is stable before the larger CRUD pages are implemented.

---

# 48. Final Design Principle

The AQUA CLAYNE admin panel should look visually polished like the reference dashboard, but the interface must prioritize actual water-refilling operations over decorative dashboard elements.

The final result should feel:

```text
Clean
Modern
Professional
Fast
Data-oriented
Easy to scan
Consistent
Safe for admin actions
Focused on operational workflow
```

Use the provided screenshots as the visual inspiration for layout, spacing, cards, sidebar, table design, notification menu, and profile dropdown, while adapting the content, labels, flows, and actions specifically to AQUA CLAYNE.
