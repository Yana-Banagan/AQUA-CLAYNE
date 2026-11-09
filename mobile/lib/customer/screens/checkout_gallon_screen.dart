import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../customer/providers/cart_provider.dart';
import 'checkout_payment_screen.dart';
import 'checkout_step_bar.dart';

class CheckoutGallonScreen extends StatefulWidget {
  final String? addressId;
  final int? initialGallons;
  final String? initialNotes;

  const CheckoutGallonScreen({
    super.key,
    this.addressId,
    this.initialGallons,
    this.initialNotes,
  });

  @override
  State<CheckoutGallonScreen> createState() => _CheckoutGallonScreenState();
}

class _CheckoutGallonScreenState extends State<CheckoutGallonScreen> {
  static const int _maxGallons = 5;
  late int _gallons;
  late final TextEditingController _notesCtrl;
  late final TextEditingController _gallonsCtrl;

  @override
  void initState() {
    super.initState();
    final cart = context.read<CartProvider>();
    _gallons = (widget.initialGallons ??
            (cart.refillGallons > 0 ? cart.refillGallons : 1))
        .clamp(1, _maxGallons);
    _notesCtrl = TextEditingController(text: widget.initialNotes ?? '');
    _gallonsCtrl = TextEditingController(text: '$_gallons');
  }

  @override
  void dispose() {
    _notesCtrl.dispose();
    _gallonsCtrl.dispose();
    super.dispose();
  }

  void _increment() {
    if (_gallons < _maxGallons) {
      _setGallons(_gallons + 1);
    }
  }

  void _decrement() {
    if (_gallons > 1) {
      _setGallons(_gallons - 1);
    }
  }

  void _setGallons(int gallons) {
    final value = gallons.clamp(1, _maxGallons);
    setState(() => _gallons = value);
    _gallonsCtrl.value = TextEditingValue(
      text: '$value',
      selection: TextSelection.collapsed(offset: '$value'.length),
    );
  }

  void _onGallonsChanged(String value) {
    final gallons = int.tryParse(value);
    if (gallons != null && gallons >= 1 && gallons <= _maxGallons) {
      setState(() => _gallons = gallons);
    }
  }

  void _restoreValidGallons() => _gallonsCtrl.text = '$_gallons';

