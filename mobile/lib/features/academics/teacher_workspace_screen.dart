import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/authorized_photo.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import '../students/student_detail_screen.dart';
import 'attendance_screen.dart';
import 'assessment_screen.dart';
import 'diary_editor.dart';
import 'diary_screen.dart';
import 'exam_schedule_screen.dart';

class TeacherWorkspaceScreen extends StatefulWidget {
  const TeacherWorkspaceScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<TeacherWorkspaceScreen> createState() => _TeacherWorkspaceScreenState();
}

class _TeacherWorkspaceScreenState extends State<TeacherWorkspaceScreen> {
  Map<String, dynamic>? data;
  String? error;
  @override
  void initState() {
    super.initState();
    if (widget.auth.isTeacher && widget.auth.canAcademics) load();
  }

  Future<void> load() async {
    try {
      final value = await widget.auth.call('my-teaching');
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

  Future<void> open(Widget target) async {
    await Navigator.push(
      context,
      MaterialPageRoute<void>(builder: (_) => target),
    );
    if (mounted) await load();
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    if (!widget.auth.isTeacher || !widget.auth.canAcademics) {
      return const Scaffold(body: DataState(error: 'forbidden'));
    }
    return Scaffold(
      appBar: AppBar(title: Text(s.text('myClasses'))),
      body: data == null
          ? DataState(error: error, onRetry: load)
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpace.lg),
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  if (error != null)
                    StatusBanner(
                      message: s.text(error!),
                      kind: StatusKind.error,
                    ),
                  Text(
                    '${s.text('assignedStudents')}: ${data!['student_count']} | ${s.text('pendingAttendance')}: ${data!['pending_attendance_sections']}',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  Text(s.text('teachingSchedulePolicy')),
                  if ((data!['sections'] as List).isEmpty)
                    StatusBanner(message: s.text('noTeachingAssignments')),
                  for (final row in data!['sections'] as List)
                    Padding(
                      padding: const EdgeInsets.only(top: AppSpace.md),
                      child: SurfaceCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${row['class_name']} | ${row['section_name']}',
                              style: Theme.of(context).textTheme.titleLarge,
                            ),
                            Text(row['session_name'] as String),
                            Text(
                              '${row['student_count']} ${s.text('students')}',
                            ),
                            for (final subject in row['subjects'] as List)
                              Text(subject['name'] as String),
                            Text(
                              '${s.text('attendance')}: ${s.text(row['attendance_status'] as String)}',
                            ),
                            TextButton(
                              onPressed: () => open(
                                TeacherClassScreen(
                                  auth: widget.auth,
                                  section: Map<String, dynamic>.from(row),
                                ),
                              ),
                              child: Text(s.text('openClass')),
                            ),
                          ],
                        ),
                      ),
                    ),
                  const SizedBox(height: AppSpace.lg),
                  Text(
                    '${s.text('pendingAssessments')}: ${data!['pending_assessments']}',
                  ),
                  for (final a in data!['assessments'] as List)
                    ListTile(
                      title: Text(a['title'] as String),
                      subtitle: Text(
                        '${a['subject_name']} | ${a['date']} | ${s.text(a['status'] as String)}',
                      ),
                      onTap: () => open(
                        MarksScreen(auth: widget.auth, id: a['id'] as String),
                      ),
                    ),
                  Text(
                    s.text('upcomingExams'),
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  if ((data!['upcoming_exams'] as List).isEmpty)
                    Text(s.text('noUpcomingExams')),
                  for (final e in data!['upcoming_exams'] as List)
                    ListTile(
                      title: Text(e['title'] as String),
                      subtitle: Text(
                        '${e['subject_name']} | ${e['date']} | ${e['starts_at']}',
                      ),
                      onTap: () => open(ExamScheduleScreen(auth: widget.auth)),
                    ),
                  Text(
                    s.text('recentHomework'),
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  for (final d in data!['recent_diary'] as List)
                    ListTile(
                      title: Text(d['topic'] as String),
                      subtitle: Text(
                        '${d['date']} | ${s.text(d['status'] as String)}',
                      ),
                      onTap: () => open(DiaryScreen(auth: widget.auth)),
                    ),
                ],
              ),
            ),
    );
  }
}

class TeacherClassScreen extends StatelessWidget {
  const TeacherClassScreen({
    super.key,
    required this.auth,
    required this.section,
  });
  final AuthController auth;
  final Map<String, dynamic> section;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    if (!auth.isTeacher || !auth.canAcademics) {
      return const Scaffold(body: DataState(error: 'forbidden'));
    }
    final id = section['section_id'] as String;
    void open(Widget screen) => Navigator.push(
      context,
      MaterialPageRoute<void>(builder: (_) => screen),
    );
    return RecordsScreen(
      auth: auth,
      path: 'my-teaching/sections/$id/students',
      title: 'myClassStudents',
      recordTitle: (r) => '${r['roll_number']}. ${r['full_name']}',
      recordSubtitle: (r) => r['registration_number'] as String,
      recordLeading: (r) => AuthorizedPhoto(
        auth: auth,
        fileId: r['photo_file_id'] as String?,
        name: r['full_name'] as String,
        size: 48,
      ),
      onOpen: (c, r) async {
        await Navigator.push(
          c,
          MaterialPageRoute<void>(
            builder: (_) =>
                StudentDetailScreen(auth: auth, id: r['id'] as String),
          ),
        );
      },
      summary: (_) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${section['class_name']} | ${section['section_name']} | ${section['session_name']}',
          ),
          Wrap(
            spacing: AppSpace.sm,
            children: [
              OutlinedButton(
                onPressed: () =>
                    open(AttendanceScreen(auth: auth, initialSection: id)),
                child: Text(s.text('attendance')),
              ),
              OutlinedButton(
                onPressed: () =>
                    open(DiaryScreen(auth: auth, initialSection: id)),
                child: Text(s.text('diary')),
              ),
              OutlinedButton(
                onPressed: () =>
                    open(AssessmentScreen(auth: auth, initialSection: id)),
                child: Text(s.text('assessments')),
              ),
            ],
          ),
          for (final subject in section['subjects'] as List)
            ListTile(
              title: Text(subject['name'] as String),
              trailing: auth.can('academics.write')
                  ? TextButton(
                      onPressed: () => open(
                        DiaryEditor(
                          auth: auth,
                          initialSection: id,
                          initialSubject: subject['id'] as String,
                        ),
                      ),
                      child: Text(s.text('addHomework')),
                    )
                  : null,
            ),
        ],
      ),
    );
  }
}
