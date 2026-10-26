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

class FinanceHubScreen extends StatelessWidget {
  const FinanceHubScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final entries = [
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
    ];
    return Scaffold(
      appBar: AppBar(title: Text(s.text('finance'))),
      body: ListView(
        padding: const EdgeInsets.all(AppSpace.lg),
        children: [
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
