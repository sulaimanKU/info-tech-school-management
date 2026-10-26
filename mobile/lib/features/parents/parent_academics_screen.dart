import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../design/semantic_tokens.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/polished_components.dart';
import '../auth/auth_controller.dart';
import '../reports/report_screen.dart';
import 'parent_resource.dart';

class ParentAcademicsScreen extends StatefulWidget {
  const ParentAcademicsScreen({
    super.key,
    required this.auth,
    required this.studentId,
  });
  final AuthController auth;
  final String studentId;
  @override
  State<ParentAcademicsScreen> createState() => _ParentAcademicsScreenState();
}

class _ParentAcademicsScreenState extends State<ParentAcademicsScreen> {
  String period = 'latest';
  @override
  void didUpdateWidget(covariant ParentAcademicsScreen old) {
    super.didUpdateWidget(old);
    if (old.studentId != widget.studentId) period = 'latest';
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(Strings.of(context).text('academicProgress'))),
    body: ParentResource(
      auth: widget.auth,
      path: Uri(
        path: 'students/${widget.studentId}/assessments',
        queryParameters: {'exam_key': period},
      ).toString(),
      builder: (context, data) {
        final s = Strings.of(context);
        final periods = data['periods'] as List? ?? [];
        final selected = data['selected_period'] as Map?;
        final items = data['items'] as List? ?? [];
        final p = AppPalette.of(context);
        if (periods.isEmpty) {
          return SurfaceCard(child: Text(s.text('noPublishedResults')));
        }
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            DropdownButtonFormField<String>(
              key: ValueKey('assessment-selector-${selected?['key']}'),
              initialValue: selected?['key'] as String?,
              isExpanded: true,
              decoration: InputDecoration(
                labelText: s.text('assessmentPeriod'),
              ),
              items: [
                for (final value in periods)
                  DropdownMenuItem(
                    value: value['key'] as String,
                    child: Text(
                      '${value['title']} · ${value['date']} · ${s.text(value['type'] as String)}',
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
              ],
              onChanged: (v) {
                if (v != null) setState(() => period = v);
              },
            ),
            const SizedBox(height: AppSpace.lg),
            if (selected != null)
              SurfaceCard(
                accent: p.education,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      s.text('overallPerformance'),
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    Text(
                      selected['percentage'] == null
                          ? s.text('notRecorded')
                          : '${selected['percentage']}%',
                      style: Theme.of(context).textTheme.headlineLarge,
                    ),
                    Text(
                      '${selected['obtained_marks']} / ${selected['maximum_marks']} · ${s.text('grade')} ${selected['grade'] ?? s.text('notRecorded')}',
                    ),
                    Text(
                      '${selected['session_name']} · ${selected['class_name']} ${selected['section_name']}',
                    ),
                    Text('${selected['subjects']} ${s.text('subjects')}'),
                    const SizedBox(height: AppSpace.sm),
                    FilledButton.icon(
                      key: const Key('parent-report-card'),
                      onPressed: () =>
                          openSchoolReport(context, widget.auth, 'academic', {
                            'student_id': widget.studentId,
                            'exam_key': selected['key'] as String,
                          }),
                      icon: const Icon(Icons.picture_as_pdf_outlined),
                      label: Text(s.text('viewReportCard')),
                    ),
                  ],
                ),
              ),
            const SizedBox(height: AppSpace.xl),
            SectionHeader(title: s.text('subjectPerformance')),
            for (final row in items)
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpace.md),
                child: SurfaceCard(
                  accent: p.education,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        row['subject_name'].toString(),
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      Text(
                        '${row['obtained_marks']} / ${row['maximum_marks']} · ${row['percentage']}%',
                      ),
                      const SizedBox(height: AppSpace.sm),
                      LinearProgressIndicator(
                        value:
                            ((num.tryParse(row['percentage'].toString()) ?? 0) /
                                    100)
                                .clamp(0, 1),
                        color: p.education,
                      ),
                      const SizedBox(height: AppSpace.sm),
                      AppBadge(
                        label: '${s.text('grade')} ${row['grade']}',
                        status: 'published',
                      ),
                      Text('${row['title']} · ${row['date']}'),
                      if (row['remarks'] != null &&
                          row['remarks'].toString().trim().isNotEmpty) ...[
                        const Divider(),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Icon(Icons.format_quote, color: p.education),
                            const SizedBox(width: AppSpace.sm),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    s.text('teacherRemarks'),
                                    style: Theme.of(context)
                                        .textTheme
                                        .labelLarge,
                                  ),
                                  Text(row['remarks'].toString()),
                                  if (row['teacher_name'] != null)
                                    Text(
                                      row['teacher_name'].toString(),
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall,
                                    ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            if ((data['trend'] as List? ?? []).length > 1) ...[
              const SizedBox(height: AppSpace.lg),
              SectionHeader(
                title: s.text('publishedTrend'),
                description: s.text('comparableAssessments'),
              ),
              for (final point in data['trend'] as List)
                Padding(
                  padding: const EdgeInsets.only(bottom: AppSpace.md),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${point['title']} · ${point['date']} · ${point['percentage']}%',
                      ),
                      const SizedBox(height: AppSpace.xs),
                      LinearProgressIndicator(
                        value:
                            ((num.tryParse(point['percentage'].toString()) ??
                                        0) /
                                    100)
                                .clamp(0, 1),
                        minHeight: 8,
                        color: p.education,
                      ),
                    ],
                  ),
                ),
            ],
          ],
        );
      },
    ),
  );
}
