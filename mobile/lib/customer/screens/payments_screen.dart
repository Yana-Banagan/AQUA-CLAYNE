import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:mobile/core/utils/pht_datetime.dart';
import '../../core/models/payment_model.dart';
import '../../core/providers/auth_provider.dart';
import '../../customer/providers/notification_provider.dart';
import '../../customer/providers/payment_provider.dart';
import 'catalog_screen.dart';
import 'notifications_screen.dart';
import 'order_detail_screen.dart';

class PaymentsScreen extends StatefulWidget {
  final bool showStandaloneNavigation;
  const PaymentsScreen({super.key, this.showStandaloneNavigation = true});

  @override
  State<PaymentsScreen> createState() => _PaymentsScreenState();
}

class _PaymentsScreenState extends State<PaymentsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      if (!context.read<AuthProvider>().isAuthenticated) return;
      context.read<PaymentProvider>().loadPayments();
    });
  }

  @override
  Widget build(BuildContext context) {
    final canPop = widget.showStandaloneNavigation && Navigator.canPop(context);

    return Scaffold(
      body: Stack(
        children: [
          // ── 1. Full-screen background image ────────────────────────
          Positioned.fill(
            child: Image.asset(
              'assets/images/customer_dashboard_bg.png',
              fit: BoxFit.cover,
              alignment: Alignment.center,
            ),
          ),

          // ── 2. Scrollable payments content ─────────────────────────
          SafeArea(
            bottom: canPop,
            child: Column(
              children: [
                _buildHeader(context, canPop),
                Expanded(
                  child: Consumer<PaymentProvider>(
                    builder: (context, prov, _) {
                      final isGuest = !context.watch<AuthProvider>().isAuthenticated;
                      if (isGuest) {
                        return SingleChildScrollView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildTitleSection(),
                              const SizedBox(height: 14),
                              _buildSupportedMethodsNotice(),
                              const SizedBox(height: 20),
                              _buildPaymentHistoryHeader(),
                              const SizedBox(height: 14),
                              _buildEmptyState('ALL'),
                            ],
                          ),
                        );
                      }
                      if (prov.loading && prov.payments.isEmpty) {
                        return _buildLoadingSkeleton();
                      }

                      if (prov.error != null && prov.payments.isEmpty) {
                        return _buildErrorState(prov);
                      }

                      return RefreshIndicator(
                        color: const Color(0xFF0878C9),
                        backgroundColor: Colors.white,
                        onRefresh: () => context.read<AuthProvider>().isAuthenticated
                            ? prov.loadPayments()
                            : Future<void>.value(),
                        child: SingleChildScrollView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildTitleSection(),
                              const SizedBox(height: 14),
                              _buildPaymentSummaryCard(prov.summary),
                              const SizedBox(height: 12),
                              _buildSupportedMethodsNotice(),
                              const SizedBox(height: 20),
                              _buildPaymentHistoryHeader(),
                              const SizedBox(height: 10),
                              _buildFilterTabs(prov),
                              const SizedBox(height: 14),
                              _buildPaymentsList(prov),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // HEADER
  // ====================================================================
  Widget _buildHeader(BuildContext context, bool canPop) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          if (canPop)
            _buildCircularBackButton(context)
          else
            _buildBrandHeader(),
          _buildNotificationButton(context),
        ],
      ),
    );
  }

  Widget _buildCircularBackButton(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () => Navigator.of(context).pop(),
        borderRadius: BorderRadius.circular(21),
        child: Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            color: Colors.white,
            shape: BoxShape.circle,
            border: Border.all(color: const Color(0xFFD5E7F4), width: 1),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0878C9).withValues(alpha: 0.08),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: const Icon(
            Icons.arrow_back_rounded,
            color: Color(0xFF0878C9),
            size: 22,
          ),
        ),
      ),
    );
  }

  Widget _buildBrandHeader() {
    return Row(
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: const Color(0xFFEAF7FF),
            shape: BoxShape.circle,
            border: Border.all(color: const Color(0xFFBCE3FD), width: 1),
          ),
          child: ClipOval(
            child: Image.asset(
              'assets/images/aqua_clayne_logo.png',
              fit: BoxFit.cover,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: const [
            Text(
              'AQUA CLAYNE',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color: Color(0xFF064B87),
                letterSpacing: 0.5,
              ),
            ),
            Text(
              'Water Refilling Station',
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w500,
                color: Color(0xFF6F8BA3),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildNotificationButton(BuildContext context) {
    return Consumer<NotificationProvider>(
      builder: (context, notifProv, _) {
        final unreadCount = context.watch<AuthProvider>().isAuthenticated
            ? notifProv.unreadCount
            : 0;

        return Stack(
          clipBehavior: Clip.none,
          children: [
            Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: () {
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => const NotificationsScreen(),
                    ),
                  );
                },
                borderRadius: BorderRadius.circular(21),
                child: Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    border: Border.all(color: const Color(0xFFD5E7F4), width: 1),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF0878C9).withValues(alpha: 0.08),
                        blurRadius: 8,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: const Icon(
                    Icons.notifications_outlined,
                    color: Color(0xFF0878C9),
                    size: 22,
                  ),
                ),
              ),
            ),
            if (unreadCount > 0)
              Positioned(
                top: -2,
                right: -2,
                child: Container(
                  padding: const EdgeInsets.all(4),
                  decoration: const BoxDecoration(
                    color: Color(0xFFE53935),
                    shape: BoxShape.circle,
                  ),
                  constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                  child: Center(
                    child: Text(
                      unreadCount > 99 ? '99+' : '$unreadCount',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        height: 1,
                      ),
                    ),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }

  // ====================================================================
  // TITLE SECTION
  // ====================================================================
  Widget _buildTitleSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: const [
        Text(
          'Payments',
          style: TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.w800,
            color: Color(0xFF12344D),
            letterSpacing: -0.3,
          ),
        ),
        SizedBox(height: 3),
        Text(
          'Manage your water refill payments and balances',
          style: TextStyle(
            fontSize: 13,
            color: Color(0xFF6F8BA3),
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }

  // ====================================================================
  // PAYMENT SUMMARY CARD
  // ====================================================================
  Widget _buildPaymentSummaryCard(PaymentSummaryModel summary) {
    final isAllPaid = summary.allPaidUp || summary.outstandingBalance <= 0;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFE2F1FC), width: 1.5),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.08),
            blurRadius: 14,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      color: const Color(0xFFEAF7FF),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(
                      Icons.account_balance_wallet_rounded,
                      color: Color(0xFF0878C9),
                      size: 18,
                    ),
                  ),
                  const SizedBox(width: 8),
                  const Text(
                    'PAYMENT SUMMARY',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF064B87),
                      letterSpacing: 0.6,
                    ),
                  ),
                ],
              ),
              if (isAllPaid)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE8F8F0),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFBBE5D0), width: 1),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: const [
                      Icon(Icons.check_circle_rounded,
                          color: Color(0xFF128A47), size: 14),
                      SizedBox(width: 4),
                      Text(
                        'All Paid Up',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF128A47),
                        ),
                      ),
                    ],
                  ),
                )
              else
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEF5E7),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFFBD79F), width: 1),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: const [
                      Icon(Icons.access_time_rounded,
                          color: Color(0xFFC4710A), size: 14),
                      SizedBox(width: 4),
                      Text(
                        'Balance Due',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFFC4710A),
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
          const SizedBox(height: 14),
          const Text(
            'Outstanding Balance',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: Color(0xFF6F8BA3),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            '₱${summary.outstandingBalance.toStringAsFixed(2)}',
            style: TextStyle(
              fontSize: 32,
              fontWeight: FontWeight.w900,
              color: isAllPaid ? const Color(0xFF064B87) : const Color(0xFFC4710A),
              letterSpacing: -0.5,
            ),
          ),
          const SizedBox(height: 10),
          if (isAllPaid)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFFF3FAFE),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                children: const [
                  Icon(Icons.thumb_up_alt_outlined,
                      color: Color(0xFF0878C9), size: 16),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'You have no outstanding refill charges. Thank you!',
                      style: TextStyle(
                        fontSize: 12,
                        color: Color(0xFF12344D),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                ],
              ),
            )
          else ...[
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFFEF9F0),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFFCE6C7), width: 1),
              ),
              child: Column(
                children: [
                  if (summary.pendingCashTotal > 0)
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Cash Pending Collection:',
                          style: TextStyle(
                            fontSize: 12,
                            color: Color(0xFF6F8BA3),
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        Text(
                          '₱${summary.pendingCashTotal.toStringAsFixed(2)}',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF12344D),
                          ),
                        ),
                      ],
                    ),
                  if (summary.pendingCashTotal > 0 && summary.creditBalance > 0)
                    const SizedBox(height: 6),
                  if (summary.creditBalance > 0)
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Used Credit Balance:',
                          style: TextStyle(
                            fontSize: 12,
                            color: Color(0xFF6F8BA3),
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        Text(
                          '₱${summary.creditBalance.toStringAsFixed(2)}',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF12344D),
                          ),
                        ),
                      ],
                    ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }



  // ====================================================================
  // SUPPORTED METHODS NOTICE
  // ====================================================================
  Widget _buildSupportedMethodsNotice() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.85),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2F1FC), width: 1),
      ),
      child: Row(
        children: const [
          Icon(Icons.info_outline_rounded, color: Color(0xFF0878C9), size: 16),
          SizedBox(width: 8),
          Expanded(
            child: Text(
              'Supported: Cash upon delivery & Station Credit. Online card/GCash gateways are deferred.',
              style: TextStyle(
                fontSize: 11,
                color: Color(0xFF6F8BA3),
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // PAYMENT HISTORY HEADER & FILTERS
  // ====================================================================
  Widget _buildPaymentHistoryHeader() {
    return const Text(
      'Payment History',
      style: TextStyle(
        fontSize: 18,
        fontWeight: FontWeight.w800,
        color: Color(0xFF12344D),
        letterSpacing: -0.2,
      ),
    );
  }

  Widget _buildFilterTabs(PaymentProvider prov) {
    final filters = [
      {'key': 'ALL', 'label': 'All', 'count': prov.allCount},
      {'key': 'PAID', 'label': 'Paid', 'count': prov.paidCount},
      {'key': 'PENDING', 'label': 'Pending', 'count': prov.pendingCount},
    ];

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      physics: const BouncingScrollPhysics(),
      child: Row(
        children: filters.map((f) {
          final isSelected = prov.selectedFilter == f['key'];
          final count = f['count'] as int;

          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: () => prov.setFilter(f['key'] as String),
                borderRadius: BorderRadius.circular(20),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  decoration: BoxDecoration(
                    gradient: isSelected
                        ? const LinearGradient(
                            colors: [Color(0xFF064B87), Color(0xFF0878C9)],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          )
                        : null,
                    color: isSelected ? null : Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: isSelected
                          ? Colors.transparent
                          : const Color(0xFFE2F1FC),
                      width: 1.2,
                    ),
                    boxShadow: isSelected
                        ? [
                            BoxShadow(
                              color: const Color(0xFF0878C9)
                                  .withValues(alpha: 0.25),
                              blurRadius: 8,
                              offset: const Offset(0, 3),
                            ),
                          ]
                        : null,
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        f['label'] as String,
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight:
                              isSelected ? FontWeight.bold : FontWeight.w600,
                          color: isSelected
                              ? Colors.white
                              : const Color(0xFF6F8BA3),
                        ),
                      ),
                      const SizedBox(width: 6),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 6, vertical: 1.5),
                        decoration: BoxDecoration(
                          color: isSelected
                              ? Colors.white.withValues(alpha: 0.25)
                              : const Color(0xFFEAF7FF),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          '$count',
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            color: isSelected
                                ? Colors.white
                                : const Color(0xFF0878C9),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  // ====================================================================
  // PAYMENTS LIST
  // ====================================================================
  Widget _buildPaymentsList(PaymentProvider prov) {
    final payments = prov.filteredPayments;

    if (payments.isEmpty) {
      return _buildEmptyState(prov.selectedFilter);
    }

    return ListView.separated(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: payments.length,
      separatorBuilder: (context, index) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        return _buildPaymentCard(context, payments[index]);
      },
    );
  }

  Widget _buildPaymentCard(BuildContext context, PaymentModel payment) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () => _showPaymentDetailsDialog(context, payment),
        borderRadius: BorderRadius.circular(20),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFE2F1FC), width: 1.2),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                blurRadius: 10,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Top row: Ref # and Status Badge
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF0F7FC),
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(
                              color: const Color(0xFFD3E7F6), width: 0.8),
                        ),
                        child: Text(
                          payment.referenceNumber,
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF064B87),
                          ),
                        ),
                      ),
                      const SizedBox(width: 6),
                      _buildMethodPill(payment.method),
                    ],
                  ),
                  _buildStatusBadge(payment),
                ],
              ),
              const SizedBox(height: 10),

              // Associated Order
              if (payment.orderNumber != null)
                Row(
                  children: [
                    const Icon(Icons.receipt_long_rounded,
                        color: Color(0xFF0878C9), size: 14),
                    const SizedBox(width: 5),
                    Text(
                      'Order #${payment.orderNumber}',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF12344D),
                      ),
                    ),
                  ],
                ),
              const SizedBox(height: 4),

              // Service Description & Container Model
              Text(
                payment.serviceDescription,
                style: const TextStyle(
                  fontSize: 12,
                  color: Color(0xFF6F8BA3),
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 12),

              // Bottom Row: Date/Time + Amount + View Details CTA
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Total Amount',
                        style: TextStyle(
                          fontSize: 11,
                          color: Color(0xFF6F8BA3),
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '₱${payment.amount.toStringAsFixed(2)}',
                        style: const TextStyle(
                          fontSize: 19,
                          fontWeight: FontWeight.w900,
                          color: Color(0xFF0878C9),
                        ),
                      ),
                    ],
                  ),
                  Row(
                    children: [
                      Text(
                        _formatDate(payment.paidAt ?? payment.orderCreatedAt),
                        style: const TextStyle(
                          fontSize: 11,
                          color: Color(0xFF6F8BA3),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 10, vertical: 5),
                        decoration: BoxDecoration(
                          color: const Color(0xFFEAF7FF),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                              color: const Color(0xFFBCE3FD), width: 0.8),
                        ),
                        child: Row(
                          children: const [
                            Text(
                              'Details',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFF0878C9),
                              ),
                            ),
                            SizedBox(width: 2),
                            Icon(Icons.arrow_forward_ios_rounded,
                                size: 10, color: Color(0xFF0878C9)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildMethodPill(String method) {
    final isCredit = method == 'CREDIT';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2.5),
      decoration: BoxDecoration(
        color: isCredit ? const Color(0xFFF3E8FF) : const Color(0xFFEAF7FF),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            isCredit ? Icons.credit_score_rounded : Icons.payments_outlined,
            size: 11,
            color: isCredit ? const Color(0xFF7E22CE) : const Color(0xFF0878C9),
          ),
          const SizedBox(width: 3),
          Text(
            isCredit ? 'Credit' : 'Cash',
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.bold,
              color: isCredit ? const Color(0xFF7E22CE) : const Color(0xFF0878C9),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusBadge(PaymentModel payment) {
    if (payment.isPaid) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3.5),
        decoration: BoxDecoration(
          color: const Color(0xFFE8F8F0),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: const Color(0xFFBBE5D0), width: 0.8),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: const [
            Icon(Icons.check_circle_rounded,
                color: Color(0xFF128A47), size: 12),
            SizedBox(width: 4),
            Text(
              'Paid',
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                color: Color(0xFF128A47),
              ),
            ),
          ],
        ),
      );
    }

    final isPendingCollection = payment.displayStatus == 'Pending Collection';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3.5),
      decoration: BoxDecoration(
        color: const Color(0xFFFEF5E7),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFFBD79F), width: 0.8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.access_time_rounded,
              color: Color(0xFFC4710A), size: 12),
          const SizedBox(width: 4),
          Text(
            isPendingCollection ? 'Pending Collection' : 'Pending',
            style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: Color(0xFFC4710A),
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // PAYMENT DETAILS MODAL DIALOG
  // ====================================================================
  void _showPaymentDetailsDialog(BuildContext context, PaymentModel payment) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFDCEAF5),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'PAYMENT DETAILS',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF6F8BA3),
                          letterSpacing: 0.6,
                        ),
                      ),
                      Text(
                        payment.referenceNumber,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF12344D),
                        ),
                      ),
                    ],
                  ),
                  _buildStatusBadge(payment),
                ],
              ),
              const SizedBox(height: 16),
              const Divider(color: Color(0xFFE2F1FC), height: 1),
              const SizedBox(height: 14),

              // Details grid
              _buildDetailRow('Payment Method', payment.method),
              _buildDetailRow(
                  'Amount', '₱${payment.amount.toStringAsFixed(2)}'),
              _buildDetailRow(
                  'Payment Status', payment.displayStatus),
              _buildDetailRow('Date',
                  _formatDate(payment.paidAt ?? payment.orderCreatedAt)),
              if (payment.orderNumber != null)
                _buildDetailRow(
                    'Associated Order', '#${payment.orderNumber}'),
              _buildDetailRow(
                  'Service Type', 'Customer-Owned Water Refill'),

              if (payment.items.isNotEmpty) ...[
                const SizedBox(height: 12),
                const Text(
                  'Refill Items',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF064B87),
                  ),
                ),
                const SizedBox(height: 6),
                ...payment.items.map((item) => Padding(
                      padding: const EdgeInsets.symmetric(vertical: 2),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            '${item.quantity} × ${item.name}',
                            style: const TextStyle(
                                fontSize: 12, color: Color(0xFF12344D)),
                          ),
                          Text(
                            '₱${item.subtotal.toStringAsFixed(2)}',
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF0878C9),
                            ),
                          ),
                        ],
                      ),
                    )),
              ],

              const SizedBox(height: 20),

              // Associated Order Action Button
              if (payment.orderId.isNotEmpty)
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.of(ctx).pop();
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) =>
                              OrderDetailScreen(orderId: payment.orderId),
                        ),
                      );
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF0878C9),
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: const [
                        Icon(Icons.visibility_outlined, size: 18),
                        SizedBox(width: 8),
                        Text(
                          'View Associated Order',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: const TextStyle(fontSize: 12, color: Color(0xFF6F8BA3)),
          ),
          Text(
            value,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: Color(0xFF12344D),
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // SKELETON / EMPTY / ERROR STATES
  // ====================================================================
  Widget _buildLoadingSkeleton() {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 140,
            height: 24,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.7),
              borderRadius: BorderRadius.circular(8),
            ),
          ),
          const SizedBox(height: 16),
          Container(
            width: double.infinity,
            height: 130,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.8),
              borderRadius: BorderRadius.circular(22),
            ),
          ),
          const SizedBox(height: 14),
          Container(
            width: double.infinity,
            height: 90,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.8),
              borderRadius: BorderRadius.circular(20),
            ),
          ),
          const SizedBox(height: 20),
          Expanded(
            child: ListView.separated(
              itemCount: 3,
              separatorBuilder: (context, index) => const SizedBox(height: 12),
              itemBuilder: (context, index) => Container(
                height: 110,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.8),
                  borderRadius: BorderRadius.circular(20),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState(String filter) {
    String msg = 'No payment transactions yet.';
    if (filter == 'PAID') msg = 'No paid transactions found.';
    if (filter == 'PENDING') msg = 'No pending payments at this time.';
    if (filter == 'CREDIT') msg = 'No credit transactions found.';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(32),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.95),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFE2F1FC), width: 1.2),
      ),
      child: Column(
        children: [
          Container(
            width: 60,
            height: 60,
            decoration: const BoxDecoration(
              color: Color(0xFFEAF7FF),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.receipt_long_rounded,
              color: Color(0xFF0878C9),
              size: 30,
            ),
          ),
          const SizedBox(height: 14),
          Text(
            msg,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: Color(0xFF12344D),
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Your payment transactions will appear here after placing your water refill orders.',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 12,
              color: Color(0xFF6F8BA3),
              height: 1.4,
            ),
          ),
          const SizedBox(height: 18),
          SizedBox(
            height: 42,
            child: ElevatedButton(
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const CatalogScreen()),
                );
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0878C9),
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: const Text(
                'Order Water',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildErrorState(PaymentProvider prov) {
    return Center(
      child: Container(
        margin: const EdgeInsets.all(24),
        padding: const EdgeInsets.all(28),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: const Color(0xFFE2F1FC), width: 1.5),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.cloud_off_rounded,
              size: 48,
              color: Color(0xFFE53935),
            ),
            const SizedBox(height: 14),
            const Text(
              'Unable to load payment information',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.bold,
                color: Color(0xFF12344D),
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'Please check your network connection and try again.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: Color(0xFF6F8BA3)),
            ),
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: () => prov.loadPayments(),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0878C9),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: const Text('Try Again'),
            ),
          ],
        ),
      ),
    );
  }

  String _formatDate(String? raw) {
    if (raw == null || raw.isEmpty) return 'Recent';
    try {
      final dt = PhtDateTime.parse(raw);
      final months = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec'
      ];
      final month = months[dt.month - 1];
      final hour = dt.hour > 12
          ? dt.hour - 12
          : dt.hour == 0
              ? 12
              : dt.hour;
      final ampm = dt.hour >= 12 ? 'PM' : 'AM';
      final min = dt.minute.toString().padLeft(2, '0');
      return '$month ${dt.day}, ${dt.year} • $hour:$min $ampm';
    } catch (_) {
      return raw.length > 10 ? raw.substring(0, 10) : raw;
    }
  }
}
