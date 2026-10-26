import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/academic_filters.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';

class StudentAcademicScreen extends StatefulWidget {
  const StudentAcademicScreen({
    super.key,
    required this.auth,
    required this.studentId,
    required this.kind,
  });
  final AuthController auth;
  final String studentId, kind;
  @override
  State<StudentAcademicScreen> createState() => _StudentAcademicScreenState();
}

class _StudentAcademicScreenState extends State<StudentAcademicScreen> {
  Map<String, dynamic>? data;
  String? error;
  Map<String, String> filters = {};
  Future<void> filter() async {
    final value = await showFilterSheet(
      context,
      widget.auth,
      widget.kind == 'attendance'
          ? const FilterSpec(
              statuses: [
                'present',
                'absent',
                'late',
                'excused',
                'school_holiday',
              ],
            )
          : const FilterSpec(
              extra: {
                'type': [
                  'monthly_test',
                  'unit_test',
                  'midterm',
                  'final',
                  'oral',
                  'assignment',
                  'practical',
                ],
              },
            ),
      filters,
    );
    if (value != null && mounted) {
      setState(() => filters = value);
      await load();
    }
  }

  String month = DateTime.now().toIso8601String().substring(0, 7);
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final value = await widget.auth.call(
        Uri(
          path: 'students/${widget.studentId}/${widget.kind}',
          queryParameters: {
            ...filters,
            if (widget.kind == 'attendance') 'month': month,
          },
        ).toString(),
      );
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

  Future<void> addBehavior() async {
    final s = Strings.of(context);
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => DomainForm(
          title: 'behavior',
          fields: [
            const FormFieldSpec('date', 'date', kind: FieldKind.date),
            FormFieldSpec(
              'area',
              'observationArea',
              kind: FieldKind.choice,
              options: {
                for (final k in [
                  'punctuality',
                  'participation',
                  'homework_completion',
                  'respect',
                  'cooperation',
                  'cleanliness',
                  'responsibility',
                  'confidence',
                  'learning_habits',
                  'improvement',
                ])
                  k: s.text(k),
              },
            ),
            FormFieldSpec(
              'category',
              'category',
              kind: FieldKind.choice,
              options: {
                for (final k in [
                  'exceptional',
                  'excellent',
                  'good',
                  'satisfactory',
                  'improving',
                  'needs_support',
                ])
                  k: s.text(k),
              },
            ),
            const FormFieldSpec(
              'observation',
              'constructiveObservation',
              kind: FieldKind.multiline,
              maxLength: 3000,
            ),
            const FormFieldSpec(
              'parent_visible',
              'parentVisibleAfterReview',
              kind: FieldKind.boolean,
              initial: false,
            ),
          ],
          onSubmit: (v) async {
            await widget.auth.call(
              'students/${widget.studentId}/behavior',
              body: v,
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
      appBar: AppBar(
        title: Text(s.text(widget.kind)),
        actions: [
          if (['attendance', 'assessments'].contains(widget.kind))
            IconButton(
              key: const Key('open-filters'),
              tooltip: s.text('filters'),
              onPressed: filter,
              icon: Badge(
                isLabelVisible: filters.isNotEmpty,
                child: const Icon(Icons.filter_alt_outlined),
              ),
            ),
          if (widget.kind == 'attendance')
            IconButton(
              tooltip: s.text('filterDate'),
              onPressed: () async {
                final d = await showDatePicker(
                  context: context,
                  initialDate: DateTime.parse('$month-01'),
                  firstDate: DateTime(2020),
                  lastDate: DateTime.now(),
                );
                if (d != null) {
                  month = d.toIso8601String().substring(0, 7);
                  load();
                }
              },
              icon: const Icon(Icons.date_range),
            ),
        ],
      ),
      floatingActionButton:
          widget.kind == 'behavior' && widget.auth.can('academics.write')
          ? FloatingActionButton(
              onPressed: addBehavior,
              child: const Icon(Icons.add),
            )
          : null,
      body: error != null
          ? DataState(error: error, onRetry: load)
          : data == null
          ? const DataState()
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpace.lg),
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  if (widget.kind == 'attendance')
                    SurfaceCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            month,
                            style: Theme.of(context).textTheme.titleLarge,
                          ),
                          Text(
                            '${s.text('attendancePercentage')}: ${data!['attendance_percentage'] ?? '—'}%',
                          ),
                          Text(
                            '${s.text('schoolDays')}: ${data!['school_days']} · ${s.text('recordedDays')}: ${data!['recorded_days']}',
                          ),
                          if (data!['provisional'] == true)
                            StatusBanner(
                              message: s.text('attendanceProvisional'),
                            ),
                        ],
                      ),
                    ),
                  if (widget.kind == 'assessments')
                    SurfaceCard(
                      child: Text(
                        '${s.text('overallPerformance')}: ${data!['overall_percentage'] ?? '—'}%',
                      ),
                    ),
                  const SizedBox(height: AppSpace.lg),
                  if ((data!['items'] as List).isEmpty)
                    const DataState(empty: true),
                  for (final item in data!['items'] as List)
                    Padding(
                      padding: const EdgeInsets.only(bottom: AppSpace.md),
                      child: SurfaceCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              (item['subject_name'] ??
                                      s.text(
                                        item['area']?.toString() ??
                                            item['status']?.toString() ??
                                            '',
                                      ))
                                  .toString(),
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            Text(item['date'] as String),
                            if (widget.kind == 'assessments') ...[
                              Text(item['title'] as String),
                              Text(
                                '${item['obtained_marks']} / ${item['maximum_marks']} · ${item['percentage']}% · ${item['grade']}',
                              ),
                              if (item['remarks'] != null)
                                Text(item['remarks'] as String),
                            ],
                            if (widget.kind == 'behavior') ...[
                              AppBadge(
                                label: s.text(item['category'] as String),
                              ),
                              const SizedBox(height: AppSpace.sm),
                              Text(item['observation'] as String),
                              if (widget.auth.can('academics.review') &&
                                  item['status'] != 'published')
                                TextButton(
                                  onPressed: () async {
                                    try {
                                      await widget.auth.call(
                                        'behavior/${item['id']}/publish',
                                        body: {},
                                      );
                                      load();
                                    } on ApiFailure catch (e) {
                                      if (mounted) {
                                        setState(() => error = e.code);
                                      }
                                    }
                                  },
                                  child: Text(s.text('publish')),
                                ),
                            ],
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
    );
  }
}
