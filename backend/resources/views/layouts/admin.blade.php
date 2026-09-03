
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        @yield('title', 'Aqua Clayne Admin')
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            background: #f5f7fa;
            color: #1f2937;
        }

        /* =========================================================
           NAVBAR
        ========================================================= */

        .admin-navbar {
            width: 100%;
            height: 64px;
            background: #2563eb;
            color: white;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 24px;
        }

        .admin-navbar h1 {
            margin: 0;
            font-size: 22px;
        }

        .admin-navbar .admin-user {
            font-size: 14px;
        }

        /* =========================================================
           LAYOUT
        ========================================================= */

        .admin-layout {
            display: flex;
            min-height: calc(100vh - 64px);
        }

        /* =========================================================
           SIDEBAR
        ========================================================= */

        .admin-sidebar {
            width: 230px;
            background: white;
            border-right: 1px solid #e5e7eb;

            padding: 20px;
        }

        .admin-sidebar a {
            display: block;

            padding: 12px 14px;
            margin-bottom: 6px;

            color: #374151;
            text-decoration: none;

            border-radius: 8px;
        }

        .admin-sidebar a:hover {
            background: #eff6ff;
            color: #2563eb;
        }

        /* =========================================================
           MAIN CONTENT
        ========================================================= */

        .admin-content {
            flex: 1;
            padding: 30px;
        }

        /* =========================================================
           TOAST
        ========================================================= */

        #admin-toast {
            position: fixed;

            top: 80px;
            right: 25px;

            background: #2563eb;
            color: white;

            padding: 14px 18px;

            border-radius: 8px;

            box-shadow:
                0 4px 12px rgba(0, 0, 0, 0.15);

            font-size: 14px;

            z-index: 9999;

            display: none;
        }

        #admin-toast.show {
            display: block;
        }

        /* =========================================================
           ORDER BADGE
        ========================================================= */

        .order-badge {
            display: inline-flex;

            min-width: 20px;
            height: 20px;

            align-items: center;
            justify-content: center;

            margin-left: 6px;

            padding: 0 6px;

            border-radius: 999px;

            background: #ef4444;
            color: white;

            font-size: 11px;
            font-weight: bold;
        }

        /* =========================================================
           MOBILE
        ========================================================= */

        @media (max-width: 768px) {

            .admin-sidebar {
                width: 180px;
            }

            .admin-content {
                padding: 20px;
            }

            .admin-navbar {
                padding: 0 15px;
            }

            .admin-navbar h1 {
                font-size: 18px;
            }
        }
    </style>

    @stack('styles')
</head>

<body>

    <!-- =========================================================
         ADMIN NAVBAR
    ========================================================= -->

    <header class="admin-navbar">

        <h1>
            Aqua Clayne
        </h1>

        <div class="admin-user">
            Admin
        </div>

    </header>


    <!-- =========================================================
         ADMIN LAYOUT
    ========================================================= -->

    <div class="admin-layout">

        <!-- =====================================================
             SIDEBAR
        ===================================================== -->

        <aside class="admin-sidebar">

            <a href="#">
                Dashboard
            </a>

            <a href="#">
                Orders
                <span
                    id="order-badge"
                    class="order-badge"
                    style="display: none;"
                >
                    0
                </span>
            </a>

            <a href="#">
                Products
            </a>

            <a href="#">
                Customers
            </a>

            <a href="#">
                Drivers
            </a>

            <a href="#">
                Deliveries
            </a>

        </aside>


        <!-- =====================================================
             MAIN CONTENT
        ===================================================== -->

        <main class="admin-content">

            @yield('content')

        </main>

    </div>


    <!-- =========================================================
         TOAST NOTIFICATION
    ========================================================= -->

    <div id="admin-toast"></div>


    <!-- =========================================================
         EXISTING PAGE SCRIPTS
    ========================================================= -->

    @stack('scripts')


    <!-- =========================================================
         SUPABASE JS
    ========================================================= -->

    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>


    <!-- =========================================================
         SUPABASE REALTIME
    ========================================================= -->

    <script>

        const supabase = window.supabase.createClient(
            "{{ config('services.supabase.url') }}",
            "{{ config('services.supabase.anon_key') }}"
        );


        // ========================================================
        // TOAST
        // ========================================================

        function showToast(message) {

            const toast =
                document.getElementById('admin-toast');

            if (!toast) {
                return;
            }

            toast.textContent = message;

            toast.classList.add('show');


            setTimeout(() => {

                toast.classList.remove('show');

            }, 5000);
        }

        function incrementOrderBadge() {
            const badge = document.getElementById('order-badge');

            if (!badge) {
                return;
            }

            const current = parseInt(badge.textContent || '0', 10);

            badge.textContent = current + 1;
        }
        // ========================================================
        // ORDER BADGE
        // ========================================================

        function incrementOrderBadge() {

            const badge =
                document.getElementById('order-badge');

            if (!badge) {
                return;
            }


            let current =
                parseInt(
                    badge.textContent || '0',
                    10
                );


            current++;


            badge.textContent = current;

            badge.style.display = 'inline-flex';
        }


        // ========================================================
        // ADMIN ORDER REALTIME CHANNEL
        // ========================================================

        supabase
            .channel('admin-order-signals')
            .on(
                'postgres_changes',
                {
                    event: 'INSERT',
                    schema: 'public',
                    table: 'realtime_events',
                    filter: 'entity_type=eq.order'
                },
                (payload) => {

                    console.log(
                        'Realtime order event:',
                        payload
                    );


                    const entityId =
                        payload.new.entity_id;


                    showToast(
                        'New order received (#' +
                        entityId.substring(0, 8) +
                        ')'
                    );


                    incrementOrderBadge();

                }
            )
            .subscribe((status) => {

                console.log(
                    'Admin Realtime status:',
                    status
                );

            });

    </script>

</body>
</html>
