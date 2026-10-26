import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../design/semantic_tokens.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/authorized_photo.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';

class ChildProfileCard extends StatelessWidget {
  const ChildProfileCard({
    super.key,
    required this.auth,
    required this.child,
    this.compact = false,
  });
  final AuthController auth;
  final Map<String, dynamic> child;
  final bool compact;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final enrollment = child['enrollment'] as Map?;
    return SurfaceCard(
      accent: AppPalette.of(context).education,
      padding: const EdgeInsets.all(AppSpace.md),
      child: Row(
        children: [
          AuthorizedPhoto(
            auth: auth,
            fileId: child['photo_file_id'] as String?,
            name: child['full_name'] as String?,
            size: compact ? 48 : 64,
          ),
          const SizedBox(width: AppSpace.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  child['full_name']?.toString() ?? '',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                Text(
                  child['registration_number']?.toString() ?? '',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                Text(
                  enrollment == null
                      ? s.text('noActiveEnrollment')
                      : '${enrollment['class_name']} · ${enrollment['section_name']} · ${s.text('rollNumber')} ${enrollment['roll_number']}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                if (!compact && child['status'] != null)
                  AppBadge(
                    label: s.text(child['status'] as String),
                    status: child['status'] as String,
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
