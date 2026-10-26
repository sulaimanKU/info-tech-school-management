import 'package:flutter/material.dart';

import '../auth/auth_controller.dart';
import 'guardian_directory_screen.dart';

class GuardianScreen extends StatelessWidget {
  const GuardianScreen({
    super.key,
    required this.auth,
    required this.studentId,
  });
  final AuthController auth;
  final String studentId;
  @override
  Widget build(BuildContext context) =>
      GuardianDirectoryScreen(auth: auth, studentId: studentId);
}
