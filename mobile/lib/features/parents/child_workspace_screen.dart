import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import '../reports/report_screen.dart';
import '../students/student_detail_screen.dart';
import '../finance/student_fee_screen.dart';
import '../academics/student_academic_screen.dart';
import '../academics/diary_screen.dart';
import '../academics/exam_schedule_screen.dart';
import '../communication/announcement_screen.dart';
import '../communication/ticket_screen.dart';
import 'child_profile_card.dart';
import 'parent_overview.dart';
import 'parent_academics_screen.dart';
import 'parent_attendance_screen.dart';

class ChildWorkspaceScreen extends StatefulWidget {
  const ChildWorkspaceScreen({
    super.key,
    required this.auth,
    required this.children,
    required this.initialId,
  });
  final AuthController auth;
  final List<Map<String, dynamic>> children;
  final String initialId;
  @override
  State<ChildWorkspaceScreen> createState() => _ChildWorkspaceScreenState();
}

class _ChildWorkspaceScreenState extends State<ChildWorkspaceScreen> {
  late String id;
  String page = 'overview';
  @override
  void initState() {
    super.initState();
    id = widget.children.any((c) => c['id'] == widget.initialId)
        ? widget.initialId
        : widget.children.firstOrNull?['id'] as String? ?? '';
  }

  void open(String value) => setState(() => page = value);
  Widget content() {
    final auth = widget.auth;
    return switch (page) {
      'overview' => ParentOverview(auth: auth, studentId: id, onOpen: open),
      'academics' => ParentAcademicsScreen(auth: auth, studentId: id),
      'attendance' => ParentAttendanceScreen(auth: auth, studentId: id),
      'finance' => StudentFeeScreen(auth: auth, studentId: id),
      'diary' => DiaryScreen(auth: auth, studentId: id),
      'contactCoordinator' => TicketScreen(auth: auth, studentId: id),
      'announcements' => AnnouncementScreen(auth: auth, studentId: id),
      'behavior' => StudentAcademicScreen(
        auth: auth,
        studentId: id,
        kind: 'behavior',
      ),
      'reports' => ReportScreen(auth: auth, studentId: id),
      'examSchedule' => ExamScheduleScreen(auth: auth, studentId: id),
      'profile' => StudentDetailScreen(auth: auth, id: id),
      _ => ListView(
        padding: const EdgeInsets.all(AppSpace.lg),
        children: [
          for (final entry in [
            ('attendance', Icons.fact_check_outlined),
            ('finance', Icons.receipt_long_outlined),
            ('behavior', Icons.favorite_outline),
            ('announcements', Icons.campaign_outlined),
            ('contactCoordinator', Icons.support_agent_outlined),
            ('examSchedule', Icons.event_outlined),
            ('reports', Icons.picture_as_pdf_outlined),
            ('profile', Icons.person_outline),
          ])
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpace.md),
              child: SurfaceCard(
                padding: EdgeInsets.zero,
                child: ListTile(
                  leading: Icon(entry.$2),
                  title: Text(Strings.of(context).text(entry.$1)),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => open(entry.$1),
                ),
              ),
            ),
        ],
      ),
    };
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final child = widget.children.where((c) => c['id'] == id).firstOrNull;
    final destinations = [
      ('overview', Icons.home_outlined),
      ('academics', Icons.school_outlined),
      ('diary', Icons.auto_stories_outlined),
      ('contactCoordinator', Icons.support_agent_outlined),
      ('more', Icons.grid_view_outlined),
    ];
    final selected = destinations.indexWhere((d) => d.$1 == page);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('childWorkspace'))),
      body: child == null
          ? const DataState(empty: true)
          : Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpace.lg,
                    AppSpace.sm,
                    AppSpace.lg,
                    AppSpace.sm,
                  ),
                  child: DropdownButtonFormField<String>(
                    initialValue: id,
                    isExpanded: true,
                    decoration: InputDecoration(
                      labelText: s.text('selectChild'),
                    ),
                    items: [
                      for (final c in widget.children)
                        DropdownMenuItem(
                          value: c['id'] as String,
                          child: Text(
                            c['full_name'] as String,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                    ],
                    onChanged: (v) {
                      if (v != null && v != id) setState(() => id = v);
                    },
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: AppSpace.lg),
                  child: ChildProfileCard(
                    auth: widget.auth,
                    child: child,
                    compact: true,
                  ),
                ),
                Expanded(
                  child: KeyedSubtree(
                    key: ValueKey('$id/$page'),
                    child: content(),
                  ),
                ),
              ],
            ),
      bottomNavigationBar: child == null
          ? null
          : NavigationBar(
              selectedIndex: selected < 0 ? 4 : selected,
              onDestinationSelected: (index) => open(destinations[index].$1),
              destinations: [
                for (final d in destinations)
                  NavigationDestination(
                    icon: Icon(d.$2),
                    label: s.text(switch (d.$1) {
                      'diary' => 'parentHomeworkTab',
                      'contactCoordinator' => 'parentMessagesTab',
                      _ => d.$1,
                    }),
                  ),
              ],
            ),
    );
  }
}
