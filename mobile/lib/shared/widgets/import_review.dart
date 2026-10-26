import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../design/semantic_tokens.dart';
import '../../l10n/strings.dart';
import 'polished_components.dart';
import 'surface_card.dart';

class ImportProgress extends StatelessWidget {
  const ImportProgress({super.key, required this.status});
  final String status;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final stage = switch (status) {
      'confirmed' => 4,
      'validated' => 3,
      'invalid' || 'previewed' => 2,
      _ => 1,
    };
    const steps = [
      'importSelect',
      'importPreview',
      'importReview',
      'importConfirm',
      'importResult',
    ];
    return SurfaceCard(
      padding: const EdgeInsets.all(AppSpace.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          LinearProgressIndicator(
            value: (stage + 1) / 5,
            minHeight: 4,
            borderRadius: BorderRadius.circular(4),
          ),
          const SizedBox(height: AppSpace.md),
          Wrap(
            spacing: AppSpace.sm,
            runSpacing: AppSpace.sm,
            children: [
              for (var i = 0; i < steps.length; i++)
                AppBadge(
                  label: '${i + 1} · ${s.text(steps[i])}',
                  status: i < stage
                      ? 'saved'
                      : i == stage
                      ? 'in_progress'
                      : 'draft',
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class ImportReview extends StatelessWidget {
  const ImportReview({super.key, required this.report, this.confirmed = false});
  final Map<String, dynamic> report;
  final bool confirmed;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final errors = report['errors'];
    final warnings = report['warnings'] as List? ?? [];
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpace.lg),
      child: SurfaceCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AppBadge(
              label: s.text(
                confirmed
                    ? 'confirmed'
                    : report['valid'] == true
                    ? 'validationPassed'
                    : 'validationIssues',
              ),
              status: confirmed
                  ? 'confirmed'
                  : report['valid'] == true
                  ? 'validated'
                  : 'invalid',
            ),
            if (report['row_count'] != null)
              ValueRow(
                label: s.text('importRows'),
                value: report['row_count'].toString(),
              ),
            if (!confirmed)
              Text(
                s.text('importAllOrNothing'),
                style: Theme.of(context).textTheme.bodySmall,
              ),
            if (warnings.isNotEmpty) ...[
              const SizedBox(height: AppSpace.lg),
              SectionHeader(
                title: s.text('importWarnings'),
                icon: Icons.warning_amber_rounded,
              ),
              for (final warning in warnings)
                Padding(
                  padding: const EdgeInsets.only(bottom: AppSpace.sm),
                  child: Text(
                    warning.toString(),
                    style: TextStyle(color: AppPalette.of(context).warning),
                  ),
                ),
            ],
            if (errors is Map && errors.isNotEmpty) ...[
              const SizedBox(height: AppSpace.lg),
              SectionHeader(
                title: s.text('validationIssues'),
                icon: Icons.error_outline,
              ),
              for (final e in errors.entries)
                Padding(
                  padding: const EdgeInsets.only(bottom: AppSpace.md),
                  child: StatusBanner(
                    message:
                        '${s.text(e.key.toString())}: ${e.value is List ? (e.value as List).join('\n') : e.value}',
                    kind: StatusKind.error,
                  ),
                ),
            ] else if (errors is List)
              for (final e in errors)
                StatusBanner(message: e.toString(), kind: StatusKind.error),
          ],
        ),
      ),
    );
  }
}
