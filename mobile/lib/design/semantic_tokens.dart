import 'package:flutter/material.dart';

import 'app_theme.dart';

/// Semantic roles shared by every feature; accents always retain a text/icon cue.
enum StatusTone { neutral, info, success, warning, error }

abstract final class AppMotion {
  static const quick = Duration(milliseconds: 180);
  static const standard = Duration(milliseconds: 260);
  static Duration duration(BuildContext context) =>
      MediaQuery.disableAnimationsOf(context) ? Duration.zero : standard;
}

class AppPalette {
  AppPalette.of(BuildContext context) : theme = Theme.of(context);
  final ThemeData theme;
  bool get dark => theme.brightness == Brightness.dark;
  Color get primary => theme.colorScheme.primary;
  Color get secondary => theme.colorScheme.secondary;
  Color get education => dark ? AppColors.lilac : AppColors.indigo;
  Color get accent => dark ? const Color(0xFFE3C78C) : const Color(0xFF805509);
  Color get background => theme.scaffoldBackgroundColor;
  Color get surface => theme.colorScheme.surface;
  Color get elevated => dark ? const Color(0xFF23384B) : Colors.white;
  Color get text => theme.colorScheme.onSurface;
  Color get supporting => theme.colorScheme.onSurfaceVariant;
  Color get border => theme.colorScheme.outlineVariant;
  Color get success => dark ? const Color(0xFF97D9B7) : const Color(0xFF256247);
  Color get warning => dark ? const Color(0xFFF0CF8A) : const Color(0xFF805509);
  Color get error => theme.colorScheme.error;
  Color get information => dark ? const Color(0xFFAECEFA) : AppColors.blue;
  Color get insight => dark ? const Color(0xFF8FD8EB) : const Color(0xFF17677D);
  Color get disabled => theme.colorScheme.onSurface.withValues(alpha: .38);
  Color get selected => primary.withValues(alpha: dark ? .18 : .08);
  Color get navigation => surface;
  Color get card => surface;
  Color tone(StatusTone tone) => switch (tone) {
    StatusTone.neutral => supporting,
    StatusTone.info => information,
    StatusTone.success => success,
    StatusTone.warning => warning,
    StatusTone.error => error,
  };
  Color module(String key) => switch (key) {
    'academics' || 'myClasses' || 'teacherDiary' => education,
    'reports' => insight,
    'finance' => warning,
    'attendance' => success,
    'messages' || 'announcement' || 'contactCoordinator' => secondary,
    'staffModule' || 'staffManagement' || 'addGuardian' => education,
    'settingsModule' || 'accessControl' => supporting,
    _ => primary,
  };
}

abstract final class AppStatus {
  static StatusTone tone(String? value) => switch (value) {
    'paid' ||
    'approved' ||
    'published' ||
    'saved' ||
    'synced' ||
    'present' ||
    'resolved' ||
    'confirmed' ||
    'validated' ||
    'no_balance' ||
    'active' => StatusTone.success,
    'partial' ||
    'partially_paid' ||
    'pending' ||
    'requested' ||
    'pendingSync' ||
    'awaiting_parent' ||
    'late' ||
    'balance_due' ||
    'high' ||
    'needsReview' => StatusTone.warning,
    'rejected' ||
    'unpaid' ||
    'failed' ||
    'syncConflict' ||
    'absent' ||
    'urgent' ||
    'escalated' ||
    'invalid' => StatusTone.error,
    'submitted' ||
    'excused' ||
    'leave' ||
    'refunded' ||
    'in_progress' ||
    'acknowledged' => StatusTone.info,
    _ => StatusTone.neutral,
  };
  static IconData icon(String? value) => switch (tone(value)) {
    StatusTone.success => Icons.check_circle_outline_rounded,
    StatusTone.warning => Icons.schedule_rounded,
    StatusTone.error => Icons.error_outline_rounded,
    StatusTone.info => Icons.info_outline_rounded,
    StatusTone.neutral => Icons.circle_outlined,
  };
}
