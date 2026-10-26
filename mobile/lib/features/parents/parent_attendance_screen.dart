import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../design/semantic_tokens.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/summary_card.dart';
import '../../shared/widgets/polished_components.dart';
import '../auth/auth_controller.dart';
import '../reports/report_screen.dart';
import 'parent_resource.dart';

class ParentAttendanceScreen extends StatefulWidget {
  const ParentAttendanceScreen({
    super.key,
    required this.auth,
    required this.studentId,
  });
  final AuthController auth;
  final String studentId;
  @override
  State<ParentAttendanceScreen> createState() => _ParentAttendanceScreenState();
}

class _ParentAttendanceScreenState extends State<ParentAttendanceScreen> {
  DateTime month = DateTime(DateTime.now().year, DateTime.now().month);
  String get period =>
      '${month.year}-${month.month.toString().padLeft(2, '0')}';
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('attendance'))),
      body: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              IconButton(
                key: const Key('previous-attendance-month'),
                tooltip: s.text('previousMonth'),
                onPressed: month.year > 2020
                    ? () => setState(
                        () => month = DateTime(month.year, month.month - 1),
                      )
                    : null,
                icon: const Icon(Icons.chevron_left),
              ),
              Text(period, style: Theme.of(context).textTheme.titleMedium),
              IconButton(
                tooltip: s.text('nextMonth'),
                onPressed:
                    month.isBefore(
                      DateTime(DateTime.now().year, DateTime.now().month),
                    )
                    ? () => setState(
                        () => month = DateTime(month.year, month.month + 1),
                      )
                    : null,
                icon: const Icon(Icons.chevron_right),
              ),
            ],
          ),
          Expanded(
            child: ParentResource(
              auth: widget.auth,
              path: 'students/${widget.studentId}/attendance?month=$period',
              builder: (context, data) {
                final counts = data['counts'] as Map? ?? {};
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    SurfaceCard(
                      accent: AppPalette.of(context).success,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(s.text('attendancePercentage')),
                          Text(
                            data['attendance_percentage'] == null
                                ? s.text('notRecorded')
                                : '${data['attendance_percentage']}%',
                            style: Theme.of(context).textTheme.headlineLarge,
                          ),
                          Text(
                            '${s.text('recordedDays')}: ${data['recorded_days']} / ${data['school_days']}',
                          ),
                          if (data['provisional'] == true)
                            StatusBanner(
                              message: s.text('attendanceProvisional'),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpace.lg),
                    SummaryGrid(
                      children: [
                        for (final status in [
                          'present',
                          'absent',
                          'late',
                          'excused',
                        ])
                          SummaryCard(
                            label: s.text(status),
                            value: '${counts[status] ?? 0}',
                            icon: AppStatus.icon(status),
                            tone: AppStatus.tone(status),
                          ),
                      ],
                    ),
                    const SizedBox(height: AppSpace.xl),
                    SectionHeader(title: s.text('dailyAttendance')),
                    if ((data['items'] as List? ?? []).isEmpty)
                      Text(s.text('noAttendanceParent')),
                    for (final row in data['items'] as List? ?? [])
                      ListTile(
                        leading: Icon(
                          AppStatus.icon(row['status'] as String),
                          color: AppPalette.of(context)
                              .tone(AppStatus.tone(row['status'] as String)),
                        ),
                        title: Text(row['date'].toString()),
                        trailing: AppBadge(
                          label: s.text(row['status'] as String),
                          status: row['status'] as String,
                        ),
                      ),
                    if ((data['holidays'] as List? ?? []).isNotEmpty) ...[
                      const SizedBox(height: AppSpace.lg),
                      SectionHeader(title: s.text('school_holiday')),
                      for (final holiday in data['holidays'] as List)
                        ListTile(
                          leading: const Icon(Icons.event_available_outlined),
                          title: Text(holiday['date'].toString()),
                          subtitle: Text(
                            holiday['label']?.toString() ??
                                s.text('school_holiday'),
                          ),
                        ),
                    ],
                    TextButton.icon(
                      onPressed: () => openSchoolReport(
                        context,
                        widget.auth,
                        'attendance',
                        {'student_id': widget.studentId, 'month': period},
                      ),
                      icon: const Icon(Icons.picture_as_pdf_outlined),
                      label: Text(s.text('report_attendance')),
                    ),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
