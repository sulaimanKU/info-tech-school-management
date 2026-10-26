import 'package:flutter/material.dart';

import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import '../students/student_list_screen.dart';

class BiometricScreen extends StatelessWidget {
  const BiometricScreen({super.key, required this.auth});
  final AuthController auth;
  Future<void> add(BuildContext context) async {
    final student = await Navigator.push<Map<String, dynamic>>(
      context,
      MaterialPageRoute(
        builder: (_) => StudentListScreen(auth: auth, selection: true),
      ),
    );
    if (student == null || !context.mounted) return;
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => DomainForm(
          title: 'biometricMapping',
          fields: const [
            FormFieldSpec('device_code', 'deviceCode'),
            FormFieldSpec('external_id', 'deviceStudentId'),
          ],
          onSubmit: (v) async {
            await auth.call(
              'biometric/mappings',
              body: {...v, 'student_id': student['id']},
            );
          },
        ),
      ),
    );
  }

  Future<void> report(BuildContext context) async {
    final now = DateTime.now();
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => DomainForm(
          title: 'biometricSummary',
          fields: [
            FormFieldSpec(
              'month',
              'month',
              initial: '${now.year}-${now.month.toString().padLeft(2, '0')}',
            ),
          ],
          onSubmit: (v) async {
            final mappings = await auth.call('biometric/mappings');
            final names = {
              for (final row in mappings['items'] as List)
                row['student_id'] as String:
                    '${row['full_name']} · ${row['registration_number']}',
            };
            if (!context.mounted) return;
            await Navigator.push(
              context,
              MaterialPageRoute<void>(
                builder: (c) => RecordsScreen(
                  auth: auth,
                  path: 'biometric/summary?month=${v['month']}',
                  title: 'biometricSummary',
                  recordTitle: (r) =>
                      '${r['date']} · ${names[r['student_id']] ?? Strings.of(c).text('students')}',
                  recordSubtitle: (r) =>
                      '${r['first_punch']} — ${r['last_punch']} · ${Strings.of(c).text(r['needs_review'] == true ? 'needsReview' : 'pairedPunches')}',
                  summary: (r) => StatusBanner(message: r['notice'] as String),
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return RecordsScreen(
      auth: auth,
      path: 'biometric/mappings',
      title: 'biometricMapping',
      recordTitle: (r) => '${r['full_name']} · ${r['registration_number']}',
      recordSubtitle: (r) => '${r['device_code']} · ${r['external_id']}',
      onCreate: auth.can('students.manage') ? add : null,
      summary: (_) => TextButton.icon(
        onPressed: () => report(context),
        icon: const Icon(Icons.fact_check_outlined),
        label: Text(s.text('biometricSummary')),
      ),
      onOpen: auth.can('students.manage')
          ? (c, r) async {
              final yes = await showDialog<bool>(
                context: c,
                builder: (d) => AlertDialog(
                  title: Text(s.text('revokeMapping')),
                  content: Text(s.text('revokeMappingBody')),
                  actions: [
                    TextButton(
                      onPressed: () => Navigator.pop(d, false),
                      child: Text(s.text('cancel')),
                    ),
                    FilledButton(
                      onPressed: () => Navigator.pop(d, true),
                      child: Text(s.text('confirm')),
                    ),
                  ],
                ),
              );
              if (yes == true) {
                await auth.call(
                  'biometric/mappings/${r['id']}',
                  method: 'DELETE',
                );
              }
            }
          : null,
    );
  }
}
