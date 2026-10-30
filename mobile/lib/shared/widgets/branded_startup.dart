import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../design/semantic_tokens.dart';
import '../../l10n/strings.dart';

/// Shown only while secure session/preferences are resolving. This entrance is
/// finite; the caller can leave immediately when initialization completes.
class BrandedStartup extends StatelessWidget {
  const BrandedStartup({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final palette = AppPalette.of(context);
    final reducedMotion = MediaQuery.disableAnimationsOf(context);
    return Scaffold(
      key: const Key('branded-startup'),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpace.xl),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: TweenAnimationBuilder<double>(
                tween: Tween(begin: reducedMotion ? 1 : 0, end: 1),
                duration: reducedMotion
                    ? Duration.zero
                    : const Duration(milliseconds: 1800),
                curve: Curves.easeOutCubic,
                builder: (context, value, child) => Opacity(
                  opacity: value,
                  child: Transform.translate(
                    offset: Offset(0, 14 * (1 - value)),
                    child: Transform.scale(
                      scale: .94 + .06 * value,
                      child: child,
                    ),
                  ),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Semantics(
                      label: 'TreadExellence',
                      image: true,
                      child: ExcludeSemantics(
                        child: Container(
                          width: 88,
                          height: 88,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: palette.selected,
                            borderRadius: BorderRadius.circular(24),
                            border: Border.all(
                              color: palette.primary.withValues(alpha: .25),
                            ),
                          ),
                          // Typography is an explicit placeholder, not a
                          // replacement logo. See assets/branding/README.md.
                          child: Text(
                            'TE',
                            textDirection: TextDirection.ltr,
                            textScaler: TextScaler.noScaling,
                            style: theme.textTheme.headlineLarge?.copyWith(
                              color: palette.primary,
                              letterSpacing: -2,
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpace.xl),
                    Text(
                      'TreadExellence',
                      textDirection: TextDirection.ltr,
                      textAlign: TextAlign.center,
                      style: theme.textTheme.headlineMedium?.copyWith(
                        color: palette.text,
                      ),
                    ),
                    const SizedBox(height: AppSpace.sm),
                    Text(
                      'Technology for Better Education',
                      textDirection: TextDirection.ltr,
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: palette.supporting,
                      ),
                    ),
                    const SizedBox(height: AppSpace.xxl),
                    SizedBox(
                      width: 120,
                      child: LinearProgressIndicator(
                        semanticsLabel: Strings.of(context)
                            .text('loadingWorkspace'),
                        // Static when the platform requests reduced motion.
                        value: reducedMotion ? .5 : null,
                        minHeight: 3,
                        borderRadius: BorderRadius.circular(4),
                      ),
                    ),
                    const SizedBox(height: AppSpace.xl),
                    Text(
                      'INFO-TECH High School & Educational Campus',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: palette.supporting,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
