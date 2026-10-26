import 'dart:convert';

import 'package:flutter/material.dart';

import '../core/local_store.dart';

class AppPreferences {
  AppPreferences(this.store);
  final LocalStore store;
  Locale? locale;
  ThemeMode mode = ThemeMode.system;
  Future<void> load() async {
    final value = await store.read('preferences');
    if (value == null) return;
    final data = jsonDecode(value) as Map;
    if (['en', 'ur'].contains(data['locale'])) {
      locale = Locale(data['locale'] as String);
    }
    mode = ThemeMode.values.firstWhere(
      (m) => m.name == data['theme'],
      orElse: () => ThemeMode.system,
    );
  }

  Future<void> save({required Locale? locale, required ThemeMode mode}) async {
    await store.write(
      'preferences',
      jsonEncode({'locale': locale?.languageCode, 'theme': mode.name}),
    );
    this.locale = locale;
    this.mode = mode;
  }
}
