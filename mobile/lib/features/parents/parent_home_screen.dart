import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/money.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/authorized_photo.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/polished_components.dart';
import '../auth/auth_controller.dart';
import '../home/widgets/welcome_panel.dart';
import '../home/widgets/account_sheet.dart';
import '../communication/announcement_screen.dart';
import '../communication/ticket_screen.dart';
import 'child_workspace_screen.dart';

class ParentHomeScreen extends StatefulWidget {
  const ParentHomeScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<ParentHomeScreen> createState() => _ParentHomeScreenState();
}

class _ParentHomeScreenState extends State<ParentHomeScreen> {
  Map<String, dynamic>? data;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final value = await widget.auth.call('parent/home');
      if (mounted) {
        setState(() {
          data = value;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) {
        setState(() {
          error = e.code;
          if (e.code != 'network') data = null;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final children = (data?['children'] as List? ?? [])
        .map((v) => (v as Map).cast<String, dynamic>())
        .toList();
    return RefreshIndicator(
      onRefresh: load,
      child: ListView(
        padding: const EdgeInsets.all(AppSpace.lg),
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          WelcomePanel(user: widget.auth.user!),
          const SizedBox(height: AppSpace.lg),
          const SizedBox(height: AppSpace.xl),
          Text(
            s.text('myChildren'),
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: AppSpace.lg),
          if (error != null)
            DataState(error: error, onRetry: load)
          else if (data == null)
            const DataState()
          else if (children.isEmpty)
            StatusBanner(message: s.text('noLinkedChildren')),
          for (final child in children)
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpace.lg),
              child: SurfaceCard(
                padding: EdgeInsets.zero,
                child: InkWell(
                  borderRadius: BorderRadius.circular(AppRadius.card),
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute<void>(
                      builder: (_) => ChildWorkspaceScreen(
                        auth: widget.auth,
                        children: children,
                        initialId: child['id'] as String,
                      ),
                    ),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(AppSpace.xl),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            AuthorizedPhoto(
                              auth: widget.auth,
                              fileId: child['photo_file_id'] as String?,
                            ),
                            const SizedBox(width: AppSpace.lg),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    child['full_name'] as String,
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleLarge,
                                  ),
                                  Text(child['registration_number'] as String),
                                  Text(
                                    child['enrollment'] == null
                                        ? s.text('noActiveEnrollment')
                                        : '${child['enrollment']['class_name']} · ${child['enrollment']['section_name']} · ${s.text('rollNumber')} ${child['enrollment']['roll_number']}',
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: AppSpace.lg),
                        Wrap(
                          spacing: AppSpace.md,
                          runSpacing: AppSpace.sm,
                          children: [
                            AppBadge(
                              label:
                                  '${s.text('attendance')}: ${child['attendance_percentage'] == null ? s.text('notRecorded') : '${child['attendance_percentage']}%'}',
                            ),
                            AppBadge(
                              label: s.text(child['fee_status'] as String),
                              status: child['fee_status'] as String,
                            ),
                          ],
                        ),
                        if (child['attendance_provisional'] == true)
                          Padding(
                            padding: const EdgeInsets.only(top: AppSpace.sm),
                            child: Text(
                              s.text('attendanceProvisional'),
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ),
                        const SizedBox(height: AppSpace.md),
                        ValueRow(
                          label: s.text('currentBalance'),
                          value: Money.format(child['balance_minor']),
                          emphasis: true,
                        ),
                        Text(
                          child['latest_academic'] == null
                              ? s.text('noPublishedResults')
                              : '${child['latest_academic']['subject_name']} · ${child['latest_academic']['percentage']}% · ${child['latest_academic']['grade']}',
                        ),
                        const SizedBox(height: AppSpace.lg),
                        AppBadge(
                          label: s.text('openChild'),
                          icon: Icons.arrow_forward_rounded,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          const SizedBox(height: AppSpace.lg),
          Wrap(
            spacing: AppSpace.md,
            runSpacing: AppSpace.sm,
            children: [
              OutlinedButton.icon(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute<void>(
                    builder: (_) => AnnouncementScreen(auth: widget.auth),
                  ),
                ),
                icon: const Icon(Icons.campaign_outlined),
                label: Text(s.text('announcements')),
              ),
              OutlinedButton.icon(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute<void>(
                    builder: (_) => TicketScreen(auth: widget.auth),
                  ),
                ),
                icon: const Icon(Icons.support_agent_outlined),
                label: Text(s.text('contactCoordinator')),
              ),
              OutlinedButton.icon(
                onPressed: widget.auth.busy
                    ? null
                    : () => confirmLogout(context, widget.auth),
                icon: const Icon(Icons.logout),
                label: Text(s.text('logout')),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
