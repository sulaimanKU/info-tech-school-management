import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/academic_filters.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';

class AssessmentForm extends StatefulWidget {
  const AssessmentForm({super.key, required this.auth, this.initialSection});
  final AuthController auth;
  final String? initialSection;
  @override
  State<AssessmentForm> createState() => _AssessmentFormState();
}

class _AssessmentFormState extends State<AssessmentForm> {
  final form = GlobalKey<FormState>();
  final title = TextEditingController(),
      maximum = TextEditingController(text: '100');
  Map<String, String> selection = {}, teachers = {};
  String date = DateTime.now().toIso8601String().substring(0, 10),
      type = 'monthly_test';
  String? teacher, error;
  bool busy = false, loadingTeachers = false;
  int generation = 0;
  bool get chooseTeacher =>
      widget.auth.can('academics.review') &&
      (widget.auth.can('school.manage') ||
          widget.auth.can('assignments.manage'));
  @override
  void initState() {
    super.initState();
    if (widget.initialSection != null) {
      selection['section_id'] = widget.initialSection!;
    }
  }

  @override
  void dispose() {
    title.dispose();
    maximum.dispose();
    super.dispose();
  }

  Future<void> loadTeachers() async {
    if (!chooseTeacher) return;
    final revision = ++generation;
    setState(() {
      teachers = {};
      teacher = null;
      loadingTeachers = true;
    });
    try {
      if (selection['section_id'] == null || selection['subject_id'] == null) {
        return;
      }
      final data = await widget.auth.call(
        Uri(
          path: 'teaching-assignments',
          queryParameters: {...selection, 'on_date': date, 'is_active': '1'},
        ).toString(),
      );
      if (mounted && revision == generation) {
        setState(() {
          teachers = {
            for (final row in data['data'] as List)
              row['teacher_user_id'] as String:
                  '${row['employee_number']} | ${row['teacher_name']}',
          };
          teacher = teachers.keys.firstOrNull;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted && revision == generation) setState(() => error = e.code);
    } finally {
      if (mounted && revision == generation) {
        setState(() => loadingTeachers = false);
      }
    }
  }

  Future<void> save() async {
    if (busy || !form.currentState!.validate()) return;
    if (selection['section_id'] == null ||
        selection['subject_id'] == null ||
        (chooseTeacher && teacher == null)) {
      setState(() => error = 'teacherAssignmentRequired');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.auth.call(
        'assessments',
        body: {
          'section_id': selection['section_id'],
          'subject_id': selection['subject_id'],
          'date': date,
          'title': title.text.trim(),
          'type': type,
          'maximum_marks': num.parse(maximum.text),
          if (chooseTeacher) 'teacher_user_id': teacher,
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
    return Scaffold(
      appBar: AppBar(title: Text(s.text('addAssessment'))),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpace.lg),
        child: Form(
          key: form,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              IgnorePointer(
                ignoring: busy,
                child: AcademicSelection(
                  preferCurrentSession: true,
                  auth: widget.auth,
                  initial: selection,
                  subject: true,
                  onChanged: (v) {
                    setState(() => selection = v);
                    loadTeachers();
                  },
                ),
              ),
              if (chooseTeacher)
                ScopedChoice(
                  label: 'chooseTeacher',
                  options: teachers,
                  value: teacher,
                  enabled: !busy && !loadingTeachers,
                  onChanged: (v) => setState(() => teacher = v),
                ),
              if (loadingTeachers) const LinearProgressIndicator(),
              TextFormField(
                controller: title,
                maxLength: 200,
                enabled: !busy,
                decoration: InputDecoration(labelText: s.text('title')),
                validator: (v) => v == null || v.trim().isEmpty
                    ? s.text('fieldRequired')
                    : null,
              ),
              ScopedChoice(
                label: 'assessmentType',
                options: {
                  for (final t in [
                    'monthly_test',
                    'unit_test',
                    'midterm',
                    'final',
                    'oral',
                    'assignment',
                    'practical',
                  ])
                    t: s.text(t),
                },
                value: type,
                enabled: !busy,
                onChanged: (v) {
                  if (v != null) setState(() => type = v);
                },
              ),
              OutlinedButton.icon(
                onPressed: busy
                    ? null
                    : () async {
                        final d = await showDatePicker(
                          context: context,
                          initialDate: DateTime.parse(date),
                          firstDate: DateTime(2000),
                          lastDate: DateTime(2200),
                        );
                        if (d != null && mounted) {
                          setState(
                            () => date = d.toIso8601String().substring(0, 10),
                          );
                          await loadTeachers();
                        }
                      },
                icon: const Icon(Icons.calendar_today),
                label: Text(date),
              ),
              TextFormField(
                controller: maximum,
                enabled: !busy,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: InputDecoration(labelText: s.text('maximumMarks')),
                validator: (v) {
                  final n = num.tryParse(v ?? '');
                  return n == null || n <= 0 || n > 100000
                      ? s.text('marksInvalid')
                      : null;
                },
              ),
              if (error != null)
                StatusBanner(message: s.text(error!), kind: StatusKind.error),
              const SizedBox(height: AppSpace.lg),
              FilledButton(
                onPressed: busy || loadingTeachers ? null : save,
                child: Text(s.text('save')),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
