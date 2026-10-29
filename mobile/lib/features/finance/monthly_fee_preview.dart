import 'package:flutter/material.dart';

import '../../core/money.dart';

class MonthlyFeePreview extends StatelessWidget {
  const MonthlyFeePreview({super.key, required this.preview});
  final Map<String, dynamic> preview;

  @override
  Widget build(BuildContext context) {
    final rows = (preview['periods'] as List? ?? []);
    final missing = preview['missing_months'] as int? ?? 0;
    final unknown =
        preview['needs_configuration'] as int? ??
        rows.where((r) => r['status'] == 'needs_configuration').length;
    final ready = preview['ready_to_generate'] as int? ?? missing - unknown;
    final billed =
        preview['already_billed'] as int? ??
        rows
            .where(
              (r) =>
                  r['invoice_id'] != null &&
                  r['status'] != 'opening_balance_covered',
            )
            .length;
    final review =
        (preview['opening_balances_needing_review'] as List? ?? []).isNotEmpty;
    return AlertDialog(
      title: const Text('Review monthly tuition'),
      content: SizedBox(
        width: 500,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Students: ${preview['student_count']}\nEligible months: ${preview['eligible_months'] ?? rows.length}\nAlready billed: $billed\nMissing months: $missing\nReady to generate: $ready\nNeeds configuration: $unknown\nAmount to generate: ${Money.format(preview['unbilled_minor'])}',
              ),
              if (rows.isEmpty)
                const Text(
                  'No eligible monthly fees for the selected period and filters.',
                ),
              if (missing == 0 && rows.isNotEmpty)
                const Text(
                  'No missing monthly fees. All eligible monthly fees for this period have already been generated or covered by an approved opening balance.',
                ),
              if (unknown > 0)
                const Text(
                  'Fee configuration required before generation. Unknown tuition is not PKR 0.',
                ),
              if (review)
                const Text(
                  'Opening balances need approved month coverage before generation. Review each opening voucher first.',
                ),
              for (final row in rows)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(
                    '${row['registration_number']} | ${row['full_name'] ?? ''}\n${MaterialLocalizations.of(context).formatMonthYear(DateTime.parse('${row['period']}-01'))}',
                  ),
                  subtitle: Text(_description(row)),
                ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context, false),
          child: Text(missing == 0 ? 'Close' : 'Cancel'),
        ),
        if (ready > 0)
          FilledButton(
            onPressed: unknown > 0 || review
                ? null
                : () => Navigator.pop(context, true),
            child: Text('Generate $ready missing fees'),
          ),
      ],
    );
  }

  String _description(dynamic row) {
    if (row['status'] == 'needs_configuration') {
      return 'Fee configuration required';
    }
    if (row['status'] == 'opening_balance_covered') {
      return 'Covered by opening balance\nVoucher: ${row['voucher_number']}';
    }
    if (row['invoice_id'] == null) {
      return 'Not billed | Ready to generate\nTuition: ${Money.format(row['net_minor'])}';
    }
    final status = switch (row['status']) {
      'paid' => 'Paid',
      'partially_paid' => 'Partially paid',
      'waived_adjusted' => 'Waived / adjusted',
      'overdue' => 'Unpaid (overdue)',
      _ => 'Unpaid',
    };
    return 'Already billed | $status\nVoucher: ${row['voucher_number']}\nTuition: ${Money.format(row['net_minor'])}\nPaid: ${Money.format(row['paid_minor'])}\nBalance: ${Money.format(row['balance_minor'])}';
  }
}
