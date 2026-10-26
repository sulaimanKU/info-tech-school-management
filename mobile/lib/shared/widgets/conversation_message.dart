import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../design/semantic_tokens.dart';
import '../../l10n/strings.dart';

class ConversationMessage extends StatelessWidget {
  const ConversationMessage({
    super.key,
    required this.message,
    this.attachments = const [],
  });
  final Map<String, dynamic> message;
  final List<Widget> attachments;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final palette = AppPalette.of(context);
    final internal = message['is_internal'] == true;
    final parent = message['sender_kind'] == 'parent';
    final kind = internal
        ? 'staffOnlyNote'
        : parent
        ? 'parentMessage'
        : message['sender_kind'] == 'staff'
        ? 'staffReply'
        : 'publicMessage';
    final color = internal
        ? palette.warning
        : parent
        ? palette.primary
        : palette.information;
    final time = DateTime.tryParse(message['created_at'].toString())?.toLocal();
    final timestamp = time == null
        ? ''
        : '${MaterialLocalizations.of(context).formatFullDate(time)} · ${MaterialLocalizations.of(context).formatTimeOfDay(TimeOfDay.fromDateTime(time))}';
    return Align(
      alignment: parent
          ? AlignmentDirectional.centerStart
          : AlignmentDirectional.centerEnd,
      child: FractionallySizedBox(
        widthFactor: MediaQuery.sizeOf(context).width >= 600 ? .85 : 1,
        child: Container(
          margin: const EdgeInsets.only(bottom: AppSpace.lg),
          padding: const EdgeInsets.all(AppSpace.lg),
          decoration: BoxDecoration(
            color: internal ? color.withValues(alpha: .10) : palette.surface,
            borderRadius: BorderRadius.circular(AppRadius.card),
            border: Border.all(color: color.withValues(alpha: .35)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    internal
                        ? Icons.lock_outline_rounded
                        : parent
                        ? Icons.family_restroom_outlined
                        : Icons.support_agent_rounded,
                    color: color,
                    size: 22,
                  ),
                  const SizedBox(width: AppSpace.sm),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          message['sender_name'] as String,
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        Text(
                          s.text(kind),
                          style: Theme.of(context).textTheme.labelMedium
                              ?.copyWith(color: color),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpace.md),
              SelectableText(message['body'] as String),
              ...attachments,
              const SizedBox(height: AppSpace.md),
              Text(
                timestamp,
                style: Theme.of(context).textTheme.bodySmall
                    ?.copyWith(color: palette.supporting),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
