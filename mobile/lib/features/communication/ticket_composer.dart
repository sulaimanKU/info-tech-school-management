import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/operation_id.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import '../files/private_file_service.dart';

class TicketComposer extends StatefulWidget {
  const TicketComposer({
    super.key,
    required this.auth,
    required this.studentId,
    this.ticketId,
  });
  final AuthController auth;
  final String studentId;
  final String? ticketId;
  @override
  State<TicketComposer> createState() => _TicketComposerState();
}

class _TicketComposerState extends State<TicketComposer> {
  final subject = TextEditingController(), message = TextEditingController();
  final form = GlobalKey<FormState>();
  final client = operationId();
  final files = <String>[];
  String category = 'academic_progress';
  bool busy = false, internal = false;
  String? error;
  @override
  void initState() {
    super.initState();
    internal =
        !widget.auth.isParent && !widget.auth.can('communication.manage');
  }

  @override
  void dispose() {
    subject.dispose();
    message.dispose();
    super.dispose();
  }

  Future<void> send() async {
    if (!form.currentState!.validate() || busy) return;
    setState(() => busy = true);
    try {
      await widget.auth.call(
        widget.ticketId == null
            ? 'tickets'
            : 'tickets/${widget.ticketId}/messages',
        body: {
          if (widget.ticketId == null) ...{
            'student_id': widget.studentId,
            'subject': subject.text.trim(),
            'category': category,
          } else
            'is_internal': internal,
          'message': message.text.trim(),
          'attachments': files,
          'client_id': client,
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiFailure catch (e) {
      error = e.code;
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          s.text(widget.ticketId == null ? 'contactCoordinator' : 'reply'),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpace.lg),
        child: SurfaceCard(
          child: Form(
            key: form,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (widget.ticketId == null) ...[
                  DropdownButtonFormField<String>(
                    initialValue: category,
                    isExpanded: true,
                    decoration: InputDecoration(labelText: s.text('category')),
                    items:
                        [
                              'academic_progress',
                              'homework',
                              'attendance',
                              'teacher_concern',
                              'student_behavior',
                              'examination',
                              'general',
                              'other',
                            ]
                            .map(
                              (k) => DropdownMenuItem(
                                value: k,
                                child: Text(s.text(k)),
                              ),
                            )
                            .toList(),
                    onChanged: busy
                        ? null
                        : (v) => setState(() => category = v!),
                  ),
                  const SizedBox(height: AppSpace.lg),
                  TextFormField(
                    controller: subject,
                    enabled: !busy,
                    maxLength: 200,
                    decoration: InputDecoration(
                      labelText: s.text('subjectLine'),
                    ),
                    validator: (v) => v == null || v.trim().isEmpty
                        ? s.text('fieldRequired')
                        : null,
                  ),
                  const SizedBox(height: AppSpace.lg),
                ],
                TextFormField(
                  controller: message,
                  enabled: !busy,
                  minLines: 5,
                  maxLines: 12,
                  maxLength: 5000,
                  decoration: InputDecoration(labelText: s.text('message')),
                  validator: (v) => v == null || v.trim().isEmpty
                      ? s.text('fieldRequired')
                      : null,
                ),
                if (widget.auth.can('communication.manage'))
                  SwitchListTile(
                    title: Text(s.text('internalNote')),
                    subtitle: Text(s.text('internalNoteHelp')),
                    value: internal,
                    onChanged: busy
                        ? null
                        : (v) => setState(() => internal = v),
                  ),
                if (internal && !widget.auth.can('communication.manage'))
                  StatusBanner(message: s.text('forwardedStaffNote')),
                OutlinedButton.icon(
                  onPressed: busy || files.length >= 5
                      ? null
                      : () async {
                          setState(() => busy = true);
                          try {
                            final result = await const PrivateFileService()
                                .pickAndUpload(
                                  widget.auth,
                                  purpose: 'message',
                                  studentId: widget.studentId,
                                );
                            if (files.length + result.length > 5) {
                              throw const ApiFailure('invalidFile');
                            }
                            files.addAll(result.map((v) => v['id'] as String));
                          } on ApiFailure catch (e) {
                            error = e.code;
                          } finally {
                            if (mounted) setState(() => busy = false);
                          }
                        },
                  icon: const Icon(Icons.attach_file),
                  label: Text('${s.text('attachments')} (${files.length})'),
                ),
                if (error != null)
                  StatusBanner(message: s.text(error!), kind: StatusKind.error),
                const SizedBox(height: AppSpace.lg),
                FilledButton(
                  onPressed: busy ? null : send,
                  child: busy
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(s.text('send')),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
