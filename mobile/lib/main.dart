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
  // Draw immediately; resolve preferences and secure session behind branded UI.
  runApp(SchoolApp(auth: auth, preferences: preferences, initialize: true));
}
