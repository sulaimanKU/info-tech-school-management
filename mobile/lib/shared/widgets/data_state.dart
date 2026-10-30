import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../design/semantic_tokens.dart';
import '../../l10n/strings.dart';
import 'surface_card.dart';

class DataState extends StatelessWidget {
  const DataState({
    super.key,
    this.error,
    this.onRetry,
    this.empty = false,
    this.emptyMessage,
    this.emptyIcon = Icons.inbox_outlined,
  });
  final String? error;
  final VoidCallback? onRetry;
  final bool empty;
  final String? emptyMessage;
  final IconData emptyIcon;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final palette = AppPalette.of(context);
    final content = Padding(
      padding: const EdgeInsets.all(AppSpace.xl),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 480),
          child: error == null && !empty
              ? const LoadingState()
              : Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: (error == null ? palette.primary : palette.error)
                            .withValues(alpha: .08),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        switch (error) {
                          'network' => Icons.wifi_off_rounded,
                          'forbidden' => Icons.lock_outline_rounded,
                          'notFound' => Icons.search_off_rounded,
                          'conflict' => Icons.sync_problem_rounded,
                          'validation' => Icons.fact_check_outlined,
                          'unauthorized' => Icons.login_rounded,
                          null => emptyIcon,
                          _ => Icons.cloud_off_outlined,
                        },
                        size: 36,
                        color: error == null ? palette.primary : palette.error,
                      ),
                    ),
                    const SizedBox(height: AppSpace.lg),
                    Text(
                      s.text(error == null ? 'nothingHereYet' : 'unableToLoad'),
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: AppSpace.sm),
                    if (error != null)
                      StatusBanner(
                        message: s.text(
                          Strings.en.containsKey(error) ? error! : 'server',
                        ),
                        kind: StatusKind.error,
                      )
                    else
                      Text(
                        s.text(emptyMessage ?? 'noRecords'),
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodyMedium
                            ?.copyWith(color: palette.supporting),
                      ),
                    if (error != null && onRetry != null) ...[
                      const SizedBox(height: AppSpace.lg),
                      FilledButton.icon(
                        onPressed: onRetry,
                        icon: const Icon(Icons.refresh_rounded),
                        label: Text(s.text('retry')),
                      ),
                    ],
                  ],
                ),
        ),
      ),
    );
    return LayoutBuilder(
      builder: (context, constraints) => constraints.hasBoundedHeight
          ? SingleChildScrollView(child: content)
          : content,
    );
  }
}

/// Static skeleton avoids flashing, repeating motion and expensive shimmer layers.
class LoadingState extends StatelessWidget {
  const LoadingState({super.key});
  @override
  Widget build(BuildContext context) => Semantics(
    label: Strings.of(context).text('loadingWorkspace'),
    liveRegion: true,
    child: ExcludeSemantics(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (var i = 0; i < 2; i++)
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpace.md),
              child: SurfaceCard(
                padding: const EdgeInsets.all(AppSpace.lg),
                child: Row(
                  children: [
                    _bar(context, 44, 44),
                    const SizedBox(width: AppSpace.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _bar(context, double.infinity, 12),
                          const SizedBox(height: 10),
                          Align(
                            alignment: AlignmentDirectional.centerStart,
                            child: FractionallySizedBox(
                              widthFactor: .65,
                              child: _bar(context, double.infinity, 10),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    ),
  );
  Widget _bar(BuildContext context, double width, double height) => Container(
    width: width,
    height: height,
    decoration: BoxDecoration(
      color: AppPalette.of(context).border.withValues(alpha: .5),
      borderRadius: BorderRadius.circular(8),
    ),
  );
}
