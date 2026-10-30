import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import 'surface_card.dart';

/// Displays the existing dated assignment rows without exposing internal IDs.
class StaffAssignmentSummary extends StatelessWidget {
  const StaffAssignmentSummary({super.key, required this.assignments});
  final List<dynamic> assignments;

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    if (assignments.isEmpty) {
      return SurfaceCard(
        child: Column(
          children: [
            const Icon(Icons.school_outlined, size: 40),
            const SizedBox(height: AppSpace.md),
            Text(s.text('staffTeachingEmpty'), textAlign: TextAlign.center),
          ],
        ),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final row in assignments)
          Padding(
            padding: const EdgeInsets.only(bottom: AppSpace.md),
            child: SurfaceCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${row['class_name']} · ${row['section_name']}',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  Text(
                    row['session_name']?.toString() ?? s.text('notRecorded'),
                  ),
                  Text(
                    row['subject_name']?.toString() ??
                        s.text(
                          row['kind'] == 'coordinator'
                              ? 'coordinator'
                              : 'classTeacher',
                        ),
                  ),
                  const SizedBox(height: AppSpace.sm),
                  AppBadge(
                    label: s.text(
                      row['is_active'] == true ? 'active' : 'inactive',
                    ),
                    status: row['is_active'] == true ? 'active' : 'inactive',
                  ),
                  if (row['starts_on'] != null || row['ends_on'] != null)
                    Text(
                      '${s.text('startDate')}: ${row['starts_on'] ?? s.text('anyValue')} · ${s.text('endDate')}: ${row['ends_on'] ?? s.text('anyValue')}',
                    ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}
