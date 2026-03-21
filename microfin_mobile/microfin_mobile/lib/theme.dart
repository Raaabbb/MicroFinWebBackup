import 'package:flutter/material.dart';

/// Static color constants for non-tenant-dependent UI elements.
/// For tenant-dependent colors, use Theme.of(context).colorScheme.primary
/// or read activeTenant.value directly.
class AppColors {
  AppColors._();

  // ─── Surface & Background ───────────────────────────────────────────────────
  static const Color bg = Color(0xFFF8FAFC);
  static const Color card = Color(0xFFFFFFFF);
  static const Color surfaceVariant = Color(0xFFF1F5F9);
  static const Color surfaceMuted = Color(0xFFE2E8F0);

  // ─── Text ───────────────────────────────────────────────────────────────────
  static const Color textMain = Color(0xFF0F172A);
  static const Color textBody = Color(0xFF334155);
  static const Color textMuted = Color(0xFF64748B);
  static const Color textLight = Color(0xFF94A3B8);

  // ─── Border ─────────────────────────────────────────────────────────────────
  static const Color separator = Color(0xFFE2E8F0);
  static const Color separatorStrong = Color(0xFFCBD5E1);

  // ─── Status Colors ──────────────────────────────────────────────────────────
  static const Color success = Color(0xFF10B981);
  static const Color successLight = Color(0xFFD1FAE5);
  static const Color successText = Color(0xFF065F46);

  static const Color warning = Color(0xFFF59E0B);
  static const Color warningLight = Color(0xFFFEF3C7);
  static const Color warningText = Color(0xFF92400E);

  static const Color danger = Color(0xFFEF4444);
  static const Color dangerLight = Color(0xFFFEE2E2);
  static const Color dangerText = Color(0xFF991B1B);

  static const Color info = Color(0xFF3B82F6);
  static const Color infoLight = Color(0xFFDBEAFE);
  static const Color infoText = Color(0xFF1E3A8A);

  static const Color indigo = Color(0xFF6366F1);
  static const Color indigoLight = Color(0xFFE0E7FF);

  // ─── Shadows ────────────────────────────────────────────────────────────────
  static List<BoxShadow> get cardShadow => [
        BoxShadow(
          color: Colors.black.withOpacity(0.05),
          blurRadius: 16,
          offset: const Offset(0, 4),
          spreadRadius: 0,
        ),
      ];

  static List<BoxShadow> get cardShadowMd => [
        BoxShadow(
          color: Colors.black.withOpacity(0.08),
          blurRadius: 24,
          offset: const Offset(0, 8),
          spreadRadius: 0,
        ),
      ];

  static List<BoxShadow> elevatedShadow(Color color) => [
        BoxShadow(
          color: color.withOpacity(0.35),
          blurRadius: 20,
          offset: const Offset(0, 8),
          spreadRadius: -2,
        ),
      ];

  // ─── Gradients ──────────────────────────────────────────────────────────────
  static LinearGradient primaryGradient(Color primary, Color secondary) =>
      LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [primary, secondary],
      );
}

/// Formatting helpers for the app
class AppFormat {
  AppFormat._();

  static String peso(double amount) {
    final parts = amount.toStringAsFixed(2).split('.');
    final intPart = parts[0];
    final decPart = parts[1];
    final buffer = StringBuffer();
    int count = 0;
    for (int i = intPart.length - 1; i >= 0; i--) {
      if (count > 0 && count % 3 == 0) buffer.write(',');
      buffer.write(intPart[i]);
      count++;
    }
    return '₱${buffer.toString().split('').reversed.join()}.$decPart';
  }

  static String pesoCompact(double amount) {
    if (amount >= 1000000) {
      return '₱${(amount / 1000000).toStringAsFixed(1)}M';
    } else if (amount >= 1000) {
      return '₱${(amount / 1000).toStringAsFixed(1)}K';
    }
    return peso(amount);
  }
}
