import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:school/features/finance/finance_configuration_screen.dart';

import 'final_ui_polish_test.dart' show host;
import 'support/auth_fixtures.dart';

void main() {
  for (final permissions in [
    ['finance.read', 'finance.configure'],
    ['finance.read', 'finance.approve', 'payroll.approve'],
    ['finance.read'],
    ['finance.read', 'payroll.approve'],
  ]) {
    testWidgets('fee configuration actions respect $permissions', (
      tester,
    ) async {
      final auth = createAuth(
        MemoryStore(),
        handler: (request) async => http.Response(
          jsonEncode({
            'data': {'schedules': []},
          }),
          200,
        ),
      );
      addTearDown(auth.dispose);
      auth.user = {
        ...testUser,
        'permissions': permissions,
        'roles': [
          {'code': 'school_administrator', 'name': 'Administrator'},
        ],
      };
      await tester.pumpWidget(host(FinanceConfigurationScreen(auth: auth)));
      await tester.pumpAndSettle();

      final canConfigure =
          permissions.contains('finance.configure') ||
          permissions.contains('finance.approve');
      expect(
        find.widgetWithText(OutlinedButton, 'Fee schedules'),
        canConfigure ? findsOneWidget : findsNothing,
      );
      expect(
        find.widgetWithText(OutlinedButton, 'Staff compensation'),
        permissions.contains('payroll.approve') ? findsOneWidget : findsNothing,
      );
      expect(
        find.widgetWithText(OutlinedButton, 'Payroll adjustments'),
        permissions.contains('payroll.approve') ? findsOneWidget : findsNothing,
      );
      expect(
        find.byType(OutlinedButton),
        findsNWidgets(
          (canConfigure ? 4 : 0) +
              (permissions.contains('payroll.approve') ? 2 : 0),
        ),
      );
    });
  }
}
