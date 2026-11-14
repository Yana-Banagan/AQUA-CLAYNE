import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../customer/providers/credit_provider.dart';


class FaqScreen extends StatefulWidget {
  const FaqScreen({super.key});

  @override
  State<FaqScreen> createState() => _FaqScreenState();
}

class _FaqScreenState extends State<FaqScreen> {
  final _searchCtrl = TextEditingController();
  String _query = '';

  // Built-in fallback station FAQs if database is initializing
  static const _fallback = [
    (
      'How does water refill ordering work?',
      'Place an order through the app selecting your preferred refill product and quantity. Our driver will pick up your empty gallons, bring them to our station for refilling, and promptly deliver them back to your door.'
    ),
    (
      'What should I prepare for empty gallon pickup?',
      'Have your empty gallons ready at your designated delivery address. The assigned driver will inspect container condition and count the gallons before bringing them to the station.'
    ),
    (
      'What happens after my empty gallons reach the station?',
      'Station staff inspect and count your empty gallons before refilling begins. You can follow the order status in Order History.'
    ),
    (
      'When will my refilled water be delivered?',
      'Once refilling and quality seals are applied, your order is marked as Ready for Delivery. An Aqua Clayne driver will be dispatched to deliver your filled containers.'
    ),
    (
      'How does Debt payment work?',
      'Debt lets you place an order with an unpaid balance to settle directly with the station.'
    ),
    (
      'What payment methods are supported?',
      'We support Debt, Cash on Delivery (COD), and online payment through PayMongo.'
    ),
    (
      'What happens if a gallon is damaged or cracked?',
      'Drivers inspect empty gallons at pickup. Any container with visible damage or leaks will be flagged. You will be notified immediately to choose whether to replace or return the damaged container.'
    ),
    (
      'Can I submit a complaint or concern?',
      'Yes! Go to Profile > Complaints & Support > Submit Complaint. Choose the relevant category and describe your issue. Our management team actively investigates all customer submissions.'
    ),
  ];

  @override
  void initState() {
    super.initState();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
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
                _buildSearchBar(),
                Expanded(
                  child: Consumer<CreditProvider>(
                    builder: (context, prov, _) {
                      if (prov.loading && prov.faqs.isEmpty) {
                        return _buildLoadingSkeleton();
                      }

                      final qLower = _query.trim().toLowerCase();
                      final List<(String, String)> items = prov.faqs.isNotEmpty
                          ? prov.faqs
                              .where((f) =>
                                  qLower.isEmpty ||
                                  f.question.toLowerCase().contains(qLower) ||
                                  f.answer.toLowerCase().contains(qLower))
                              .map((f) => (f.question, f.answer))
                              .toList()
                          : _fallback
                              .where((f) =>
                                  qLower.isEmpty ||
                                  f.$1.toLowerCase().contains(qLower) ||
                                  f.$2.toLowerCase().contains(qLower))
                              .toList();

                      if (items.isEmpty) {
                        return _buildEmptySearchState();
                      }

                      return RefreshIndicator(
                        color: const Color(0xFF0878C9),
                        backgroundColor: Colors.white,
                        onRefresh: prov.loadFaqs,
                        child: ListView.separated(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
                          itemCount: items.length,
                          separatorBuilder: (_, _) => const SizedBox(height: 12),
                          itemBuilder: (ctx, i) {
                            final (q, a) = items[i];
                            return _FaqAccordionCard(question: q, answer: a);
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
                  'Frequently Asked Questions',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF12344D),
                    letterSpacing: -0.2,
                  ),
                ),
                SizedBox(height: 2),
                Text(
                  'Find answers to common questions',
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
  // SEARCH BAR
  // ====================================================================
  Widget _buildSearchBar() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 6, 16, 12),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF0878C9).withValues(alpha: 0.05),
              blurRadius: 10,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: TextField(
          controller: _searchCtrl,
          onChanged: (v) => setState(() => _query = v),
          style: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: Color(0xFF12344D),
          ),
          decoration: InputDecoration(
            hintText: 'Search questions, refills, credit...',
            hintStyle: const TextStyle(
              fontSize: 13,
              color: Color(0xFF9EBCD5),
              fontWeight: FontWeight.w400,
            ),
            prefixIcon: const Icon(
              Icons.search_rounded,
              color: Color(0xFF0878C9),
              size: 22,
            ),
            suffixIcon: _query.isNotEmpty
                ? IconButton(
                    icon: const Icon(Icons.close_rounded,
                        size: 18, color: Color(0xFF6F8BA3)),
                    onPressed: () {
                      _searchCtrl.clear();
                      setState(() => _query = '');
                    },
                  )
                : null,
            border: InputBorder.none,
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          ),
        ),
      ),
    );
  }

  // ====================================================================
  // EMPTY SEARCH STATE
  // ====================================================================
  Widget _buildEmptySearchState() {
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
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF7FF),
                  shape: BoxShape.circle,
                  border: Border.all(color: const Color(0xFFBCE3FD), width: 1.2),
                ),
                child: const Icon(
                  Icons.search_off_rounded,
                  color: Color(0xFF0878C9),
                  size: 32,
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'No Matching Questions',
                style: TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF12344D),
                ),
              ),
              const SizedBox(height: 6),
              Text(
                'We couldn\'t find any FAQ matching "$_query". Try searching for refill, queue, or credit.',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 12.5,
                  color: Color(0xFF6F8BA3),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 18),
              OutlinedButton(
                onPressed: () {
                  _searchCtrl.clear();
                  setState(() => _query = '');
                },
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: Color(0xFFD5E7F4), width: 1.2),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text(
                  'Clear Search',
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF0878C9),
                  ),
                ),
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
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
      itemCount: 4,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (_, _) => Container(
        height: 68,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.8),
          borderRadius: BorderRadius.circular(16),
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

