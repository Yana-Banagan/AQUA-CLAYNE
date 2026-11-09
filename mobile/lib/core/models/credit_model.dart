class CreditAccountModel {
  final String id;
  final double balance;
  final double creditLimit;
  final double outstandingBalance;
  final List<CreditTransactionModel> transactions;

  const CreditAccountModel({
    required this.id,
    required this.balance,
    required this.creditLimit,
    required this.outstandingBalance,
    this.transactions = const [],
  });

  factory CreditAccountModel.fromMap(Map<String, dynamic> m) {
    final rawTx = m['transactions'] as List? ?? [];
    return CreditAccountModel(
      id: m['id']?.toString() ?? '',
      balance: _toDouble(m['balance']),
      creditLimit: _toDouble(m['credit_limit']),
      outstandingBalance: _toDouble(m['outstanding_balance']),
      transactions: rawTx
          .map((t) => CreditTransactionModel.fromMap(t as Map<String, dynamic>))
          .toList(),
    );
  }

  static double _toDouble(dynamic v) {
    if (v is num) return v.toDouble();
    return double.tryParse(v?.toString() ?? '0') ?? 0;
  }

  double get availableCredit => creditLimit - outstandingBalance;
}

class CreditTransactionModel {
  final String id;
  final String type;
  final double amount;
  final String? description;
  final String? orderId;
  final String createdAt;

  const CreditTransactionModel({
    required this.id,
    required this.type,
    required this.amount,
    this.description,
    this.orderId,
    required this.createdAt,
  });

  factory CreditTransactionModel.fromMap(Map<String, dynamic> m) =>
      CreditTransactionModel(
        id: m['id']?.toString() ?? '',
        type: m['type']?.toString() ?? 'DEBIT',
        amount: m['amount'] is num
            ? (m['amount'] as num).toDouble()
            : double.tryParse(m['amount']?.toString() ?? '0') ?? 0,
        description: m['description']?.toString(),
        orderId: m['order_id']?.toString(),
        createdAt: m['created_at']?.toString() ?? '',
      );

  bool get isCredit => type.toUpperCase() == 'CREDIT';
}

class FaqModel {
  final String id;
  final String question;
  final String answer;
  final String? categoryName;

  const FaqModel({
    required this.id,
    required this.question,
    required this.answer,
    this.categoryName,
  });

  factory FaqModel.fromMap(Map<String, dynamic> m) {
    final cat = m['category'] as Map<String, dynamic>? ?? {};
    return FaqModel(
      id: m['id']?.toString() ?? '',
      question: m['question']?.toString() ?? '',
      answer: m['answer']?.toString() ?? '',
      categoryName: cat['name']?.toString(),
    );
  }
}
