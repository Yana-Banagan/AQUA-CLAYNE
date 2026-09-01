<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Assign Deliveries</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        h1 {
            margin: 0;
            font-size: 28px;
        }

        .back {
            text-decoration: none;
            color: #2563eb;
            font-weight: bold;
        }

        .message {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #dcfce7;
            color: #166534;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 21px;
        }

        .order {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 15px;
        }

        .order:last-child {
            margin-bottom: 0;
        }

        .order-info {
            margin-bottom: 15px;
        }

        .order-info p {
            margin: 6px 0;
        }

        .label {
            font-weight: bold;
        }

        form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        select {
            min-width: 250px;
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            background: white;
            font-size: 14px;
        }

        button {
            border: none;
            border-radius: 7px;
            padding: 11px 18px;
            background: #2563eb;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #6b7280;
        }

        .warning {
            padding: 15px;
            border-radius: 8px;
            background: #fef3c7;
            color: #92400e;
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {
            .header {
                align-items: flex-start;
                flex-direction: column;
                gap: 10px;
            }

            form {
                align-items: stretch;
                flex-direction: column;
            }

            select,
            button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Assign Deliveries</h1>

        <a
            href="{{ route('admin.dashboard') }}"
            class="back"
        >
            ← Back to Dashboard
        </a>
    </div>

    @if(session('success'))
        <div class="message">
            {{ session('success') }}
        </div>
    @endif

    @if($activeDrivers->isEmpty())
        <div class="warning">
            No active drivers are currently available.
            Please create a driver or activate an existing driver first.
        </div>
    @endif

    <div class="card">

        <h2>Pending Orders</h2>

        @if($pendingOrders->isEmpty())

            <div class="empty">
                No pending or confirmed orders are available for assignment.
            </div>

        @else

            @foreach($pendingOrders as $order)

                <div class="order">

                    <div class="order-info">

                        <p>
                            <span class="label">
                                Order ID:
                            </span>

                            {{ substr($order->id, 0, 8) }}...
                        </p>

                        <p>
                            <span class="label">
                                Customer:
                            </span>

                            {{ $order->customer?->user?->name ?? 'Unknown Customer' }}
                        </p>

                        <p>
                            <span class="label">
                                Status:
                            </span>

                            {{ ucfirst($order->status) }}
                        </p>

                    </div>

                    @if($activeDrivers->isNotEmpty())

                        <form
                            method="POST"
                            action="{{ route('admin.deliveries.assign.store') }}"
                        >

                            @csrf

                            <input
                                type="hidden"
                                name="order_id"
                                value="{{ $order->id }}"
                            >

                            <select
                                name="driver_id"
                                required
                            >

                                <option value="">
                                    Select active driver
                                </option>

                                @foreach($activeDrivers as $driver)

                                    <option value="{{ $driver->id }}">

                                        {{ $driver->user?->name ?? 'Unnamed Driver' }}

                                        @if($driver->vehicle_plate)
                                            — {{ $driver->vehicle_plate }}
                                        @endif

                                    </option>

                                @endforeach

                            </select>

                            <button type="submit">
                                Assign Driver
                            </button>

                        </form>

                    @else

                        <p>
                            No active drivers available.
                        </p>

                    @endif

                </div>

            @endforeach

        @endif

    </div>

</div>

</body>
</html>
