import 'package:flutter/material.dart';

import '../../l10n/strings.dart';
import '../../design/app_theme.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import '../students/student_form.dart';

class CoordinatorScreen extends StatelessWidget {
  const CoordinatorScreen({super.key, required this.auth});
  final AuthController auth;
  Future<void> form(
    BuildContext context, {
    Map<String, dynamic>? existing,
  }) async {
    final sections = await sectionOptions(auth);
    if (!context.mounted) return;
    final s = Strings.of(context);
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => DomainForm(
          title: 'coordinator',
          fields: [
            FormFieldSpec(
              'section_id',
              'classSection',
              kind: FieldKind.choice,
              required: false,
              options: sections,
              initial: existing?['section_id'],
            ),
            FormFieldSpec(
              'kind',
              'category',
              kind: FieldKind.choice,
              initial: existing?['kind'],
              options: {
                for (final k in [
                  'syllabus',
                  'learning_gap',
                  'classroom',
                  'follow_up',
                  'issue',
                  'parent_communication',
                ])
                  k: s.text(k),
              },
            ),
            FormFieldSpec('title', 'title', initial: existing?['title']),
            FormFieldSpec(
              'notes',
              'notes',
              kind: FieldKind.multiline,
              maxLength: 5000,
              initial: existing?['notes'],
            ),
            FormFieldSpec(
              'progress_percent',
              'syllabusProgress',
              kind: FieldKind.integer,
              required: false,
              initial: existing?['progress_percent'],
            ),
            FormFieldSpec(
              'follow_up_on',
              'followUpDate',
              kind: FieldKind.date,
              required: false,
              initial: existing?['follow_up_on'],
            ),
            FormFieldSpec(
              'status',
              'status',
              kind: FieldKind.choice,
              initial: existing?['status'] ?? 'open',
              options: {
                for (final k in ['open', 'in_progress', 'resolved'])
                  k: s.text(k),
              },
            ),
          ],
          onSubmit: (v) async {
            await auth.call(
              existing == null
                  ? 'coordinator'
                  : 'coordinator/${existing['id']}',
              method: existing == null ? 'POST' : 'PATCH',
              body: v,
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
      path: 'coordinator',
      title: 'coordinator',
      recordTitle: (r) => r['title'] as String,
      recordSubtitle: (r) => '${s.text(r['kind'] as String)} · ${r['notes']}',
      onCreate: (c) => form(c),
      onOpen: (c, r) => form(c, existing: r),
      summary: (data) => Padding(
        padding: const EdgeInsets.only(bottom: AppSpace.xl),
        child: SurfaceCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                s.text('reviewQueue'),
                style: Theme.of(context).textTheme.titleLarge,
              ),
              Text(
                '${s.text('submittedAssessments')}: ${data['submitted_assessments']}',
              ),
              Text(
                '${s.text('unreviewedDiaries')}: ${data['unreviewed_diaries']}',
              ),
              const SizedBox(height: AppSpace.lg),
              for (final row in data['attendance_today'] as List)
                Text('${row['name']} · ${s.text(row['status'] as String)}'),
            ],
          ),
        ),
      ),
    );
  }
}
