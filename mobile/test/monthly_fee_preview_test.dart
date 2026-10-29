import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:school/features/finance/monthly_fee_preview.dart';

import 'final_ui_polish_test.dart' show host;

void main() {
  Map<String, dynamic> row(String status, {String period = '2026-05'}) => {
    'registration_number': 'INF-SG-26-0001',
    'full_name': 'Fictional Child',
    'period': period,
    'invoice_id': ['not_billed', 'needs_configuration'].contains(status)
        ? null
        : 'internal-id',
    'voucher_number': 'INF-SG-FV-2026-000001',
    'status': status,
    'net_minor': status == 'needs_configuration' ? null : 100000,
    'paid_minor': status == 'paid'
        ? 100000
        : (status == 'partially_paid' ? 50000 : 0),
    'balance_minor': status == 'paid'
        ? 0
        : (status == 'partially_paid' ? 50000 : 100000),
  };

  for (final status in ['unpaid', 'paid', 'partially_paid']) {
    testWidgets(
      'billed $status shows voucher and balances with no Generate button',
      (tester) async {
        await tester.pumpWidget(
          host(
            MonthlyFeePreview(
              preview: {
                'student_count': 1,
                'missing_months': 0,
                'unbilled_minor': 0,
                'periods': [row(status)],
              },
            ),
          ),
        );
        expect(find.textContaining('No missing monthly fees'), findsOneWidget);
        expect(find.textContaining('Already billed |'), findsOneWidget);
        expect(
          find.textContaining('Voucher: INF-SG-FV-2026-000001'),
          findsOneWidget,
        );
        expect(find.textContaining('Paid: PKR'), findsOneWidget);
        expect(find.textContaining('Balance: PKR'), findsOneWidget);
        expect(find.textContaining('May 2026'), findsOneWidget);
        expect(find.textContaining('internal-id'), findsNothing);
        expect(find.byType(FilledButton), findsNothing);
        expect(find.text('Close'), findsOneWidget);
      },
    );
  }

  testWidgets('ready months enable counted Generate button', (tester) async {
    await tester.pumpWidget(
      host(
        MonthlyFeePreview(
          preview: {
            'student_count': 1,
            'missing_months': 2,
            'unbilled_minor': 200000,
            'periods': [
              row('not_billed'),
              row('not_billed', period: '2026-06'),
            ],
          },
        ),
      ),
    );
    expect(
      find.textContaining('Not billed | Ready to generate'),
      findsNWidgets(2),
    );
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNotNull,
    );
    expect(find.text('Generate 2 missing fees'), findsOneWidget);
  });

  testWidgets(
    'unknown configuration blocks whole batch without showing zero tuition',
    (tester) async {
      await tester.pumpWidget(
        host(
          MonthlyFeePreview(
            preview: {
              'student_count': 1,
              'missing_months': 2,
              'unbilled_minor': 100000,
              'periods': [
                row('not_billed'),
                row('needs_configuration', period: '2026-06'),
              ],
            },
          ),
        ),
      );
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull,
      );
      expect(find.text('Fee configuration required'), findsOneWidget);
      expect(find.textContaining('Tuition: PKR 0'), findsNothing);
    },
  );
}
