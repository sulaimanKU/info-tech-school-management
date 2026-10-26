import 'package:flutter/material.dart';

import '../../core/money.dart';
import '../../design/app_theme.dart';
import '../../design/semantic_tokens.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/summary_card.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/polished_components.dart';
import '../auth/auth_controller.dart';
import 'parent_resource.dart';

class ParentOverview extends StatelessWidget {
  const ParentOverview({
    super.key,
    required this.auth,
    required this.studentId,
    required this.onOpen,
  });
  final AuthController auth;
  final String studentId;
  final ValueChanged<String> onOpen;
  @override
  Widget build(BuildContext context) => ParentResource(
    auth: auth,
    path: 'parent/children/$studentId/overview',
    builder: (context, data) {
      final s = Strings.of(context);
      final child = data['child'] as Map?;
      if (child == null) return const DataState(empty: true);
      final latest = child['latest_academic'] as Map?;
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SectionHeader(
            title: s.text('childSnapshot'),
            description: s.text('parentSnapshotNote'),
          ),
          SummaryGrid(
            children: [
              SummaryCard(
                label: s.text('attendance'),
                value: child['attendance_percentage'] == null
                    ? s.text('notRecorded')
                    : '${child['attendance_percentage']}%',
                icon: Icons.fact_check_outlined,
                tone: StatusTone.success,
                contextLabel: s.text('thisMonth'),
                onTap: () => onOpen('attendance'),
              ),
              SummaryCard(
                label: s.text('academics'),
                value: latest == null
                    ? s.text('notRecorded')
                    : '${latest['percentage']}%',
                icon: Icons.school_outlined,
                contextLabel: latest == null
                    ? s.text('noPublishedResults')
                    : latest['subject_name']?.toString(),
                onTap: () => onOpen('academics'),
              ),
              SummaryCard(
                label: s.text('finance'),
                value: Money.format(child['balance_minor']),
                icon: Icons.account_balance_wallet_outlined,
                tone: StatusTone.warning,
                contextLabel: s.text(child['fee_status'] as String),
                onTap: () => onOpen('finance'),
              ),
              SummaryCard(
                label: s.text('homework'),
                value: data['homework_count'].toString(),
                icon: Icons.auto_stories_outlined,
                contextLabel: s.text('publishedItems'),
                onTap: () => onOpen('diary'),
              ),
            ],
          ),
          if (child['attendance_provisional'] == true)
            Padding(
              padding: const EdgeInsets.only(top: AppSpace.md),
              child: StatusBanner(message: s.text('attendanceProvisional')),
            ),
          const SizedBox(height: AppSpace.xl),
          SectionHeader(title: s.text('latestAcademic')),
          SurfaceCard(
            accent: AppPalette.of(context).education,
            child: latest == null
                ? Text(s.text('noPublishedResults'))
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        latest['title'].toString(),
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      Text('${latest['subject_name']} · ${latest['date']}'),
                      Text(
                        '${latest['percentage']}% · ${s.text('grade')} ${latest['grade']}',
                      ),
                      TextButton(
                        onPressed: () => onOpen('academics'),
                        child: Text(s.text('subjectPerformance')),
                      ),
                    ],
                  ),
          ),
          const SizedBox(height: AppSpace.xl),
          SectionHeader(title: s.text('recentHomework')),
          if ((data['recent_homework'] as List? ?? []).isEmpty)
            Text(s.text('noHomeworkParent')),
          for (final item in data['recent_homework'] as List? ?? [])
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpace.md),
              child: SurfaceCard(
                accent: AppPalette.of(context).secondary,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item['subject_name'].toString(),
                      style: Theme.of(context).textTheme.labelLarge,
                    ),
                    Text(
                      item['topic'].toString(),
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    Text(
                      item['homework'].toString(),
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                    ),
                    Text(
                      '${item['teacher_name']} · ${item['date']}',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    TextButton(
                      onPressed: () => onOpen('diary'),
                      child: Text(s.text('viewAttachment')),
                    ),
                  ],
                ),
              ),
            ),
          const SizedBox(height: AppSpace.lg),
          SectionHeader(title: s.text('recentAnnouncements')),
          if ((data['announcements'] as List? ?? []).isEmpty)
            Text(s.text('noRecentAnnouncements')),
          for (final item in data['announcements'] as List? ?? [])
            ListTile(
              leading: const Icon(Icons.campaign_outlined),
              title: Text(item['title'].toString()),
              subtitle: Text(item['publish_at'].toString().split('T').first),
              onTap: () => onOpen('announcements'),
            ),
          const SizedBox(height: AppSpace.lg),
          Wrap(
            spacing: AppSpace.sm,
            runSpacing: AppSpace.sm,
            children: [
              for (final action in [
                ('academics', Icons.grading_outlined),
                ('attendance', Icons.fact_check_outlined),
                ('finance', Icons.receipt_long_outlined),
                ('contactCoordinator', Icons.support_agent_outlined),
              ])
                OutlinedButton.icon(
                  onPressed: () => onOpen(action.$1),
                  icon: Icon(action.$2),
                  label: Text(s.text(action.$1)),
                ),
            ],
          ),
        ],
      );
    },
  );
}
