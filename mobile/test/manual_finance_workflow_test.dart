import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:school/features/finance/student_fee_screen.dart';
import 'package:school/features/finance/financial_forms.dart';
import 'package:school/features/finance/finance_summary_screen.dart';

import 'final_ui_polish_test.dart' show host;
import 'support/auth_fixtures.dart';

http.Response ok(Object data) => http.Response(jsonEncode({'data': data}), 200);
const method = {'id': 'cash-id', 'code': 'cash', 'name': 'Cash'};
const invoice = {
  'id': 'invoice-id',
  'voucher_number': 'FV-TEST',
  'period': '2026-10',
  'due_on': '2026-10-28',
  'balance_minor': 100000,
  'status': 'unpaid',
};
Finder field(String key) => find.descendant(
  of: find.byKey(ValueKey(key)),
  matching: find.byType(TextFormField),
);

void main() {
  testWidgets(
    'unbilled tuition explains zero posted dues and disables payment',
    (tester) async {
      final auth = createAuth(
        MemoryStore(),
        handler: (_) async => ok({
          'student': {
            'full_name': 'Fictional Student',
            'registration_number': 'INF-001',
          },
          'configured_fee_minor': 100000,
          'unbilled_minor': 100000,
          'voucher_required': true,
          'balance_minor': 0,
          'items': [],
          'payments': [],
          'discounts': [],
          'fee_schedule': [],
        }),
      );
      addTearDown(auth.dispose);
      auth.user = {
        ...testUser,
        'permissions': ['finance.read', 'finance.write'],
      };
      await tester.pumpWidget(
        host(StudentFeeScreen(auth: auth, studentId: 'student-id')),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.textContaining('configured but not billed'),
        200,
      );
      expect(
        find.textContaining('Generate a voucher to post the due'),
        findsOneWidget,
      );
      final pay = find.widgetWithText(OutlinedButton, 'Record payment');
      await tester.scrollUntilVisible(pay, 150);
      await tester.ensureVisible(pay);
      await tester.pumpAndSettle();
      expect(tester.widget<OutlinedButton>(pay).onPressed, isNull);
    },
  );

  testWidgets(
    'manual partial payment submits amount notes and allocation then refreshes balance',
    (tester) async {
      Map<String, dynamic>? payload;
      final auth = createAuth(
        MemoryStore(),
        handler: (r) async {
          if (r.url.path.endsWith('/finance/configuration')) {
            return ok({
              'payment_methods': [method],
            });
          }
          if (r.method == 'POST' && r.url.path.endsWith('/finance/payments')) {
            payload = jsonDecode(r.body) as Map<String, dynamic>;
            return ok({'id': 'payment-id'});
          }
          if (r.url.path.endsWith('/finance/payments/payment-id')) {
            return ok({
              'receipt_number': 'RC-TEST',
              'paid_on': '2026-10-28',
              'amount_minor': 25000,
              'method': 'Cash',
              'status': 'verified',
              'notes': 'Desk receipt',
              'allocations': [],
              'refunds': [],
            });
          }
          return ok({
            'student': {
              'full_name': 'Fictional Student',
              'registration_number': 'INF-001',
            },
            'total_minor': 100000,
            'paid_minor': payload == null ? 0 : 25000,
            'balance_minor': payload == null ? 100000 : 75000,
            'items': [
              {...invoice, 'balance_minor': payload == null ? 100000 : 75000},
            ],
            'payments': [],
            'discounts': [],
            'fee_schedule': [],
          });
        },
      );
      addTearDown(auth.dispose);
      auth.user = {
        ...testUser,
        'permissions': ['finance.read', 'finance.write'],
      };
      await tester.pumpWidget(
        host(StudentFeeScreen(auth: auth, studentId: 'student-id')),
      );
      await tester.pumpAndSettle();
      final pay = find.widgetWithText(OutlinedButton, 'Record payment');
      await tester.scrollUntilVisible(pay, 200);
      await tester.ensureVisible(pay);
      await tester.pumpAndSettle();
      await tester.tap(pay);
      await tester.pumpAndSettle();
      expect(
        find.textContaining('Outstanding before payment: PKR 1000.00'),
        findsOneWidget,
      );
      await tester.ensureVisible(field('amount_received'));
      await tester.enterText(field('amount_received'), '250.00');
      await tester.ensureVisible(field('notes'));
      await tester.enterText(field('notes'), 'Desk receipt');
      tester.testTextInput.hide();
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.widgetWithText(FilledButton, 'Save'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Save'));
      await tester.pumpAndSettle();
      expect(payload?['amount_minor'], 25000);
      expect(payload?['payment_method_id'], 'cash-id');
      expect(payload?['notes'], 'Desk receipt');
      expect(payload?['allocations'], [
        {'fee_invoice_id': 'invoice-id', 'amount_minor': 25000},
      ]);
      expect(payload?['request_id'], isNotEmpty);
      expect(find.text('RC-TEST'), findsOneWidget);
      await tester.pageBack();
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Outstanding posted dues'),
        -200,
      );
      expect(find.text('PKR 750.00'), findsWidgets);
    },
  );

  testWidgets(
    'empty active method list produces actionable message instead of empty dropdown',
    (tester) async {
      final auth = createAuth(
        MemoryStore(),
        handler: (_) async => ok({'payment_methods': []}),
      );
      addTearDown(auth.dispose);
      await tester.pumpWidget(
        host(
          Builder(
            builder: (context) => TextButton(
              onPressed: () => recordPayment(context, auth, [invoice]),
              child: const Text('Pay'),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Pay'));
      await tester.pumpAndSettle();
      expect(find.text('No active payment methods'), findsOneWidget);
      expect(
        find.textContaining('configure manual payment methods'),
        findsOneWidget,
      );
      expect(find.byType(DropdownButtonFormField<String>), findsNothing);
    },
  );

  testWidgets(
    'salary instalment includes amount employee allocation notes and retry key',
    (tester) async {
      Map<String, dynamic>? payload;
      final auth = createAuth(
        MemoryStore(),
        handler: (r) async {
          if (r.method == 'POST') {
            payload = jsonDecode(r.body) as Map<String, dynamic>;
            return ok({'paid': true});
          }
          return ok({
            'items': [method],
          });
        },
      );
      addTearDown(auth.dispose);
      await tester.pumpWidget(
        host(
          Builder(
            builder: (context) => TextButton(
              onPressed: () => disbursementForm(
                context,
                auth,
                'finance/payroll/period-id/pay',
                outstanding: 100000,
                payrollItemId: 'item-id',
                staffName: 'Fictional Teacher',
              ),
              child: const Text('Salary'),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Salary'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Fictional Teacher'), findsOneWidget);
      await tester.enterText(field('amount_minor'), '400.00');
      await tester.ensureVisible(field('notes'));
      await tester.enterText(field('notes'), 'Salary instalment');
      tester.testTextInput.hide();
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.widgetWithText(FilledButton, 'Save'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Save'));
      await tester.pumpAndSettle();
      expect(payload?['amount_minor'], 40000);
      expect(payload?['allocations'], [
        {'payroll_item_id': 'item-id', 'amount_minor': 40000},
      ]);
      expect(payload?['notes'], 'Salary instalment');
      expect(payload?['request_id'], isNotEmpty);
    },
  );

  testWidgets(
    'finance overview renders server totals in a narrow dark viewport',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final auth = createAuth(
        MemoryStore(),
        handler: (_) async => ok({
          'total_minor': 146000,
          'paid_minor': 26000,
          'discount_minor': 24000,
          'balance_minor': 120000,
          'arrears_minor': 50000,
          'students_unpaid': 0,
          'students_partially_paid': 1,
          'student_balances': [],
          'recent_payments': [],
        }),
      );
      addTearDown(auth.dispose);
      await tester.pumpWidget(
        host(FinanceSummaryScreen(auth: auth), dark: true),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(find.text('PKR 1200.00'), 200);
      expect(find.text('PKR 1200.00'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
