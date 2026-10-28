import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/polished_components.dart';
import '../auth/auth_controller.dart';
import 'invoice_screen.dart';
import 'payment_screen.dart';
import 'expense_screen.dart';
import 'payroll_screen.dart';
import 'discount_screen.dart';
import 'finance_configuration_screen.dart';
import 'trial_balance_screen.dart';
import 'financial_forms.dart';
import 'student_fee_screen.dart';

class FinanceHubScreen extends StatelessWidget {
  const FinanceHubScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final entries = [
      if (auth.can('finance.read')) ...[
      ('feeVouchers', Icons.receipt_long_outlined, InvoiceScreen(auth: auth)),
      ('paymentHistory', Icons.payments_outlined, PaymentScreen(auth: auth)),
      ('discounts', Icons.discount_outlined, DiscountScreen(auth: auth)),
      ('expenses', Icons.shopping_bag_outlined, ExpenseScreen(auth: auth)),
      ('payroll', Icons.account_balance_outlined, PayrollScreen(auth: auth)),
      ('trialBalance', Icons.balance_outlined, TrialBalanceScreen(auth: auth)),
      (
        'financeConfiguration',
        Icons.settings_outlined,
        FinanceConfigurationScreen(auth: auth),
      ),
      ],
    ];
    return Scaffold(
      appBar: AppBar(title: Text(s.text('finance'))),
      body: ListView(
        padding: const EdgeInsets.all(AppSpace.lg),
        children: [
          if (auth.can('finance.read') && auth.can('students.read'))
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpace.lg),
              child: ActionTile(icon: Icons.person_search, title: auth.can('finance.write') ? 'Student fees / Record payment' : 'Student fees', onTap: () async {
                final student = await chooseStudent(context, auth);
                if (student == null || !context.mounted) return;
                await Navigator.push<void>(context, MaterialPageRoute(builder: (_) => StudentFeeScreen(auth: auth, studentId: student['id'] as String)));
              }),
            ),
          for (final entry in entries)
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpace.lg),
              child: ActionTile(
                icon: entry.$2,
                title: s.text(entry.$1),
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute<void>(builder: (_) => entry.$3),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
