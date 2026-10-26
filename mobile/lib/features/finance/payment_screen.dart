import '../files/private_file_screen.dart';

import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/money.dart';
import '../../core/operation_id.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';

class PaymentScreen extends StatelessWidget {
  const PaymentScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  Widget build(BuildContext context) => RecordsScreen(
    auth: auth,
    path: 'finance/payments',
    title: 'paymentHistory',
    recordTitle: (p) =>
        p['receipt_number']?.toString() ??
        Strings.of(context).text('pendingVerification'),
    recordSubtitle: (p) =>
        '${p['paid_on']} · ${Money.format(p['amount_minor'])}',
    onOpen: (c, p) async {
      await Navigator.push<void>(
        c,
        MaterialPageRoute(
          builder: (_) =>
              PaymentDetailScreen(auth: auth, id: p['id'] as String),
        ),
      );
    },
  );
}

class PaymentDetailScreen extends StatefulWidget {
  const PaymentDetailScreen({super.key, required this.auth, required this.id});
  final AuthController auth;
  final String id;
  @override
  State<PaymentDetailScreen> createState() => _PaymentDetailScreenState();
}

class _PaymentDetailScreenState extends State<PaymentDetailScreen> {
  Map<String, dynamic>? data;
  String? error;
  bool busy = false;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final value = await widget.auth.call('finance/payments/${widget.id}');
      if (mounted) {
        setState(() {
          data = value;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  Future<void> action(String key) async {
    final s = Strings.of(context);
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(s.text(key)),
        content: Text(s.text('financialConfirm')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: Text(s.text('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: Text(s.text('confirm')),
          ),
        ],
      ),
    );
    if (ok != true) return;
    setState(() => busy = true);
    try {
      await widget.auth.call('finance/payments/${widget.id}/$key', body: {});
      await load();
    } on ApiFailure catch (e) {
      error = e.code;
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> refund() async {
    final key = operationId();
    final allocations = (data!['allocations'] as List)
        .cast<Map<String, dynamic>>();
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => DomainForm(
          title: 'refundPayment',
          fields: [
            FormFieldSpec(
              'refunded_on',
              'refundDate',
              kind: FieldKind.date,
              initial: DateTime.now().toIso8601String().substring(0, 10),
            ),
            const FormFieldSpec(
              'reason',
              'reason',
              kind: FieldKind.multiline,
              maxLength: 500,
            ),
            for (final a in allocations)
              if ((a['amount_minor'] as num) > (a['refunded_minor'] as num))
                FormFieldSpec(
                  'refund_${a['id']}',
                  '${a['voucher_number']} · ${Money.format((a['amount_minor'] as num) - (a['refunded_minor'] as num))}',
                  kind: FieldKind.money,
                  required: false,
                ),
          ],
          onSubmit: (v) async {
            final rows = <Map<String, dynamic>>[];
            for (final a in allocations) {
              final amount = v.remove('refund_${a['id']}') as int?;
              if (amount != null && amount > 0) {
                rows.add({
                  'payment_allocation_id': a['id'],
                  'amount_minor': amount,
                });
              }
            }
            await widget.auth.call(
              'finance/payments/${widget.id}/refund',
              body: {...v, 'request_id': key, 'allocations': rows},
            );
          },
        ),
      ),
    );
    load();
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('paymentDetails'))),
      body: data == null
          ? DataState(error: error, onRetry: load)
          : ListView(
              padding: const EdgeInsets.all(AppSpace.lg),
              children: [
                SurfaceCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        data!['receipt_number']?.toString() ??
                            s.text('pendingVerification'),
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      Text(
                        Money.format(data!['amount_minor']),
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      Text(data!['paid_on'] as String),
                      if (data!['reference'] != null)
                        Text(data!['reference'] as String),
                      AppBadge(
                        label: s.text(data!['status'] as String),
                        status: data!['status'] as String,
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpace.lg),
                for (final a in data!['allocations'] as List)
                  ListTile(
                    title: Text(a['voucher_number'] as String),
                    subtitle: Text(
                      '${s.text('refunded')}: ${Money.format(a['refunded_minor'])}',
                    ),
                    trailing: Text(Money.format(a['amount_minor'])),
                  ),
                if (error != null)
                  StatusBanner(message: s.text(error!), kind: StatusKind.error),
                if (data!['proof_file'] != null)
                  TextButton.icon(
                    onPressed: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => PrivateFileScreen(
                          auth: widget.auth,
                          id: data!['proof_file']['id'] as String,
                          mime: data!['proof_file']['mime'] as String,
                        ),
                      ),
                    ),
                    icon: const Icon(Icons.attach_file),
                    label: Text(s.text('viewAttachment')),
                  ),
                if (widget.auth.can('finance.approve')) ...[
                  if (data!['status'] == 'pending')
                    Wrap(
                      spacing: AppSpace.sm,
                      children: [
                        FilledButton(
                          onPressed: busy ? null : () => action('verify'),
                          child: Text(s.text('verifyPayment')),
                        ),
                        OutlinedButton(
                          onPressed: busy ? null : () => action('cancel'),
                          child: Text(s.text('cancelPayment')),
                        ),
                      ],
                    ),
                  if (data!['status'] == 'verified')
                    OutlinedButton.icon(
                      onPressed: busy ? null : refund,
                      icon: const Icon(Icons.undo),
                      label: Text(s.text('refundPayment')),
                    ),
                ],
                for (final f in data!['refunds'] as List)
                  ListTile(
                    title: Text(f['number'] as String),
                    subtitle: Text('${f['refunded_on']} · ${f['reason']}'),
                    trailing: Text(Money.format(f['amount_minor'])),
                  ),
              ],
            ),
    );
  }
}
