import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/conversation_message.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import '../finance/financial_forms.dart';
import '../files/private_file_screen.dart';
import 'ticket_composer.dart';

class TicketScreen extends StatelessWidget {
  const TicketScreen({super.key, required this.auth, this.studentId});
  final AuthController auth;
  final String? studentId;
  Future<void> create(BuildContext context) async {
    final selected =
        studentId ?? (await chooseStudent(context, auth))?['id'] as String?;
    if (selected == null || !context.mounted) return;
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => TicketComposer(auth: auth, studentId: selected),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => RecordsScreen(
    auth: auth,
    path: Uri(
      path: 'tickets',
      queryParameters: {'student_id': ?studentId},
    ).toString(),
    title: 'contactCoordinator',
    recordTitle: (r) => '${r['number']} · ${r['subject']}',
    recordSubtitle: (r) =>
        '${r['student_name']} · ${Strings.of(context).text(r['category'] as String)}',
    onCreate: auth.isParent ? create : null,
    onOpen: (c, r) async {
      await Navigator.push<void>(
        c,
        MaterialPageRoute(
          builder: (_) => TicketDetailScreen(auth: auth, id: r['id'] as String),
        ),
      );
    },
  );
}

class TicketDetailScreen extends StatefulWidget {
  const TicketDetailScreen({super.key, required this.auth, required this.id});
  final AuthController auth;
  final String id;
  @override
  State<TicketDetailScreen> createState() => _TicketDetailScreenState();
}

class _TicketDetailScreenState extends State<TicketDetailScreen> {
  Map<String, dynamic>? data;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final value = await widget.auth.call('tickets/${widget.id}');
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

  Future<void> workflow() async {
    final staff = await widget.auth.call(
      'communication/staff?student_id=${data!['student_id']}',
    );
    if (!mounted) return;
    final s = Strings.of(context);
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => DomainForm(
          title: 'ticketWorkflow',
          fields: [
            FormFieldSpec(
              'status',
              'status',
              kind: FieldKind.choice,
              initial: data!['status'],
              options: {
                for (final k in [
                  'submitted',
                  'acknowledged',
                  'in_progress',
                  'awaiting_parent',
                  'escalated',
                  'resolved',
                  'closed',
                ])
                  k: s.text(k),
              },
            ),
            FormFieldSpec(
              'priority',
              'priority',
              kind: FieldKind.choice,
              initial: data!['priority'],
              options: {
                for (final k in ['normal', 'high', 'urgent']) k: s.text(k),
              },
            ),
            FormFieldSpec(
              'assigned_to',
              'assignCoordinator',
              kind: FieldKind.choice,
              required: false,
              initial: data!['assigned_to'],
              options: {
                for (final u in staff['items'] as List)
                  if (u['can_coordinate'] == true)
                    (u['id'] as String): u['name'] as String,
              },
            ),
            FormFieldSpec(
              'forwarded_to',
              'forwardInternally',
              kind: FieldKind.multichoice,
              required: false,
              initial: data!['forwarded_to'] ?? [],
              options: {
                for (final u in staff['items'] as List)
                  (u['id'] as String): u['name'] as String,
              },
            ),
            const FormFieldSpec(
              'internal_note',
              'internalNote',
              kind: FieldKind.multiline,
              required: false,
              maxLength: 5000,
            ),
          ],
          onSubmit: (v) async {
            await widget.auth.call(
              'tickets/${widget.id}/workflow',
              body: {...v, 'version': data!['version']},
            );
          },
        ),
      ),
    );
    load();
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          data?['number']?.toString() ?? s.text('contactCoordinator'),
        ),
        actions: [
          if (widget.auth.can('communication.manage'))
            IconButton(
              tooltip: s.text('ticketWorkflow'),
              onPressed: data == null ? null : workflow,
              icon: const Icon(Icons.tune),
            ),
        ],
      ),
      body: data == null
          ? DataState(error: error, onRetry: load)
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpace.lg),
                children: [
                  SurfaceCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          data!['subject'] as String,
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                        const SizedBox(height: AppSpace.sm),
                        Wrap(
                          spacing: AppSpace.sm,
                          children: [
                            AppBadge(
                              label: s.text(data!['status'] as String),
                              status: data!['status'] as String,
                            ),
                            if (widget.auth.can('communication.manage'))
                              AppBadge(
                                label: s.text(data!['priority'] as String),
                                status: data!['priority'] as String,
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpace.xl),
                  for (final message in data!['messages'] as List)
                    ConversationMessage(
                      message: (message as Map).cast<String, dynamic>(),
                      attachments: [
                        for (final f in message['attachments'] as List)
                          TextButton.icon(
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute<void>(
                                builder: (_) => PrivateFileScreen(
                                  auth: widget.auth,
                                  id: f['id'] as String,
                                  mime: f['mime'] as String,
                                ),
                              ),
                            ),
                            icon: const Icon(Icons.attach_file),
                            label: Text(s.text('viewAttachment')),
                          ),
                      ],
                    ),
                  if (data!['status'] != 'closed')
                    FilledButton.icon(
                      onPressed: () async {
                        await Navigator.push<bool>(
                          context,
                          MaterialPageRoute(
                            builder: (_) => TicketComposer(
                              auth: widget.auth,
                              studentId: data!['student_id'] as String,
                              ticketId: widget.id,
                            ),
                          ),
                        );
                        load();
                      },
                      icon: const Icon(Icons.reply),
                      label: Text(s.text('reply')),
                    ),
                  const SizedBox(height: AppSpace.xl),
                  Text(
                    s.text('ticketHistory'),
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  for (final event in data!['events'] as List)
                    ListTile(
                      title: Text(s.text(event['to_status'] as String)),
                      subtitle: Text(
                        event['created_at'].toString().substring(0, 16),
                      ),
                    ),
                ],
              ),
            ),
    );
  }
}
