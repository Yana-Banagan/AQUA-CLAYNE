import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../customer/providers/complaint_provider.dart';


class ComplaintDetailScreen extends StatefulWidget {
  final String id;
  const ComplaintDetailScreen({super.key, required this.id});

  @override
  State<ComplaintDetailScreen> createState() => _ComplaintDetailScreenState();
}

class _ComplaintDetailScreenState extends State<ComplaintDetailScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ComplaintProvider>().loadDetail(widget.id);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          // ── 1. Full-screen Aqua Clayne water background ──────────────
          Positioned.fill(
            child: Image.asset(
              'assets/images/customer_dashboard_bg.png',
              fit: BoxFit.cover,
              alignment: Alignment.center,
            ),
          ),

          // ── 2. Screen Content ────────────────────────────────────────
          SafeArea(
            child: Column(
              children: [
                _buildHeader(context),
                Expanded(
                  child: Consumer<ComplaintProvider>(
                    builder: (context, prov, _) {
                      if (prov.loading && prov.detail == null) {
                        return const Center(
                          child: CircularProgressIndicator(
                            color: Color(0xFF0878C9),
                            strokeWidth: 2.5,
                          ),
                        );
                      }

                      final c = prov.detail;
                      if (c == null) {
                        return Center(
                          child: Container(
                            margin: const EdgeInsets.all(24),
                            padding: const EdgeInsets.all(24),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(color: const Color(0xFFE5F1FA)),
                            ),
                            child: const Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.info_outline_rounded,
                                    size: 36, color: Color(0xFF6F8BA3)),
                                SizedBox(height: 10),
                                Text(
                                  'Complaint Not Found',
                                  style: TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFF12344D),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      }

                      final ref = c.id.length > 8 ? c.id.substring(0, 8).toUpperCase() : c.id;

                      return SingleChildScrollView(
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // 1. Complaint Status Overview Card
                            Container(
                              width: double.infinity,
                              padding: const EdgeInsets.all(18),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(20),
                                border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
                                boxShadow: [
                                  BoxShadow(
                                    color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                                    blurRadius: 14,
                                    offset: const Offset(0, 3),
                                  ),
                                ],
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Container(
                                        padding: const EdgeInsets.symmetric(
                                            horizontal: 10, vertical: 4),
                                        decoration: BoxDecoration(
                                          color: const Color(0xFFEAF7FF),
                                          borderRadius: BorderRadius.circular(8),
                                          border: Border.all(color: const Color(0xFFBCE3FD)),
                                        ),
                                        child: Text(
                                          '#COMP-$ref',
                                          style: const TextStyle(
                                            fontSize: 12,
                                            fontWeight: FontWeight.w800,
                                            color: Color(0xFF0878C9),
                                          ),
                                        ),
                                      ),
                                      _buildStatusBadge(c.status),
                                    ],
                                  ),
                                  const SizedBox(height: 14),
                                  Text(
                                    c.categoryName,
                                    style: const TextStyle(
                                      fontSize: 17,
                                      fontWeight: FontWeight.w800,
                                      color: Color(0xFF12344D),
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Row(
                                    children: [
                                      const Icon(
                                        Icons.access_time_rounded,
                                        size: 14,
                                        color: Color(0xFF9EBCD5),
                                      ),
                                      const SizedBox(width: 5),
                                      Text(
                                        'Submitted on ${_formatDate(c.createdAt)}',
                                        style: const TextStyle(
                                          fontSize: 12,
                                          color: Color(0xFF6F8BA3),
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: 16),

                            // 2. Customer Concern / Message Card
                            Container(
                              width: double.infinity,
                              padding: const EdgeInsets.all(18),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(20),
                                border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
                                boxShadow: [
                                  BoxShadow(
                                    color: const Color(0xFF0878C9).withValues(alpha: 0.05),
                                    blurRadius: 12,
                                    offset: const Offset(0, 3),
                                  ),
                                ],
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Row(
                                    children: [
                                      Icon(Icons.chat_bubble_outline_rounded,
                                          color: Color(0xFF0878C9), size: 18),
                                      SizedBox(width: 8),
                                      Text(
                                        'Your Concern Details',
                                        style: TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w700,
                                          color: Color(0xFF12344D),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 12),
                                  const Divider(color: Color(0xFFF0F5FA), height: 1),
                                  const SizedBox(height: 12),
                                  Text(
                                    c.description,
                                    style: const TextStyle(
                                      fontSize: 14,
                                      color: Color(0xFF33536D),
                                      height: 1.55,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: 16),

                            // 3. Admin Response Card
                            Container(
                              width: double.infinity,
                              padding: const EdgeInsets.all(18),
                              decoration: BoxDecoration(
                                color: c.adminResponse != null && c.adminResponse!.isNotEmpty
                                    ? const Color(0xFFF7FBFF)
                                    : Colors.white,
                                borderRadius: BorderRadius.circular(20),
                                border: Border.all(
                                  color: c.adminResponse != null && c.adminResponse!.isNotEmpty
                                      ? const Color(0xFFBCE3FD)
                                      : const Color(0xFFE5F1FA),
                                  width: 1.2,
                                ),
                                boxShadow: [
                                  BoxShadow(
                                    color: const Color(0xFF0878C9).withValues(alpha: 0.05),
                                    blurRadius: 12,
                                    offset: const Offset(0, 3),
                                  ),
                                ],
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Container(
                                        width: 32,
                                        height: 32,
                                        decoration: BoxDecoration(
                                          color: const Color(0xFFEAF7FF),
                                          borderRadius: BorderRadius.circular(10),
                                        ),
                                        child: const Icon(
                                          Icons.support_agent_rounded,
                                          color: Color(0xFF0878C9),
                                          size: 20,
                                        ),
                                      ),
                                      const SizedBox(width: 10),
                                      const Text(
                                        'Station Management Response',
                                        style: TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w700,
                                          color: Color(0xFF12344D),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 12),
                                  const Divider(color: Color(0xFFF0F5FA), height: 1),
                                  const SizedBox(height: 12),
                                  if (c.adminResponse != null && c.adminResponse!.isNotEmpty)
                                    Text(
                                      c.adminResponse!,
                                      style: const TextStyle(
                                        fontSize: 14,
                                        color: Color(0xFF12344D),
                                        height: 1.5,
                                        fontWeight: FontWeight.w500,
                                      ),
                                    )
                                  else
                                    const Row(
                                      children: [
                                        Icon(
                                          Icons.hourglass_empty_rounded,
                                          color: Color(0xFFD97706),
                                          size: 18,
                                        ),
                                        SizedBox(width: 8),
                                        Expanded(
                                          child: Text(
                                            'Waiting for station review. Our team is actively looking into your submission.',
                                            style: TextStyle(
                                              fontSize: 13,
                                              color: Color(0xFF6F8BA3),
                                              height: 1.4,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                ],
                              ),
                            ),
                          ],
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
  Widget _buildHeader(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          Material(
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
          ),
          const SizedBox(width: 14),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Complaint Details',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF12344D),
                    letterSpacing: -0.2,
                  ),
                ),
                SizedBox(height: 2),
                Text(
                  'Status and management resolution',
                  style: TextStyle(
                    fontSize: 12,
                    color: Color(0xFF6F8BA3),
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusBadge(String rawStatus) {
    final s = rawStatus.toUpperCase().replaceAll('_', ' ');

    Color bg;
    Color border;
    Color text;

    if (s.contains('RESOLV')) {
      bg = const Color(0xFFE8F5E9);
      border = const Color(0xFF81C784);
      text = const Color(0xFF2E7D32);
    } else if (s.contains('INVESTIGAT') || s.contains('PROGRESS')) {
      bg = const Color(0xFFEAF7FF);
      border = const Color(0xFFBCE3FD);
      text = const Color(0xFF0878C9);
    } else if (s.contains('DISMISS') || s.contains('REJECT')) {
      bg = const Color(0xFFF3F4F6);
      border = const Color(0xFFD1D5DB);
      text = const Color(0xFF6B7280);
    } else {
      bg = const Color(0xFFFFFBEB);
      border = const Color(0xFFFDE68A);
      text = const Color(0xFFB45309);
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3.5),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: border, width: 1),
      ),
      child: Text(
        s,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w800,
          color: text,
          letterSpacing: 0.3,
        ),
      ),
    );
  }

  String _formatDate(String raw) {
    try {
      final dt = DateTime.parse(raw).toLocal();
      final months = [
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
      ];
      final hour = dt.hour % 12 == 0 ? 12 : dt.hour % 12;
      final minute = dt.minute.toString().padLeft(2, '0');
      final period = dt.hour >= 12 ? 'PM' : 'AM';
      return '${months[dt.month - 1]} ${dt.day}, ${dt.year} • $hour:$minute $period';
    } catch (_) {
      return raw.length > 10 ? raw.substring(0, 10) : raw;
    }
  }
}