// ====================================================================
// ACCORDION CARD WIDGET
// ====================================================================
class _FaqAccordionCard extends StatefulWidget {
  final String question;
  final String answer;

  const _FaqAccordionCard({
    required this.question,
    required this.answer,
  });

  @override
  State<_FaqAccordionCard> createState() => _FaqAccordionCardState();
}

class _FaqAccordionCardState extends State<_FaqAccordionCard> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeInOut,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: _expanded ? const Color(0xFF0878C9) : const Color(0xFFE5F1FA),
          width: _expanded ? 1.5 : 1.2,
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: _expanded ? 0.08 : 0.04),
            blurRadius: _expanded ? 14 : 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(18),
        child: InkWell(
          onTap: () => setState(() => _expanded = !_expanded),
          borderRadius: BorderRadius.circular(18),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: _expanded
                            ? const Color(0xFF0878C9)
                            : const Color(0xFFEAF7FF),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.help_outline_rounded,
                        color: _expanded ? Colors.white : const Color(0xFF0878C9),
                        size: 18,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        widget.question,
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: _expanded
                              ? const Color(0xFF0878C9)
                              : const Color(0xFF12344D),
                          height: 1.3,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    AnimatedRotation(
                      turns: _expanded ? 0.5 : 0.0,
                      duration: const Duration(milliseconds: 240),
                      child: Container(
                        width: 28,
                        height: 28,
                        decoration: BoxDecoration(
                          color: _expanded
                              ? const Color(0xFFEAF7FF)
                              : const Color(0xFFF4F9FD),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          Icons.keyboard_arrow_down_rounded,
                          color: _expanded
                              ? const Color(0xFF0878C9)
                              : const Color(0xFF6F8BA3),
                          size: 20,
                        ),
                      ),
                    ),
                  ],
                ),
                if (_expanded) ...[
                  const SizedBox(height: 12),
                  const Divider(color: Color(0xFFF0F5FA), height: 1),
                  const SizedBox(height: 12),
                  Padding(
                    padding: const EdgeInsets.only(left: 44),
                    child: Text(
                      widget.answer,
                      style: const TextStyle(
                        fontSize: 13,
                        color: Color(0xFF4A6982),
                        height: 1.5,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
