import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/polished_components.dart';
import '../auth/auth_controller.dart';
import 'ticket_screen.dart';
import 'announcement_screen.dart';
import 'notification_screen.dart';

class CommunicationHubScreen extends StatelessWidget {
  const CommunicationHubScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final entries = [
      (
        'announcements',
        Icons.campaign_outlined,
        AnnouncementScreen(auth: auth),
      ),
      (
        'contactCoordinator',
        Icons.support_agent_outlined,
        TicketScreen(auth: auth),
      ),
      (
        'notifications',
        Icons.notifications_outlined,
        NotificationScreen(auth: auth),
      ),
    ];
    return Scaffold(
      appBar: AppBar(title: Text(s.text('messages'))),
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