  void _continueToPayment() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => CheckoutPaymentScreen(
          addressId: widget.addressId,
          gallons: _gallons,
          notes: _notesCtrl.text.trim(),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();

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

          // ── 2. Content ─────────────────────────────────────────────
          SafeArea(
            child: Column(
              children: [
                _buildHeader(context),
                const CheckoutStepBar(step: 2),
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 10, 16, 24),
                    children: [
                      if (cart.refillGallons > 0) ...[
                        _buildInfoBanner(cart),
                        const SizedBox(height: 16),
                        _buildGallonStepperCard(),
                        const SizedBox(height: 16),
                      ] else
                        _buildNewGallonInfoBanner(),

                      // Refill Order Summary Snippet
                      if (cart.items.isNotEmpty) ...[
                        _buildCartSummarySnippet(cart),
                        const SizedBox(height: 16),
                      ],

                      // Special Delivery / Pickup Notes Card
                      _buildNotesCard(cart),
                    ],
                  ),
                ),

                // Bottom Sticky Continue Action
                _buildBottomAction(),
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
                  'Gallon Information',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF12344D),
                    letterSpacing: -0.2,
                  ),
                ),
                SizedBox(height: 2),
                Text(
                  'Specify empty gallons to be collected',
                  style: TextStyle(
                    fontSize: 11.5,
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
  // INFO BANNER
  // ====================================================================
  Widget _buildInfoBanner(CartProvider cart) {
    final isPickup = cart.isStationPickup;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFFEAF7FF),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFBCE3FD), width: 1.2),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.06),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: const BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
            ),
            child: Icon(
              isPickup ? Icons.storefront_rounded : Icons.water_drop_rounded,
              color: isPickup ? const Color(0xFF059669) : const Color(0xFF0878C9),
              size: 24,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  isPickup
                      ? 'CUSTOMER-OWNED GALLONS • STATION DROP-OFF'
                      : 'CUSTOMER-OWNED GALLONS',
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w800,
                    color: isPickup
                        ? const Color(0xFF059669)
                        : const Color(0xFF0878C9),
                    letterSpacing: 0.6,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  isPickup
                      ? 'Aqua Clayne refills your own gallons. Please bring your clean, empty containers directly to the station counter when picking up your order.'
                      : 'Aqua Clayne refills your own gallons. Please have your clean, empty containers ready at your doorstep or gate for driver pickup before refilling.',
                  style: const TextStyle(
                    fontSize: 12.5,
                    color: Color(0xFF335C7D),
                    height: 1.45,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNewGallonInfoBanner() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFFEAF7FF),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFBCE3FD), width: 1.2),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.water_drop_rounded, color: Color(0xFF0878C9), size: 26),
          SizedBox(width: 14),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('NEW GALLON WITH WATER', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w800, color: Color(0xFF0878C9))),
              SizedBox(height: 6),
              Text('Your new gallons will arrive filled with water. No empty gallon pickup is required.', style: TextStyle(fontSize: 12.5, color: Color(0xFF335C7D), height: 1.45)),
            ]),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // GALLONS STEPPER CARD
  // ====================================================================
  Widget _buildGallonStepperCard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.05),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          const Text(
            'Expected Empty Gallons to Collect',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: Color(0xFF12344D),
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Confirm how many empty containers the driver will receive',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 12,
              color: Color(0xFF6F8BA3),
            ),
          ),
          const SizedBox(height: 20),

          // Stepper Controls
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // Minus Button
              Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: _gallons > 1 ? _decrement : null,
                  borderRadius: BorderRadius.circular(16),
                  child: Container(
                    width: 50,
                    height: 50,
                    decoration: BoxDecoration(
                      color: _gallons > 1
                          ? const Color(0xFFF0F7FC)
                          : const Color(0xFFF7FAFD),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(
                        color: _gallons > 1
                            ? const Color(0xFFBCE3FD)
                            : const Color(0xFFE5F1FA),
                        width: 1.5,
                      ),
                    ),
                    child: Icon(
                      Icons.remove_rounded,
                      color: _gallons > 1
                          ? const Color(0xFF0878C9)
                          : const Color(0xFFB0C5D8),
                      size: 24,
                    ),
                  ),
                ),
              ),

              const SizedBox(width: 24),

              // Quantity input box
              Container(
                constraints: const BoxConstraints(minWidth: 90),
                padding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                decoration: BoxDecoration(
                  color: const Color(0xFFF7FAFD),
                  borderRadius: BorderRadius.circular(16),
                  border:
                      Border.all(color: const Color(0xFFD5E7F4), width: 1.2),
                ),
                child: Column(
                  children: [
                    SizedBox(
                      width: 56,
                      child: TextField(
                        controller: _gallonsCtrl,
                        textAlign: TextAlign.center,
                        keyboardType: TextInputType.number,
                        inputFormatters: [
                          FilteringTextInputFormatter.digitsOnly,
                          TextInputFormatter.withFunction((oldValue, newValue) {
                            if (newValue.text.isEmpty) return newValue;
                            final value = int.tryParse(newValue.text);
                            return value != null && value <= _maxGallons
                                ? newValue
                                : oldValue;
                          }),
                        ],
                        onChanged: _onGallonsChanged,
                        onEditingComplete: _restoreValidGallons,
                        style: const TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.w900,
                          color: Color(0xFF0878C9),
                        ),
                        decoration: const InputDecoration(
                          isDense: true,
                          border: InputBorder.none,
                          contentPadding: EdgeInsets.zero,
                        ),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _gallons == 1 ? 'Gallon' : 'Gallons',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF6F8BA3),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(width: 24),

              // Plus Button
              Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: _gallons < _maxGallons ? _increment : null,
                  borderRadius: BorderRadius.circular(16),
                  child: Container(
                    width: 50,
                    height: 50,
                    decoration: BoxDecoration(
                      color: _gallons < _maxGallons
                          ? const Color(0xFF0878C9)
                          : const Color(0xFFB0C5D8),
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF0878C9).withValues(alpha: 0.25),
                          blurRadius: 8,
                          offset: const Offset(0, 3),
                        ),
                      ],
                    ),
                    child: Icon(
                      Icons.add_rounded,
                      color: _gallons < _maxGallons
                          ? Colors.white
                          : const Color(0xFFF7FAFD),
                      size: 24,
                    ),
                  ),
                ),
              ),
            ],
          ),

          const SizedBox(height: 16),
          Text(
            'Driver will collect $_gallons customer-owned gallon${_gallons > 1 ? 's' : ''}',
            style: const TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
              color: Color(0xFF0878C9),
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // CART SUMMARY SNIPPET
  // ====================================================================
  Widget _buildCartSummarySnippet(CartProvider cart) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.shopping_bag_outlined,
                  color: Color(0xFF0878C9), size: 16),
              SizedBox(width: 6),
              Text(
                'ORDERED REFILL SERVICES',
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF0878C9),
                  letterSpacing: 0.6,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          ...cart.items.map((item) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      '${item.quantity} × ${item.product.name}',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF12344D),
                      ),
                    ),
                    Text(
                      '₱${item.subtotal.toStringAsFixed(2)}',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF0878C9),
                      ),
                    ),
                  ],
                ),
              )),
        ],
      ),
    );
  }

  // ====================================================================
  // NOTES CARD
  // ====================================================================
  Widget _buildNotesCard(CartProvider cart) {
    final isPickup = cart.isStationPickup;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE5F1FA), width: 1.2),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.notes_rounded, color: Color(0xFF0878C9), size: 18),
              const SizedBox(width: 8),
              Text(
                isPickup
                    ? 'Station Pickup Notes (Optional)'
                    : 'Pickup & Delivery Instructions (Optional)',
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF12344D),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          TextFormField(
            controller: _notesCtrl,
            maxLines: 3,
            style: const TextStyle(
              fontSize: 13,
              color: Color(0xFF12344D),
            ),
            decoration: InputDecoration(
              hintText: isPickup
                  ? 'e.g. Will pick up around 3 PM, special container tags or requests...'
                  : 'e.g. Leave empty containers near the garage, call when arriving, door buzzer #102...',
              hintStyle: const TextStyle(
                fontSize: 12.5,
                color: Color(0xFF9EBCD5),
              ),
              filled: true,
              fillColor: const Color(0xFFF7FAFD),
              contentPadding: const EdgeInsets.all(14),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide:
                    const BorderSide(color: Color(0xFFD5E7F4), width: 1),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide:
                    const BorderSide(color: Color(0xFFD5E7F4), width: 1),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide:
                    const BorderSide(color: Color(0xFF0878C9), width: 1.8),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ====================================================================
  // BOTTOM STICKY ACTION
  // ====================================================================
  Widget _buildBottomAction() {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, -4),
          ),
        ],
        border: const Border(
          top: BorderSide(color: Color(0xFFE5F1FA), width: 1),
        ),
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          width: double.infinity,
          height: 52,
          child: ElevatedButton(
            onPressed: _continueToPayment,
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF0878C9),
              foregroundColor: Colors.white,
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
              ),
            ),
            child: const Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  'Continue to Payment',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                SizedBox(width: 8),
                Icon(Icons.arrow_forward_rounded, size: 18),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
