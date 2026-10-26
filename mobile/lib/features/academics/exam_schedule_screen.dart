import 'package:flutter/material.dart';

import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/records_screen.dart';
import '../auth/auth_controller.dart';
import '../students/student_form.dart';
import '../reports/report_screen.dart';

class ExamScheduleScreen extends StatelessWidget {
  const ExamScheduleScreen({super.key, required this.auth, this.studentId});
  final AuthController auth;
  final String? studentId;
  Future<void> edit(
    BuildContext context, [
    Map<String, dynamic>? current,
  ]) async {
    final sections = await sectionOptions(auth);
    final subjects = await auth.call('catalog/subjects');
    if (!context.mounted) return;
    final s = Strings.of(context);
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => DomainForm(
          academicAuth: auth,
          title: 'examSchedules',
          fields: [
            FormFieldSpec(
              'section_id',
              'section',
              kind: FieldKind.choice,
              options: sections,
              initial: current?['section_id'],
            ),
            FormFieldSpec(
              'subject_id',
              'subject',
              kind: FieldKind.choice,
              options: {
                for (final v in subjects['items'] as List)
                  v['id'] as String: v['name'] as String,
              },
              initial: current?['subject_id'],
            ),
            FormFieldSpec('title', 'title', initial: current?['title']),
            FormFieldSpec(
              'date',
              'date',
              kind: FieldKind.date,
              initial: current?['date'],
            ),
            FormFieldSpec(
              'starts_at',
              'examStarts',
              initial: (current?['starts_at'] as String?)?.substring(0, 5),
            ),
            FormFieldSpec(
              'ends_at',
              'examEnds',
              initial: (current?['ends_at'] as String?)?.substring(0, 5),
            ),
            FormFieldSpec(
              'room',
              'room',
              required: false,
              initial: current?['room'],
            ),
            FormFieldSpec(
              'status',
              'status',
              kind: FieldKind.choice,
              options: {
                for (final k in ['draft', 'published', 'cancelled'])
                  k: s.text(k),
              },
              initial: current?['status'] ?? 'draft',
            ),
          ],
          onSubmit: (v) async {
            await auth.call(
              current == null
                  ? 'exam-schedules'
                  : 'exam-schedules/${current['id']}',
              method: current == null ? 'POST' : 'PUT',
              body: {...v, 'version': current?['version'] ?? 0},
            );
          },
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => RecordsScreen(
    auth: auth,
    path: 'exam-schedules${studentId == null ? '' : '?student_id=$studentId'}',
    title: 'examSchedules',
    recordTitle: (v) => '${v['title']} · ${v['subject_name']}',
    recordSubtitle: (v) =>
        '${v['date']} · ${v['starts_at']}–${v['ends_at']} · ${v['class_name']} ${v['section_name']} · ${Strings.of(context).text(v['status'] as String)}',
    onCreate: auth.can('academics.review') ? (c) => edit(c) : null,
    onOpen: auth.can('academics.review') ? (c, v) => edit(c, v) : null,
    summary: (_) => TextButton.icon(
      onPressed: () => openSchoolReport(context, auth, 'exam_schedule', {
        'student_id': ?studentId,
      }),
      icon: const Icon(Icons.picture_as_pdf_outlined),
      label: Text(Strings.of(context).text('downloadPdf')),
    ),
  );
}
