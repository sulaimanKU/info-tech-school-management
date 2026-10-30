import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/academic_filters.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/polished_components.dart';
import '../auth/auth_controller.dart';

class StaffAssignmentScreen extends StatefulWidget {
  const StaffAssignmentScreen({
    super.key,
    required this.auth,
    required this.profileId,
    required this.coordinator,
    this.existing,
  });
  final AuthController auth;
  final String profileId;
  final bool coordinator;
  final Map<String, dynamic>? existing;
  @override
  State<StaffAssignmentScreen> createState() => _StaffAssignmentScreenState();
}

class _StaffAssignmentScreenState extends State<StaffAssignmentScreen> {
  Map<String, String> selection = {};
  bool active = true, classTeacher = false, busy = false;
  String? start, end, error;
  @override
  void initState() {
    super.initState();
    final old = widget.existing;
    for (final k in [
      'academic_session_id',
      'class_id',
      'section_id',
      'subject_id',
    ]) {
      if (old?[k] != null) selection[k] = old![k] as String;
    }
    active = old?['is_active'] as bool? ?? true;
    classTeacher = old?['is_class_teacher'] as bool? ?? false;
    start = old?['starts_on'] as String?;
    end = old?['ends_on'] as String?;
  }

  Future<void> save() async {
    if (busy) return;
    if (selection['section_id'] == null ||
        (!widget.coordinator &&
            !classTeacher &&
            selection['subject_id'] == null)) {
      setState(() => error = 'fieldRequired');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final kind = widget.coordinator
          ? 'coordinator-assignments'
          : 'teacher-assignments';
      await widget.auth.call(
        'catalog/$kind${widget.existing == null ? '' : '/${widget.existing!['id']}'}',
        method: widget.existing == null ? 'POST' : 'PATCH',
        body: {
          'staff_profile_id': widget.profileId,
          'section_id': selection['section_id'],
          'is_active': active,
          'starts_on': start,
          'ends_on': end,
          if (!widget.coordinator) 'subject_id': selection['subject_id'],
          if (!widget.coordinator) 'is_class_teacher': classTeacher,
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiFailure catch (e) {
      if (mounted) {
        setState(
          () => error =
              e.fields.values
                  .whereType<List>()
                  .firstOrNull
                  ?.firstOrNull
                  ?.toString() ??
              e.code,
        );
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> remove() async {
    final s = Strings.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(s.text('removeAssignment')),
        content: Text(s.text('removeAssignmentHelp')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: Text(s.text('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: Text(s.text('confirm')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted || busy) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final kind = widget.coordinator
          ? 'coordinator-assignments'
          : 'teacher-assignments';
      await widget.auth.call(
        'catalog/$kind/${widget.existing!['id']}/deactivate',
        method: 'POST',
        body: {},
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
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
          s.text(
            widget.coordinator ? 'coordinatorAssignments' : 'staffAssignments',
          ),
        ),
      ),
      body: PageBody(
        child: ListView(
          padding: const EdgeInsets.all(AppSpace.lg),
          children: [
            SurfaceCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  AcademicSelection(
                    preferCurrentSession: true,
                    auth: widget.auth,
                    initial: selection,
                    subject: !widget.coordinator,
                    onChanged: (v) => setState(() => selection = v),
                  ),
                  if (!widget.coordinator)
                    SwitchListTile(
                      title: Text(s.text('classTeacher')),
                      value: classTeacher,
                      onChanged: busy
                          ? null
                          : (v) => setState(() => classTeacher = v),
                    ),
                  SwitchListTile(
                    title: Text(s.text('assignmentActive')),
                    value: active,
                    onChanged: busy ? null : (v) => setState(() => active = v),
                  ),
                  for (final from in [true, false])
                    OutlinedButton.icon(
                      onPressed: busy
                          ? null
                          : () async {
                              final date = await showDatePicker(
                                context: context,
                                initialDate:
                                    DateTime.tryParse(
                                      (from ? start : end) ?? '',
                                    ) ??
                                    DateTime.now(),
                                firstDate: DateTime(2000),
                                lastDate: DateTime(2200),
                              );
                              if (date != null && mounted) {
                                setState(() {
                                  if (from) {
                                    start = date.toIso8601String().substring(
                                      0,
                                      10,
                                    );
                                  } else {
                                    end = date.toIso8601String().substring(
                                      0,
                                      10,
                                    );
                                  }
                                });
                              }
                            },
                      icon: const Icon(Icons.date_range),
                      label: Text(
                        '${s.text(from ? 'startDate' : 'endDate')}: ${(from ? start : end) ?? s.text('anyValue')}',
                      ),
                    ),
                  if (error != null)
                    StatusBanner(
                      message: s.text(error!),
                      kind: StatusKind.error,
                    ),
                  const SizedBox(height: AppSpace.lg),
                  FilledButton(
                    onPressed: busy ? null : save,
                    child: Text(s.text('save')),
                  ),
                  if (widget.existing != null && active)
                    TextButton.icon(
                      onPressed: busy ? null : remove,
                      icon: const Icon(Icons.remove_circle_outline),
                      label: Text(s.text('removeAssignment')),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
