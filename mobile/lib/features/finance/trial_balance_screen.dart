import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/money.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';

class TrialBalanceScreen extends StatefulWidget {
  const TrialBalanceScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<TrialBalanceScreen> createState() => _TrialBalanceScreenState();
}

class _TrialBalanceScreenState extends State<TrialBalanceScreen> {
  String from = '${DateTime.now().year}-01-01',
      to = DateTime.now().toIso8601String().substring(0, 10);
  Map<String, dynamic>? data;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final result = await widget.auth.call(
        'finance/trial-balance?from=$from&to=$to',
      );
      if (mounted) {
        setState(() {
          data = result;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('trialBalance'))),
      body: ListView(
        padding: const EdgeInsets.all(AppSpace.lg),
        children: [
          Wrap(
            spacing: AppSpace.lg,
            children: [
              for (final isFrom in [true, false])
                OutlinedButton.icon(
                  onPressed: () async {
                    final d = await showDatePicker(
                      context: context,
                      initialDate: DateTime.parse(isFrom ? from : to),
                      firstDate: DateTime(2020),
                      lastDate: DateTime(2100),
                    );
                    if (d != null) {
                      isFrom
                          ? from = d.toIso8601String().substring(0, 10)
                          : to = d.toIso8601String().substring(0, 10);
                      load();
                    }
                  },
                  icon: const Icon(Icons.calendar_today_outlined),
                  label: Text(isFrom ? from : to),
                ),
            ],
          ),
          const SizedBox(height: AppSpace.lg),
          if (error != null)
            DataState(error: error, onRetry: load)
          else if (data == null)
            const DataState()
          else ...[
            SurfaceCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${s.text('debits')}: ${Money.format(data!['debit_minor'])}',
                  ),
                  Text(
                    '${s.text('credits')}: ${Money.format(data!['credit_minor'])}',
                  ),
                  AppBadge(
                    label: s.text(
                      data!['balanced'] == true ? 'balanced' : 'unbalanced',
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpace.lg),
            for (final line in data!['items'] as List)
              ListTile(
                title: Text(line['name'] as String),
                subtitle: Text(
                  '${s.text('debits')}: ${Money.format(line['debit_minor'])}\n${s.text('credits')}: ${Money.format(line['credit_minor'])}',
                ),
              ),
          ],
        ],
      ),
    );
  }
}
