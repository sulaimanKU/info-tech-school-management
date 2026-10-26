import 'package:flutter/material.dart';

import '../../l10n/strings.dart';

class AppToolbar extends StatelessWidget {
  const AppToolbar({
    super.key,
    required this.onLanguage,
    required this.themeMode,
    required this.onTheme,
  });
  final VoidCallback onLanguage;
  final ThemeMode themeMode;
  final ValueChanged<ThemeMode> onTheme;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final urdu = Localizations.localeOf(context).languageCode == 'ur';
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Tooltip(
          message: 'English / اردو',
          child: TextButton(
            key: const Key('language-toggle'),
            onPressed: onLanguage,
            child: Text(urdu ? 'EN' : 'اردو'),
          ),
        ),
        PopupMenuButton<ThemeMode>(
          key: const Key('theme-menu'),
          tooltip: s.text('appearance'),
          initialValue: themeMode,
          icon: const Icon(Icons.contrast_rounded),
          onSelected: onTheme,
          itemBuilder: (_) => ThemeMode.values
              .map(
                (value) => PopupMenuItem(
                  value: value,
                  child: Row(
                    children: [
                      Icon(switch (value) {
                        ThemeMode.system => Icons.brightness_auto_outlined,
                        ThemeMode.light => Icons.light_mode_outlined,
                        ThemeMode.dark => Icons.dark_mode_outlined,
                      }, size: 20),
                      const SizedBox(width: 12),
                      Text(s.text(value.name)),
                    ],
                  ),
                ),
              )
              .toList(),
        ),
      ],
    );
  }
}
