import 'package:flutter/material.dart';

/// Shared step progress bar used across all Aqua Clayne checkout screens.
class CheckoutStepBar extends StatelessWidget {
  final int step; // 1 = Address, 2 = Gallons, 3 = Payment, 4 = Review

  const CheckoutStepBar({super.key, required this.step});

  @override
  Widget build(BuildContext context) {
    const labels = ['Address', 'Gallons', 'Payment', 'Review'];

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFD5E7F4), width: 1.2),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0878C9).withValues(alpha: 0.06),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: List.generate(labels.length, (i) {
          final active = i + 1 == step;
          final done = i + 1 < step;

          return Expanded(
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      // Step Circle
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        width: 28,
                        height: 28,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: (done || active)
                              ? const Color(0xFF0878C9)
                              : const Color(0xFFF0F6FA),
                          border: Border.all(
                            color: (done || active)
                                ? const Color(0xFF0878C9)
                                : const Color(0xFFD5E7F4),
                            width: 1.5,
                          ),
                          boxShadow: active
                              ? [
                                  BoxShadow(
                                    color: const Color(0xFF0878C9)
                                        .withValues(alpha: 0.25),
                                    blurRadius: 6,
                                    offset: const Offset(0, 2),
                                  ),
                                ]
                              : null,
                        ),
                        child: Center(
                          child: done
                              ? const Icon(
                                  Icons.check_rounded,
                                  color: Colors.white,
                                  size: 15,
                                )
                              : Text(
                                  '${i + 1}',
                                  style: TextStyle(
                                    color: active
                                        ? Colors.white
                                        : const Color(0xFF6F8BA3),
                                    fontSize: 12,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                        ),
                      ),
                      const SizedBox(height: 4),
                      // Step Label
                      Text(
                        labels[i],
                        textAlign: TextAlign.center,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 10.5,
                          fontWeight:
                              active ? FontWeight.w800 : FontWeight.w600,
                          color: active
                              ? const Color(0xFF0878C9)
                              : (done
                                  ? const Color(0xFF12344D)
                                  : const Color(0xFF8FA9BF)),
                        ),
                      ),
                    ],
                  ),
                ),
                // Connector line between steps
                if (i < labels.length - 1)
                  Expanded(
                    child: Container(
                      height: 2.5,
                      margin: const EdgeInsets.only(bottom: 18, left: 2, right: 2),
                      decoration: BoxDecoration(
                        color: done
                            ? const Color(0xFF0878C9)
                            : const Color(0xFFE5F1FA),
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
              ],
            ),
          );
        }),
      ),
    );
  }
}
