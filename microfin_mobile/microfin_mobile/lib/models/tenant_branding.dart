import 'package:flutter/material.dart';

/// Holds all branding data for a single tenant.
/// In production, this would be fetched from:
///   GET /api/tenant/{slug}/branding
/// and mapped from the tenant_branding table.
class TenantBranding {
  final String slug;
  final String appName;
  final String tagline;
  final Color primaryColor;
  final Color secondaryColor;
  final String emoji;
  final String description;

  const TenantBranding({
    required this.slug,
    required this.appName,
    required this.tagline,
    required this.primaryColor,
    required this.secondaryColor,
    required this.emoji,
    required this.description,
    required this.logo,
  });

  final String logo;

  /// Light tint of primary — for icon backgrounds, chip backgrounds.
  Color get primaryLight => primaryColor.withOpacity(0.12);
  Color get primaryVeryLight => primaryColor.withOpacity(0.06);
  Color get primaryExtraLight => primaryColor.withOpacity(0.08);

  // ─── Static Tenant Configurations ──────────────────────────────────────────
  // In production: replace with API call using tenant_slug from deep link / config

  static const TenantBranding fundline = TenantBranding(
    slug: 'fundline',
    appName: 'Fundline Mobile',
    tagline: 'Your trusted lending partner',
    primaryColor: Color(0xFFDC2626),
    secondaryColor: Color(0xFF991B1B),
    emoji: '💳',
    description: 'Personal & Business Loans at low rates',
    logo: 'images/fundline_logo.png',
  );

  static const TenantBranding plaridel = TenantBranding(
    slug: 'plaridel',
    appName: 'PlaridelMFB',
    tagline: 'Banking for every Filipino',
    primaryColor: Color(0xFF1D4ED8),
    secondaryColor: Color(0xFF1E40AF),
    emoji: '🏦',
    description: 'Agricultural & Rural Financing Solutions',
    logo: 'images/plaridel_logo.png',
  );

  static const TenantBranding sacredheart = TenantBranding(
    slug: 'sacredheart',
    appName: 'Sacred Heart Coop',
    tagline: 'Community-driven microfinance',
    primaryColor: Color(0xFF059669),
    secondaryColor: Color(0xFF065F46),
    emoji: '🌿',
    description: 'Cooperative Loans for the Community',
    logo: 'images/sacred_logo.jpg',
  );

  static const List<TenantBranding> tenants = [fundline, plaridel, sacredheart];

  /// Utility to get tenant branding from a scanned QR URL or deep link.
  /// Example: getTenantById('fundline') resolves to the fundline branding.
  static TenantBranding? fromTenantId(String id) {
    try {
      return tenants.firstWhere((t) => t.slug == id);
    } catch (_) {
      return null;
    }
  }
}
