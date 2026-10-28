import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final form = GlobalKey<FormState>();
  final current = TextEditingController(),
      password = TextEditingController(),
      confirmation = TextEditingController();
  bool busy = false;
  final visible = [false, false, false];
  String? error;
  bool wrongCurrent = false;
  @override
  void dispose() {
    current.dispose();
    password.dispose();
    confirmation.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (busy || !form.currentState!.validate()) return;
    setState(() {
      busy = true;
      error = null;
      wrongCurrent = false;
    });
    try {
      await widget.auth.call(
        'account/password',
        body: {
          'current_password': current.text,
          'password': password.text,
          'password_confirmation': confirmation.text,
        },
      );
      current.clear();
      password.clear();
      confirmation.clear();
      await widget.auth.logout();
      if (mounted) {
        final messenger = ScaffoldMessenger.of(context);
        final message = Strings.of(context).text('accountPasswordChanged');
        Navigator.of(context).popUntil((r) => r.isFirst);
        messenger.showSnackBar(SnackBar(content: Text(message)));
      }
    } on ApiFailure catch (e) {
      if (mounted) {
        setState(() {
          error = e.code;
          wrongCurrent = e.fields.containsKey('current_password');
        });
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final controllers = [current, password, confirmation];
    final labels = [
      'accountCurrentPassword',
      'accountNewPassword',
      'accountConfirmPassword',
    ];
    return Scaffold(
      appBar: AppBar(title: Text(s.text('accountChangePassword'))),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpace.lg),
        child: SurfaceCard(
          child: Form(
            key: form,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                StatusBanner(message: s.text('accountPasswordPolicy')),
                const SizedBox(height: AppSpace.lg),
                for (var i = 0; i < 3; i++)
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpace.lg),
                    child: TextFormField(
                      key: Key(labels[i]),
                      controller: controllers[i],
                      enabled: !busy,
                      obscureText: !visible[i],
                      autocorrect: false,
                      enableSuggestions: false,
                      maxLength: 1024,
                      decoration: InputDecoration(
                        labelText: s.text(labels[i]),
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          tooltip: s.text(
                            visible[i] ? 'hidePassword' : 'showPassword',
                          ),
                          onPressed: () =>
                              setState(() => visible[i] = !visible[i]),
                          icon: Icon(
                            visible[i]
                                ? Icons.visibility_off_outlined
                                : Icons.visibility_outlined,
                          ),
                        ),
                      ),
                      validator: (v) {
                        if (v == null || v.isEmpty) {
                          return s.text('fieldRequired');
                        }
                        if (i == 1 && v.length < 12) {
                          return s.text('accountPasswordLength');
                        }
                        if (i == 1 && v == current.text) {
                          return s.text('accountPasswordDifferent');
                        }
                        if (i == 2 && v != password.text) {
                          return s.text('accountPasswordMismatch');
                        }
                        return null;
                      },
                    ),
                  ),
                if (error != null)
                  StatusBanner(
                    message: s.text(
                      wrongCurrent ? 'accountWrongPassword' : error!,
                    ),
                    kind: StatusKind.error,
                  ),
                FilledButton.icon(
                  key: const Key('save-own-password'),
                  onPressed: busy ? null : save,
                  icon: busy
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.lock_reset),
                  label: Text(s.text('accountChangePassword')),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
