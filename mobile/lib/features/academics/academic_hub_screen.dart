import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/polished_components.dart';
import '../auth/auth_controller.dart';
import '../offline/draft_queue_screen.dart';
import 'diary_screen.dart';
import 'exam_schedule_screen.dart';
import 'assessment_screen.dart';
import 'coordinator_screen.dart';
import 'academic_settings_screen.dart';

class AcademicHubScreen extends StatelessWidget {
  const AcademicHubScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final entries = [
      ('examSchedules', Icons.event_note, ExamScheduleScreen(auth: auth)),
      if (auth.can('school.manage'))
        (
          'academicSettings',
          Icons.settings_outlined,
          AcademicSettingsScreen(auth: auth),
        ),
      ('diary', Icons.auto_stories_outlined, DiaryScreen(auth: auth)),
      ('assessments', Icons.grading_outlined, AssessmentScreen(auth: auth)),
      (
        'offlineDrafts',
        Icons.cloud_sync_outlined,
        DraftQueueScreen(auth: auth),
      ),
      if (auth.can('academics.review'))
        ('coordinator', Icons.checklist_rtl, CoordinatorScreen(auth: auth)),
    ];
    return Scaffold(
      appBar: AppBar(title: Text(s.text('academics'))),
      body: ListView(
        padding: const EdgeInsets.all(AppSpace.lg),
        children: [
          for (final e in entries)
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpace.lg),
              child: ActionTile(
                icon: e.$2,
                title: s.text(e.$1),
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute<void>(builder: (_) => e.$3),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
