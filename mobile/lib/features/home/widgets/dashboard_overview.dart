import '../../finance/finance_summary_screen.dart';

import 'package:flutter/material.dart';

import '../../../core/api_client.dart';
import '../../../core/money.dart';
import '../../../design/app_theme.dart';
import '../../../design/semantic_tokens.dart';
import '../../../l10n/strings.dart';
import '../../../shared/widgets/data_state.dart';
import '../../../shared/widgets/polished_components.dart';
import '../../../shared/widgets/summary_card.dart';
import '../../../shared/widgets/surface_card.dart';
import '../../auth/auth_controller.dart';
import '../../students/student_list_screen.dart';
import '../../administration/staff_screen.dart';
import '../../academics/attendance_screen.dart';
import '../../academics/academic_hub_screen.dart';
import '../../finance/invoice_screen.dart';
import '../../communication/ticket_screen.dart';
import '../../communication/announcement_screen.dart';
import '../../communication/notification_screen.dart';

class DashboardOverview extends StatefulWidget {
  const DashboardOverview({super.key, required this.auth, this.quickActions});
  final Widget? quickActions;
  final AuthController auth;
  @override
  State<DashboardOverview> createState() => DashboardOverviewState();
}

class DashboardOverviewState extends State<DashboardOverview> {
  Map<String, dynamic>? data;
  String? error;
  bool loading = false;
  int generation = 0;
  late String signature;
  String fingerprint() =>
      '${widget.auth.user?['id']}:${widget.auth.user?['roles']}:${widget.auth.user?['permissions']}';
  @override
  void initState() {
    super.initState();
    signature = fingerprint();
    load();
  }

  @override
  void didUpdateWidget(covariant DashboardOverview oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (signature != fingerprint()) {
      signature = fingerprint();
      data = null;
      load();
    }
  }

  Future<void> load() async {
    final version = ++generation;
    if (mounted) {
      setState(() {
        loading = true;
        error = null;
      });
    }
    try {
      final value = await widget.auth.call('dashboard-summary');
      if (value['as_of'] is! String || value['unread_notifications'] is! num) {
        throw const ApiFailure('server');
      }
      if (mounted && version == generation) setState(() => data = value);
    } on ApiFailure catch (e) {
      if (mounted && version == generation) setState(() => error = e.code);
    } finally {
      if (mounted && version == generation) setState(() => loading = false);
    }
  }

