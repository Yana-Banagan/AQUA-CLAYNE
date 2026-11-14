import 'package:flutter/material.dart';
import 'package:mobile/core/utils/pht_datetime.dart';
import 'package:provider/provider.dart';
import '../../customer/providers/complaint_provider.dart';
import '../../core/models/complaint_model.dart';
import 'complaint_detail_screen.dart';
import 'submit_complaint_screen.dart';

class ComplaintsScreen extends StatefulWidget {
  const ComplaintsScreen({super.key});

  @override
  State<ComplaintsScreen> createState() => _ComplaintsScreenState();
}

class _ComplaintsScreenState extends State<ComplaintsScreen> {
  String _selectedStatus = 'ALL';

  final List<String> _filterStatuses = [
    'ALL',
    'PENDING',
    'IN INVESTIGATION',
    'RESOLVED',
    'DISMISSED',
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ComplaintProvider>().load();
    });
  }

  Future<void> _navigateToSubmit() async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const SubmitComplaintScreen()),
    );
    if (mounted) {
      context.read<ComplaintProvider>().load();
    }
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
                _buildStatusFilters(),
                Expanded(
                  child: Consumer<ComplaintProvider>(
                    builder: (context, prov, _) {
                      if (prov.loading && prov.complaints.isEmpty) {
                        return _buildLoadingSkeleton();
                      }

                      if (prov.error != null && prov.complaints.isEmpty) {
                        return _buildErrorState(prov);
                      }

                      final filtered = _filterComplaints(prov.complaints);

                      if (filtered.isEmpty) {
                        return _buildEmptyState();
                      }

                      return RefreshIndicator(
                        color: const Color(0xFF0878C9),
                        backgroundColor: Colors.white,
                        onRefresh: prov.load,
                        child: ListView.separated(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(16, 4, 16, 80),
                          itemCount: filtered.length,
                          separatorBuilder: (_, _) => const SizedBox(height: 12),
                          itemBuilder: (ctx, i) {
                            final complaint = filtered[i];
                            return _buildComplaintCard(ctx, complaint);
                          },
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
      floatingActionButton: Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: FloatingActionButton.extended(
          onPressed: _navigateToSubmit,
          icon: const Icon(Icons.add_comment_rounded, size: 20),
          label: const Text(
            'Submit Complaint',
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5),
          ),
          backgroundColor: const Color(0xFF0878C9),
          foregroundColor: Colors.white,
          elevation: 4,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        ),
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
                  'My Complaints',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF12344D),
                    letterSpacing: -0.2,
                  ),
                ),
                SizedBox(height: 2),
                Text(
                  'View and track your submitted complaints',
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

  // ====================================================================
  // STATUS FILTER CHIPS
  // ====================================================================
  Widget _buildStatusFilters() {
    return SizedBox(
      height: 48,
      child: ListView.separated(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        scrollDirection: Axis.horizontal,
        itemCount: _filterStatuses.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (ctx, i) {
          final s = _filterStatuses[i];
          final isSelected = _selectedStatus == s;

          return ChoiceChip(
            label: Text(_formatStatusFilter(s)),
            selected: isSelected,
            selectedColor: const Color(0xFF0878C9),
            backgroundColor: Colors.white,
            labelStyle: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: isSelected ? Colors.white : const Color(0xFF4A6982),
            ),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
              side: BorderSide(
                color: isSelected
                    ? const Color(0xFF0878C9)
                    : const Color(0xFFD5E7F4),
              ),
            ),
            onSelected: (selected) {
              if (selected) {
                setState(() => _selectedStatus = s);
              }
            },
          );
        },
      ),
    );
  }

  String _formatStatusFilter(String s) {
    if (s == 'ALL') return 'All';
    if (s == 'IN INVESTIGATION') return 'In Investigation';
    return s[0] + s.substring(1).toLowerCase();
  }

  List<ComplaintModel> _filterComplaints(List<ComplaintModel> all) {
    if (_selectedStatus == 'ALL') return all;
    return all.where((c) {
      final statusNormalized =
          c.status.toUpperCase().replaceAll('_', ' ').trim();
      return statusNormalized == _selectedStatus;
    }).toList();
  }

  // ====================================================================
  // COMPLAINT CARD
  // ====================================================================
  Widget _buildComplaintCard(BuildContext context, ComplaintModel c) {
    final ref = c.id.length > 8 ? c.id.substring(0, 8).toUpperCase() : c.id;

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.05),
            blurRadius: 12,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(18),
        child: InkWell(
          onTap: () {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => ComplaintDetailScreen(id: c.id),
              ),
            );
          },
          borderRadius: BorderRadius.circular(18),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Top Row: Reference + Status Badge
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF4F9FD),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: const Color(0xFFD5E7F4)),
                          ),
                          child: Text(
                            '#COMP-$ref',
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF0878C9),
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          c.categoryName,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF12344D),
                          ),
                        ),
                      ],
                    ),
                    _buildStatusBadge(c.status),
                  ],
                ),
                const SizedBox(height: 10),

                // Description Preview
                Text(
                  c.description,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13,
                    color: Color(0xFF4A6982),
                    height: 1.4,
                  ),
                ),

                const SizedBox(height: 12),
                const Divider(height: 1, color: Color(0xFFF0F5FA)),
                const SizedBox(height: 10),

                // Bottom Row: Date + View Details Link
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        const Icon(
                          Icons.calendar_today_rounded,
                          size: 13,
                          color: Color(0xFF9EBCD5),
                        ),
                        const SizedBox(width: 5),
                        Text(
                          _formatDate(c.createdAt),
                          style: const TextStyle(
                            fontSize: 11.5,
                            color: Color(0xFF6F8BA3),
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                    const Row(
                      children: [
                        Text(
                          'View Details',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF0878C9),
                          ),
                        ),
                        SizedBox(width: 2),
                        Icon(
                          Icons.chevron_right_rounded,
                          size: 16,
                          color: Color(0xFF0878C9),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
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
      // Pending / Open
      bg = const Color(0xFFFFFBEB);
      border = const Color(0xFFFDE68A);
      text = const Color(0xFFB45309);
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: border, width: 1),
      ),
      child: Text(
        s,
        style: TextStyle(
          fontSize: 10.5,
          fontWeight: FontWeight.w700,
          color: text,
          letterSpacing: 0.3,
        ),
      ),
    );
  }

  String _formatDate(String raw) {
    try {
      final dt = PhtDateTime.parse(raw);
      final months = [
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
      ];
      return '${months[dt.month - 1]} ${dt.day}, ${dt.year}';
    } catch (_) {
      return raw.length > 10 ? raw.substring(0, 10) : raw;
    }
  }

  // ====================================================================
  // EMPTY STATE
  // ====================================================================
  Widget _buildEmptyState() {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(28),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 36),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0878C9).withValues(alpha: 0.06),
                blurRadius: 18,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 66,
                height: 66,
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF7FF),
                  shape: BoxShape.circle,
                  border: Border.all(color: const Color(0xFFBCE3FD), width: 1.2),
                ),
                child: const Icon(
                  Icons.report_gmailerrorred_rounded,
                  color: Color(0xFF0878C9),
                  size: 32,
                ),
              ),
              const SizedBox(height: 18),
              const Text(
                'No Complaints Found',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF12344D),
                ),
              ),
              const SizedBox(height: 6),
              const Text(
                'You haven\'t submitted any complaints or issues yet.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 13,
                  color: Color(0xFF6F8BA3),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 22),
              ElevatedButton.icon(
                onPressed: _navigateToSubmit,
                icon: const Icon(Icons.add_comment_rounded, size: 18),
                label: const Text(
                  'Submit Complaint',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0878C9),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                  elevation: 0,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ====================================================================
  // ERROR STATE
  // ====================================================================
  Widget _buildErrorState(ComplaintProvider prov) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFFEE2E2)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline_rounded,
                  color: Color(0xFFDC2626), size: 40),
              const SizedBox(height: 12),
              const Text(
                'Something went wrong',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF12344D),
                ),
              ),
              const SizedBox(height: 4),
              Text(
                prov.error ?? 'Unable to load complaints. Please try again.',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 12.5, color: Color(0xFF6F8BA3)),
              ),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: prov.load,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0878C9),
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('Try Again',
                    style: TextStyle(fontWeight: FontWeight.w700)),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ====================================================================
  // LOADING SKELETON
  // ====================================================================
  Widget _buildLoadingSkeleton() {
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 80),
      itemCount: 3,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (_, _) => Container(
        height: 120,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.8),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFFE5F1FA)),
        ),
        child: const Center(
          child: SizedBox(
            width: 22,
            height: 22,
            child: CircularProgressIndicator(
              color: Color(0xFF0878C9),
              strokeWidth: 2.2,
            ),
          ),
        ),
      ),
    );
  }
}
