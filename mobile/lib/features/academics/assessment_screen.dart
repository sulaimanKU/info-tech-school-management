import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import '../students/student_form.dart';

class AssessmentScreen extends StatelessWidget {
  const AssessmentScreen({super.key, required this.auth});
  final AuthController auth;
  Future<void> create(BuildContext context) async {
    try {
      final sections = await sectionOptions(auth);
      final result = await auth.call('catalog/subjects');
      if (!context.mounted) return;
      final s = Strings.of(context);
      await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => DomainForm(
            academicAuth: auth,
            title: 'addAssessment',
            fields: [
              FormFieldSpec(
                'section_id',
                'classSection',
                kind: FieldKind.choice,
                options: sections,
              ),
              FormFieldSpec(
                'subject_id',
                'subject',
                kind: FieldKind.choice,
                options: {
                  for (final v in result['items'] as List)
                    (v['id'] as String): v['name'] as String,
                },
              ),
              const FormFieldSpec('title', 'title'),
              FormFieldSpec(
                'type',
                'assessmentType',
                kind: FieldKind.choice,
                options: {
                  for (final k in [
                    'monthly_test',
                    'unit_test',
                    'midterm',
                    'final',
                    'oral',
                    'assignment',
                    'practical',
                  ])
                    k: s.text(k),
                },
              ),
              const FormFieldSpec('date', 'date', kind: FieldKind.date),
              const FormFieldSpec(
                'maximum_marks',
                'maximumMarks',
                kind: FieldKind.integer,
              ),
            ],
            onSubmit: (v) async {
              await auth.call('assessments', body: v);
            },
          ),
        ),
      );
    } on ApiFailure catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(Strings.of(context).text(e.code))),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) => RecordsScreen(
    auth: auth,
    path: 'assessments',
    title: 'assessments',
    recordTitle: (r) => r['title'] as String,
    recordSubtitle: (r) =>
        '${r['date']} · ${Strings.of(context).text(r['type'] as String)}',
    onCreate: auth.can('academics.write') ? create : null,
    onOpen: (c, r) async {
      await Navigator.push<void>(
        c,
        MaterialPageRoute(
          builder: (_) => MarksScreen(auth: auth, id: r['id'] as String),
        ),
      );
    },
  );
}

class MarksScreen extends StatefulWidget {
  const MarksScreen({super.key, required this.auth, required this.id});
  final AuthController auth;
  final String id;
  @override
  State<MarksScreen> createState() => _MarksScreenState();
}

class _MarksScreenState extends State<MarksScreen> {
  Map<String, dynamic>? data;
  String? error;
  bool busy = false;
  final marks = <String, TextEditingController>{},
      remarks = <String, TextEditingController>{};
  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    for (final c in [...marks.values, ...remarks.values]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> load() async {
    try {
      final result = await widget.auth.call('assessments/${widget.id}');
      for (final c in [...marks.values, ...remarks.values]) {
        c.dispose();
      }
      marks.clear();
      remarks.clear();
      for (final s in result['students'] as List) {
        final row = (result['results'] as List)
            .where((v) => v['student_id'] == s['id'])
            .firstOrNull;
        marks[s['id'] as String] = TextEditingController(
          text: row?['obtained_marks']?.toString() ?? '',
        );
        remarks[s['id'] as String] = TextEditingController(
          text: row?['remarks']?.toString() ?? '',
        );
      }
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

  Future<void> save(String status) async {
    setState(() => busy = true);
    try {
      final rows = <Map<String, dynamic>>[];
      for (final id in marks.keys) {
        final value = double.tryParse(marks[id]!.text);
        if (value == null) {
          if (status == 'submitted') throw const ApiFailure('marksInvalid');
          continue;
        }
        if (value < 0 ||
            value > double.parse(data!['maximum_marks'].toString())) {
          throw const ApiFailure('marksInvalid');
        }
        rows.add({
          'student_id': id,
          'obtained_marks': value,
          'remarks': remarks[id]!.text.trim(),
        });
      }
      await widget.auth.call(
        'assessments/${widget.id}/marks',
        method: 'PUT',
        body: {'version': data!['version'], 'status': status, 'results': rows},
      );
      await load();
    } on ApiFailure catch (e) {
      error = e.code;
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> review(String action) async {
    final s = Strings.of(context);
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(s.text(action)),
        content: Text(s.text('reviewConfirm')),
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
      await widget.auth.call(
        'assessments/${widget.id}/review',
        body: {'action': action, 'version': data!['version']},
      );
      await load();
    } on ApiFailure catch (e) {
      error = e.code;
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final editable = data?['status'] == 'draft';
    return Scaffold(
      appBar: AppBar(
        title: Text(data?['title']?.toString() ?? s.text('assessments')),
      ),
      body: data == null
          ? DataState(error: error, onRetry: load)
          : ListView(
              padding: const EdgeInsets.all(AppSpace.lg),
              children: [
                AppBadge(
                  label: s.text(data!['status'] as String),
                  status: data!['status'] as String,
                ),
                const SizedBox(height: AppSpace.lg),
                Text('${s.text('maximumMarks')}: ${data!['maximum_marks']}'),
                if (error != null)
                  StatusBanner(message: s.text(error!), kind: StatusKind.error),
                for (final student in data!['students'] as List)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: AppSpace.sm),
                    child: SurfaceCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            student['full_name'] as String,
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          const SizedBox(height: AppSpace.lg),
                          TextField(
                            controller: marks[student['id']],
                            enabled: editable && !busy,
                            keyboardType: const TextInputType.numberWithOptions(
                              decimal: true,
                            ),
                            decoration: InputDecoration(
                              labelText: s.text('obtainedMarks'),
                            ),
                          ),
                          const SizedBox(height: AppSpace.md),
                          TextField(
                            controller: remarks[student['id']],
                            enabled: editable && !busy,
                            maxLength: 2000,
                            decoration: InputDecoration(
                              labelText: s.text('teacherRemarks'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                if (editable)
                  Wrap(
                    spacing: AppSpace.sm,
                    children: [
                      OutlinedButton(
                        onPressed: busy ? null : () => save('draft'),
                        child: Text(s.text('saveDraft')),
                      ),
                      FilledButton(
                        onPressed: busy ? null : () => save('submitted'),
                        child: Text(s.text('submitReview')),
                      ),
                    ],
                  ),
                if (widget.auth.can('academics.review'))
                  Wrap(
                    spacing: AppSpace.sm,
                    children: [
                      if (data!['status'] == 'submitted')
                        FilledButton(
                          onPressed: busy ? null : () => review('approve'),
                          child: Text(s.text('approve')),
                        ),
                      if (data!['status'] == 'approved')
                        FilledButton(
                          onPressed: busy ? null : () => review('publish'),
                          child: Text(s.text('publish')),
                        ),
                      if (['submitted', 'approved'].contains(data!['status']))
                        OutlinedButton(
                          onPressed: busy ? null : () => review('return'),
                          child: Text(s.text('return')),
                        ),
                    ],
                  ),
              ],
            ),
    );
  }
}
