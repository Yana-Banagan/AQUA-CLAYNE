class PaymentModel {
  final String id;
  final String orderId;
  final String method;
  final double amount;
  final String status;
  final String? paidAt;
  final String? orderNumber;
  final String? orderCreatedAt;
  final String? orderStatus;
  final String serviceDescription;
  final int containerCount;
  final String? deliveryAddress;
  final List<PaymentItemModel> items;

  const PaymentModel({
    required this.id,
    required this.orderId,
    required this.method,
    required this.amount,
    required this.status,
    this.paidAt,
    this.orderNumber,
    this.orderCreatedAt,
    this.orderStatus,
    this.serviceDescription = 'Water Refill Service • Customer-Owned',
    this.containerCount = 0,
    this.deliveryAddress,
    this.items = const [],
  });

  factory PaymentModel.fromMap(Map<String, dynamic> m) {
    final order = m['order'] as Map<String, dynamic>?;
    final rawItems = (order?['items'] ?? order?['order_items']) as List? ?? [];
    final addressMap = order?['address'] as Map<String, dynamic>?;

    final parsedItems = rawItems
        .map((i) => PaymentItemModel.fromMap(i as Map<String, dynamic>))
        .toList();

    int totalGallons = 0;
    if (order != null) {
      if (order['expected_empty_gallons'] != null) {
        totalGallons = _toInt(order['expected_empty_gallons']);
      } else {
        for (final it in parsedItems) {
          totalGallons += it.quantity;
        }
      }
    }

    String serviceDesc = '5-Gallon Purified Water Refill • Customer-Owned';
    if (parsedItems.isNotEmpty) {
      serviceDesc = '${parsedItems.first.name} • Customer-Owned';
    }

    return PaymentModel(
      id: m['id']?.toString() ?? '',
      orderId: m['order_id']?.toString() ?? order?['id']?.toString() ?? '',
      method: (m['method']?.toString() ?? 'cash').toUpperCase(),
      amount: _toDouble(m['amount']),
      status: (m['status']?.toString() ?? 'pending').toUpperCase(),
      paidAt: m['paid_at']?.toString(),
      orderNumber: order?['order_number']?.toString(),
      orderCreatedAt: order?['created_at']?.toString(),
      orderStatus: order?['status']?.toString(),
      serviceDescription: serviceDesc,
      containerCount: totalGallons,
      deliveryAddress: addressMap?['address_line']?.toString() ??
          addressMap?['full_address']?.toString(),
      items: parsedItems,
    );
  }

  static double _toDouble(dynamic v) {
    if (v is num) return v.toDouble();
    return double.tryParse(v?.toString() ?? '0') ?? 0;
  }

  static int _toInt(dynamic v) {
    if (v is int) return v;
    return int.tryParse(v?.toString() ?? '0') ?? 0;
  }

  bool get isPaid => status == 'PAID';
  bool get isPending => status == 'PENDING';
  bool get isCredit => method == 'CREDIT';
  bool get isCash => method == 'CASH';

  /// Display text for payment status badge
  String get displayStatus {
    if (isPaid) return 'Paid';
    if (isCash && isPending) {
      // If delivery is already delivered or out for delivery, pending collection
      final ord = orderStatus?.toUpperCase();
      if (ord == 'DELIVERED' || ord == 'OUT_FOR_DELIVERY') {
        return 'Pending Collection';
      }
      return 'Pending';
    }
    return 'Pending';
  }

  /// Reference tag e.g. "PAY-A1B2" or first 8 chars
  String get referenceNumber {
    if (id.isEmpty) return 'PAY-0000';
    if (id.length >= 8) {
      return 'PAY-${id.substring(0, 8).toUpperCase()}';
    }
    return 'PAY-$id';
  }
}

class PaymentItemModel {
  final String name;
  final int quantity;
  final double unitPrice;
  final double subtotal;

  const PaymentItemModel({
    required this.name,
    required this.quantity,
    required this.unitPrice,
    required this.subtotal,
  });

  factory PaymentItemModel.fromMap(Map<String, dynamic> m) {
    final prod = m['product'] as Map<String, dynamic>?;
    return PaymentItemModel(
      name: prod?['name']?.toString() ??
          m['product_name']?.toString() ??
          'Water Refill',
      quantity: PaymentModel._toInt(m['quantity']),
      unitPrice: PaymentModel._toDouble(m['unit_price']),
      subtotal: PaymentModel._toDouble(m['subtotal']),
    );
  }
}

class PaymentSummaryModel {
  final double outstandingBalance;
  final double creditBalance;
  final double creditLimit;
  final double availableCredit;
  final bool hasCreditAccount;
  final String creditStatus;
  final double pendingCashTotal;
  final double totalPaid;
  final bool allPaidUp;

  const PaymentSummaryModel({
    this.outstandingBalance = 0.0,
    this.creditBalance = 0.0,
    this.creditLimit = 0.0,
    this.availableCredit = 0.0,
    this.hasCreditAccount = false,
    this.creditStatus = 'NONE',
    this.pendingCashTotal = 0.0,
    this.totalPaid = 0.0,
    this.allPaidUp = true,
  });

  factory PaymentSummaryModel.fromMap(Map<String, dynamic> m) {
    return PaymentSummaryModel(
      outstandingBalance: PaymentModel._toDouble(m['outstanding_balance']),
      creditBalance: PaymentModel._toDouble(m['credit_balance']),
      creditLimit: PaymentModel._toDouble(m['credit_limit']),
      availableCredit: PaymentModel._toDouble(m['available_credit']),
      hasCreditAccount: m['has_credit_account'] == true,
      creditStatus: m['credit_status']?.toString() ?? 'NONE',
      pendingCashTotal: PaymentModel._toDouble(m['pending_cash_total']),
      totalPaid: PaymentModel._toDouble(m['total_paid']),
      allPaidUp: m['all_paid_up'] == true,
    );
  }
}
