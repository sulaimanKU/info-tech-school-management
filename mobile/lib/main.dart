import 'package:flutter/material.dart';

import 'app/school_app.dart';
import 'app/app_preferences.dart';
import 'core/local_store.dart';
import 'core/api_client.dart';
import 'features/auth/auth_controller.dart';
import 'features/auth/session_store.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final auth = AuthController(ApiClient(), SecureSessionStore());
  final preferences = AppPreferences(const SecureLocalStore());
  try {
    await preferences.load();
  } catch (_) {
    /* Login remains available if preference storage cannot be read. */
  }
  runApp(SchoolApp(auth: auth, preferences: preferences));
  auth.initialize();
}
