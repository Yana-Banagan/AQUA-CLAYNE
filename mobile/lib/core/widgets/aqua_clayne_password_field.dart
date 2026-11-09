import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// Password input field with independent show/hide visibility toggle.
class AquaClaynePasswordField extends StatefulWidget {
  final String? label;
  final String hint;
  final TextEditingController? controller;
  final TextInputAction textInputAction;
  final String? Function(String?)? validator;
  final void Function(String)? onChanged;
  final void Function(String)? onFieldSubmitted;
  final bool enabled;
  final FocusNode? focusNode;
  /// Used by live validators to clearly show valid (green) or invalid (red).
  final Color? validationBorderColor;

  const AquaClaynePasswordField({
    super.key,
    this.label,
    this.hint = 'Password',
    this.controller,
    this.textInputAction = TextInputAction.done,
    this.validator,
    this.onChanged,
    this.onFieldSubmitted,
    this.enabled = true,
    this.focusNode,
    this.validationBorderColor,
  });

  @override
  State<AquaClaynePasswordField> createState() => _AquaClaynePasswordFieldState();
}

class _AquaClaynePasswordFieldState extends State<AquaClaynePasswordField> {
  bool _obscure = true;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (widget.label != null) ...[
          Text(
            widget.label!,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: AppColors.darkText,
            ),
          ),
          const SizedBox(height: 6),
        ],
        TextFormField(
          controller: widget.controller,
          obscureText: _obscure,
          textInputAction: widget.textInputAction,
          validator: widget.validator,
          onChanged: widget.onChanged,
          onFieldSubmitted: widget.onFieldSubmitted,
          enabled: widget.enabled,
          focusNode: widget.focusNode,
          style: const TextStyle(
            fontSize: 14.5,
            fontWeight: FontWeight.w500,
            color: AppColors.darkText,
          ),
          decoration: InputDecoration(
            isDense: true,
            hintText: widget.hint,
            hintStyle: const TextStyle(
              fontSize: 14,
              color: AppColors.mutedText,
              fontWeight: FontWeight.w400,
            ),
            filled: true,
            fillColor: Colors.white,
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 14,
            ),
            prefixIcon: const Padding(
              padding: EdgeInsets.only(left: 14, right: 10),
              child: Icon(
                Icons.lock_outline_rounded,
                size: 20,
                color: AppColors.aquaBlue,
              ),
            ),
            prefixIconConstraints: const BoxConstraints(
              minWidth: 44,
              minHeight: 44,
            ),
            suffixIcon: IconButton(
              icon: Icon(
                _obscure ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                color: _obscure ? AppColors.mutedText : AppColors.aquaBlue,
                size: 20,
              ),
              onPressed: () {
                setState(() {
                  _obscure = !_obscure;
                });
              },
              splashRadius: 20,
              tooltip: _obscure ? 'Show password' : 'Hide password',
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: BorderSide(
                color: widget.validationBorderColor ?? AppColors.border,
                width: 1.2,
              ),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: BorderSide(
                color: widget.validationBorderColor ?? AppColors.aquaBlue,
                width: 1.8,
              ),
            ),
            errorBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: const BorderSide(
                color: AppColors.error,
                width: 1.2,
              ),
            ),
            focusedErrorBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: const BorderSide(
                color: AppColors.error,
                width: 1.8,
              ),
            ),
            disabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: BorderSide(
                color: AppColors.border.withValues(alpha: 0.5),
                width: 1.0,
              ),
            ),
            errorStyle: const TextStyle(
              fontSize: 12,
              color: AppColors.error,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
      ],
    );
  }
}
