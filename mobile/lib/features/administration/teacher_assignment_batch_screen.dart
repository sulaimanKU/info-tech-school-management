import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/academic_filters.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/polished_components.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';

class TeacherAssignmentBatchScreen extends StatefulWidget {
  const TeacherAssignmentBatchScreen({
    super.key,
    required this.auth,
    required this.teacher,
  });
  final AuthController auth;
  final Map<String, dynamic> teacher;
  @override
  State<TeacherAssignmentBatchScreen> createState() =>
      _TeacherAssignmentBatchScreenState();
}

class _AssignmentDraft {
  _AssignmentDraft(this.id, String session)
    : values = {'academic_session_id': session};
  final int id;
  Map<String, String> values;
  bool classTeacher = false;
}

class _TeacherAssignmentBatchScreenState
    extends State<TeacherAssignmentBatchScreen> {
  final drafts = <_AssignmentDraft>[];
  Map<String, String> sessions = {};
  String? session, error;
  bool loading = true, busy = false;
  int nextId = 0;
  int sessionRevision = 0;

  bool get allowed =>
      widget.auth.can('school.manage') || widget.auth.can('assignments.manage');

  @override
  void initState() {
    super.initState();
    if (allowed) load();
  }

  Future<void> load() async {
    try {
      final data = await widget.auth.call('academic-filter-options');
      if (!mounted) return;
      setState(() {
        sessions = {
          for (final row in data['sessions'] as List)
            row['id'] as String: row['name'] as String,
        };
        final current = data['current_academic_session_id'] as String?;
        session = sessions.containsKey(current) ? current : null;
        if (session != null && drafts.isEmpty) {
          drafts.add(_AssignmentDraft(nextId++, session!));
        }
        error = null;
      });
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> changeSession(String? value) async {
    if (value == session) return;
    if (drafts.any((d) => d.values.containsKey('section_id'))) {
      final confirm = await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: Text(Strings.of(c).text('changeAssignmentSession')),
          content: Text(Strings.of(c).text('clearAssignmentDrafts')),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(c, false),
              child: Text(Strings.of(c).text('cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(c, true),
              child: Text(Strings.of(c).text('confirm')),
            ),
          ],
        ),
      );
      if (!mounted) return;
      if (confirm != true) {
        setState(() => sessionRevision++);
        return;
      }
    }
    setState(() {
      session = value;
      drafts.clear();
      error = null;
      if (value != null) drafts.add(_AssignmentDraft(nextId++, value));
    });
  }

  Future<void> save() async {
    if (busy) {
      return;
    }
    final seen = <String>{};
    if (session == null ||
        drafts.isEmpty ||
        drafts.any(
          (d) =>
              d.values['class_id'] == null ||
              d.values['section_id'] == null ||
              (d.values['subject_id'] == null && !d.classTeacher),
        )) {
      setState(() => error = 'completeAssignments');
      return;
    }
    for (final d in drafts) {
      if (!seen.add(
        '${d.values['section_id']}|${d.values['subject_id'] ?? ''}',
      )) {
        setState(() => error = 'duplicateAssignment');
        return;
      }
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.auth.call(
        'teaching-assignments',
        body: {
          'staff_profile_id': widget.teacher['id'],
          'academic_session_id': session,
          'assignments': [
            for (final d in drafts)
              {
                'section_id': d.values['section_id'],
                'subject_id': d.values['subject_id'],
                'is_class_teacher': d.classTeacher,
              },
          ],
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiFailure catch (e) {
      if (mounted) {
        setState(
          () => error =
              e.fields.values
                  .whereType<List>()
                  .firstOrNull
                  ?.firstOrNull
                  ?.toString() ??
              e.code,
        );
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    if (!allowed) return const Scaffold(body: DataState(error: 'forbidden'));
    return Scaffold(
      appBar: AppBar(title: Text(s.text('teachingAssignments'))),
      body: loading
          ? const DataState()
          : PageBody(
              child: ListView(
                padding: const EdgeInsets.all(AppSpace.lg),
                children: [
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const CircleAvatar(
                      child: Icon(Icons.person_outline),
                    ),
                    title: Text(widget.teacher['name'] as String),
                    subtitle: Text(
                      [
                        widget.teacher['employee_number'],
                        widget.teacher['account_code'],
                      ].whereType<String>().join(' · '),
                    ),
                  ),
                  Text(s.text('multipleAssignmentHelp')),
                  const SizedBox(height: AppSpace.lg),
                  if (sessions.isEmpty && error == null)
                    StatusBanner(message: s.text('noAssignmentSections')),
                  ScopedChoice(
                    key: ValueKey('session-choice-$sessionRevision'),
                    label: 'academicSession',
                    options: sessions,
                    value: session,
                    enabled: !busy,
                    onChanged: changeSession,
                  ),
                  const SizedBox(height: AppSpace.lg),
                  for (final d in drafts)
                    Padding(
                      key: ValueKey('draft-${d.id}'),
                      padding: const EdgeInsets.only(bottom: AppSpace.lg),
                      child: SurfaceCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    '${s.text('assignment')} ${drafts.indexOf(d) + 1}',
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium,
                                  ),
                                ),
                                IconButton(
                                  key: ValueKey('remove-draft-${d.id}'),
                                  tooltip: s.text('removeAssignment'),
                                  onPressed: busy
                                      ? null
                                      : () => setState(() => drafts.remove(d)),
                                  icon: const Icon(Icons.remove_circle_outline),
                                ),
                              ],
                            ),
                            AcademicSelection(
                              key: ValueKey('draft-fields-${d.id}'),
                              auth: widget.auth,
                              initial: d.values,
                              subject: true,
                              showSession: false,
                              enabled: !busy,
                              onChanged: (v) => setState(() {
                                d.values = v;
                                error = null;
                              }),
                            ),
                            SwitchListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(s.text('classTeacher')),
                              value: d.classTeacher,
                              onChanged: busy
                                  ? null
                                  : (v) => setState(() => d.classTeacher = v),
                            ),
                          ],
                        ),
                      ),
                    ),
                  if (session != null)
                    OutlinedButton.icon(
                      key: const Key('add-assignment'),
                      onPressed: busy || drafts.length >= 50
                          ? null
                          : () => setState(
                              () => drafts.add(
                                _AssignmentDraft(nextId++, session!),
                              ),
                            ),
                      icon: const Icon(Icons.add),
                      label: Text(s.text('addAssignment')),
                    ),
                  if (error != null)
                    Padding(
                      padding: const EdgeInsets.symmetric(
                        vertical: AppSpace.lg,
                      ),
                      child: StatusBanner(
                        message: s.text(error!),
                        kind: StatusKind.error,
                      ),
                    ),
                  if (error != null && sessions.isEmpty)
                    TextButton(onPressed: load, child: Text(s.text('retry'))),
                  const SizedBox(height: AppSpace.lg),
                  FilledButton.icon(
                    key: const Key('save-assignments'),
                    onPressed: busy || drafts.isEmpty ? null : save,
                    icon: busy
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.check),
                    label: Text(s.text('saveAssignments')),
                  ),
                  const SizedBox(height: AppSpace.xl),
                ],
              ),
            ),
    );
  }
}
