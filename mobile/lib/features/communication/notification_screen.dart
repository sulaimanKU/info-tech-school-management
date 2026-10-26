import 'package:flutter/material.dart';

import '../../l10n/strings.dart';
import '../../shared/widgets/records_screen.dart';
import '../auth/auth_controller.dart';
import '../academics/diary_screen.dart';
import '../academics/student_academic_screen.dart';
import '../finance/invoice_screen.dart';
import 'ticket_screen.dart';
import 'announcement_screen.dart';

class NotificationScreen extends StatelessWidget {
  const NotificationScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return RecordsScreen(
      auth: auth,
      path: 'notifications',
      title: 'notifications',
      recordTitle: (r) => s.text('notification_${r['kind']}'),
      recordSubtitle: (r) =>
          '${s.text(r['read_at'] == null ? 'unread' : 'read')} · ${r['created_at'].toString().substring(0, 16)}',
      onOpen: (c, r) async {
        await auth.call('notifications/${r['id']}/read', body: {});
        if (!c.mounted) return;
        Widget? target;
        final id = r['target_id'] as String;
        switch (r['target_type']) {
          case 'ticket':
            target = TicketDetailScreen(auth: auth, id: id);
          case 'announcement':
            target = AnnouncementDetailScreen(auth: auth, id: id);
          case 'invoice':
            target = InvoiceDetailScreen(auth: auth, id: id);
          case 'diary':
            target = DiaryScreen(
              auth: auth,
              studentId: r['student_id'] as String?,
            );
          case 'assessment':
            if (r['student_id'] != null) {
              target = StudentAcademicScreen(
                auth: auth,
                studentId: r['student_id'] as String,
                kind: 'assessments',
              );
            }
        }
        if (target != null) {
          await Navigator.push<void>(
            c,
            MaterialPageRoute(builder: (_) => target!),
          );
        }
      },
    );
  }
}