  void open(Widget screen) =>
      Navigator.push(context, MaterialPageRoute<void>(builder: (_) => screen));
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final p = AppPalette.of(context);
    final value = data;
    if (error != null) {
      return SurfaceCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            StatusBanner(message: s.text(error!), kind: StatusKind.error),
            TextButton.icon(
              key: const Key('dashboard-retry'),
              onPressed: loading ? null : load,
              icon: const Icon(Icons.refresh),
              label: Text(s.text('retry')),
            ),
          ],
        ),
      );
    }
    if (value == null) return const LoadingState();
    final students = value['students'] as Map?;
    final staff = widget.auth.can('staff.manage')
        ? value['staff'] as Map?
        : null;
    final finance = widget.auth.can('finance.read')
        ? value['finance'] as Map?
        : null;
    final school =
        (widget.auth.canAcademics || widget.auth.can('school.manage'))
        ? value['school'] as Map?
        : null;
    final academic = widget.auth.canAcademics
        ? value['academic'] as Map?
        : null;
    final attendance = widget.auth.canAcademics
        ? value['attendance'] as Map?
        : null;
    final cards = <Widget>[
      if (students != null)
        SummaryCard(
          key: const Key('kpi-students'),
          label: s.text(
            value['scope'] == 'assigned' ? 'assignedStudents' : 'totalStudents',
          ),
          value: '${students['total']}',
          contextLabel: '${s.text('active')}: ${students['active']}',
          icon: Icons.school_outlined,
          onTap: () => open(StudentListScreen(auth: widget.auth)),
        ),
      if (staff != null)
        SummaryCard(
          key: const Key('kpi-staff'),
          label: s.text('activeStaff'),
          value: '${staff['active']}',
          contextLabel: '${s.text('teachers')}: ${staff['teachers']}',
          icon: Icons.groups_outlined,
          tone: StatusTone.neutral,
          onTap: () => open(StaffScreen(auth: widget.auth)),
        ),
      if (finance != null)
        SummaryCard(
          key: const Key('kpi-finance'),
          label: s.text('outstandingFees'),
          value: Money.format(finance['outstanding_minor']),
          contextLabel:
              '${finance['students_with_balance']} ${s.text('studentsWithBalance')}',
          icon: Icons.account_balance_wallet_outlined,
          tone: StatusTone.warning,
          onTap: () => open(InvoiceScreen(auth: widget.auth)),
        ),
      if (widget.auth.can('communication.manage') &&
          value.containsKey('open_requests'))
        SummaryCard(
          key: const Key('kpi-requests'),
          label: s.text('openParentRequests'),
          value: '${value['open_requests']}',
          icon: Icons.support_agent_outlined,
          tone: StatusTone.info,
          onTap: () => open(TicketScreen(auth: widget.auth)),
        ),
      if (staff == null && school != null)
        SummaryCard(
          label: s.text('assignedSections'),
          value: '${school['sections']}',
          contextLabel: '${s.text('subjects')}: ${school['subjects']}',
          icon: Icons.class_outlined,
          tone: StatusTone.success,
          onTap: () => open(AcademicHubScreen(auth: widget.auth)),
        ),
      if (staff == null && academic != null)
        SummaryCard(
          label: s.text(
            academic['assessment_state'] == 'submitted'
                ? 'pendingReviews'
                : 'draftAssessments',
          ),
          value: '${academic['pending_assessments']}',
          contextLabel:
              '${s.text('homeworkToday')}: ${academic['homework_today']}',
          icon: Icons.grading_outlined,
          tone: StatusTone.warning,
          onTap: () => open(AcademicHubScreen(auth: widget.auth)),
        ),
      if (staff == null && finance != null)
        SummaryCard(
          label: s.text('verifiedReceipts'),
          value: Money.format(finance['receipts_month_minor']),
          contextLabel: '${finance['period']}',
          icon: Icons.payments_outlined,
          tone: StatusTone.success,
        ),
    ];
    if (cards.length < 4) {
      cards.add(
        SummaryCard(
          key: const Key('kpi-notifications'),
          label: s.text('unreadNotifications'),
          value: '${value['unread_notifications'] ?? 0}',
          icon: Icons.notifications_outlined,
          onTap: () => open(NotificationScreen(auth: widget.auth)),
        ),
      );
    }
    final announcements = value['announcements'] as List? ?? [];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    s.text('schoolOverview'),
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  if (value['as_of'] != null)
                    Text(
                      '${s.text(value['scope'] == 'assigned' ? 'assignedScope' : 'schoolScope')} · ${value['as_of'].toString().substring(0, 10)}',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                ],
              ),
            ),
            IconButton(
              key: const Key('refresh-dashboard'),
              tooltip: s.text('refresh'),
              onPressed: loading ? null : load,
              icon: const Icon(Icons.refresh_rounded),
            ),
          ],
        ),
        const SizedBox(height: AppSpace.lg),
        SummaryGrid(children: cards),
        if (widget.quickActions != null) ...[
          const SizedBox(height: AppSpace.xl),
          Text(
            s.text('quickActions'),
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: AppSpace.md),
          widget.quickActions!,
        ],
        if (attendance != null) ...[
          const SizedBox(height: AppSpace.xl),
          SurfaceCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SectionHeader(
                  title: s.text('attendanceToday'),
                  description: attendance['date'] as String?,
                  icon: Icons.fact_check_outlined,
                ),
                if ((attendance['expected'] as num? ?? 0) > 0) ...[
                  Text(
                    '${attendance['recorded']} / ${attendance['expected']} ${s.text('attendanceRecorded')}',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: AppSpace.md),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(AppRadius.pill),
                    child: LinearProgressIndicator(
                      value:
                          ((attendance['recorded'] as num) /
                                  (attendance['expected'] as num))
                              .clamp(0, 1)
                              .toDouble(),
                      minHeight: 8,
                      semanticsLabel: s.text('attendanceRecorded'),
                    ),
                  ),
                  const SizedBox(height: AppSpace.lg),
                ] else
                  Text(s.text('noScheduledStudents')),
                Wrap(
                  spacing: AppSpace.sm,
                  runSpacing: AppSpace.sm,
                  children: [
                    for (final status in [
                      'present',
                      'absent',
                      'late',
                      'excused',
                      'not_marked',
                    ])
                      AppBadge(
                        label: '${s.text(status)}: ${attendance[status]}',
                        status: status,
                      ),
                  ],
                ),
                if (widget.auth.canAcademics)
                  Align(
                    alignment: AlignmentDirectional.centerEnd,
                    child: TextButton(
                      onPressed: () =>
                          open(AttendanceScreen(auth: widget.auth)),
                      child: Text(s.text('openAttendance')),
                    ),
                  ),
              ],
            ),
          ),
        ],
        if (finance != null) ...[
          const SizedBox(height: AppSpace.xl),
          SurfaceCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SectionHeader(
                  title: s.text('financialOverview'),
                  description: s.text('financeSummaryBasis'),
                  icon: Icons.account_balance_outlined,
                ),
                ValueRow(
                  label: s.text('verifiedReceipts'),
                  value: Money.format(finance['receipts_month_minor']),
                ),
                Text(
                  '${finance['period']}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const Divider(height: AppSpace.xl),
                ValueRow(
                  label: 'Unbilled configured tuition',
                  value: Money.format(finance['unbilled_minor']),
                ),
                Text(
                  '${finance['students_with_missing_months'] ?? 0} students have missing monthly fees. Posted outstanding excludes these unbilled amounts.',
                ),
                TextButton(
                  onPressed: () =>
                      open(FinanceSummaryScreen(auth: widget.auth)),
                  child: const Text('Review missing monthly fees'),
                ),
                ValueRow(
                  label: s.text('overdueFees'),
                  value: Money.format(finance['overdue_minor']),
                ),
              ],
            ),
          ),
        ],
        if (school != null) ...[
          const SizedBox(height: AppSpace.xl),
          SurfaceCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SectionHeader(
                  title: s.text('schoolOperations'),
                  icon: Icons.domain_outlined,
                ),
                Wrap(
                  spacing: AppSpace.sm,
                  runSpacing: AppSpace.sm,
                  children: [
                    AppBadge(
                      label: '${s.text('classes')}: ${school['classes']}',
                    ),
                    AppBadge(
                      label: '${s.text('sections')}: ${school['sections']}',
                    ),
                    if (value.containsKey('guardians'))
                      AppBadge(
                        label:
                            '${s.text('verifiedGuardians')}: ${value['guardians']}',
                      ),
                  ],
                ),
                if ((school['sessions'] as List? ?? []).isNotEmpty) ...[
                  const SizedBox(height: AppSpace.lg),
                  Text(
                    s.text('currentAcademicSessions'),
                    style: Theme.of(context).textTheme.labelLarge,
                  ),
                  for (final row in school['sessions'] as List)
                    Text(row['name'] as String),
                ],
              ],
            ),
          ),
        ],
        const SizedBox(height: AppSpace.xl),
        SurfaceCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SectionHeader(
                title: s.text('recentAnnouncements'),
                icon: Icons.campaign_outlined,
              ),
              if (announcements.isEmpty)
                Text(
                  s.text('noRecentAnnouncements'),
                  style: Theme.of(context).textTheme.bodyMedium
                      ?.copyWith(color: p.supporting),
                ),
              for (final raw in announcements)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(raw['title'] as String),
                  subtitle: Text(
                    '${s.text(raw['kind'] as String)} · ${raw['publish_at'].toString().substring(0, 10)}',
                  ),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => open(
                    AnnouncementDetailScreen(
                      auth: widget.auth,
                      id: raw['id'] as String,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}
