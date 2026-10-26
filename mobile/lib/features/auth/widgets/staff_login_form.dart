import 'package:flutter/material.dart';

import '../../../design/app_theme.dart';
import '../../../design/semantic_tokens.dart';
import '../../../l10n/strings.dart';
import '../../../shared/widgets/school_brand.dart';
import '../../../shared/widgets/surface_card.dart';
import '../auth_controller.dart';

class StaffLoginForm extends StatefulWidget {
  const StaffLoginForm({super.key, required this.auth, this.showLogo = false});
  final AuthController auth;
  final bool showLogo;
  @override
  State<StaffLoginForm> createState() => _StaffLoginFormState();
}

class _StaffLoginFormState extends State<StaffLoginForm> {
  final email = TextEditingController();
  final password = TextEditingController();
  final form = GlobalKey<FormState>();
  bool rememberMe = false;
  bool hidden = true;
  @override
  void dispose() {
    email.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    if (widget.auth.busy || !form.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    await widget.auth.login(email.text, password.text, rememberMe: rememberMe);
    if (mounted) {
      password.clear();
      setState(() => hidden = true);
    }
  }

  void forgotPassword() {
    final s = Strings.of(context);
    showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        icon: const Icon(Icons.lock_reset_rounded),
        title: Text(s.text('forgotPassword')),
        content: Text(s.text('resetHelp')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(s.text('understood')),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final auth = widget.auth;
    final theme = Theme.of(context);
    return SurfaceCard(
      accent: AppPalette.of(context).primary,
      child: Form(
        key: form,
        autovalidateMode: AutovalidateMode.onUserInteraction,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (widget.showLogo) ...[
              const Align(
                alignment: AlignmentDirectional.centerStart,
                child: SchoolLogo(size: 80),
              ),
              const SizedBox(height: AppSpace.xl),
            ],
            Text(
              s.text('staffAccess'),
              style: theme.textTheme.labelMedium?.copyWith(
                color: theme.colorScheme.primary,
                fontWeight: FontWeight.w700,
                letterSpacing: 1.2,
              ),
            ),
            const SizedBox(height: AppSpace.sm),
            Text(s.text('staff'), style: theme.textTheme.headlineSmall),
            const SizedBox(height: AppSpace.sm),
            Text(
              s.text('intro'),
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: AppSpace.xxl),
            TextFormField(
              key: const Key('email-field'),
              controller: email,
              enabled: !auth.busy,
              textDirection: TextDirection.ltr,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              autocorrect: false,
              enableSuggestions: false,
              decoration: InputDecoration(
                labelText: s.text('email'),
                hintText: s.text('emailHint'),
                prefixIcon: const Icon(Icons.alternate_email_rounded),
              ),
              validator: (v) =>
                  v != null &&
                      v.trim().length <= 254 &&
                      RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(v.trim())
                  ? null
                  : s.text('emailInvalid'),
            ),
            const SizedBox(height: AppSpace.lg),
            TextFormField(
              key: const Key('password-field'),
              controller: password,
              enabled: !auth.busy,
              obscureText: hidden,
              autocorrect: false,
              enableSuggestions: false,
              textInputAction: TextInputAction.done,
              onFieldSubmitted: (_) => submit(),
              decoration: InputDecoration(
                labelText: s.text('password'),
                prefixIcon: const Icon(Icons.lock_outline_rounded),
                suffixIcon: IconButton(
                  key: const Key('password-visibility'),
                  tooltip: s.text(hidden ? 'showPassword' : 'hidePassword'),
                  onPressed: auth.busy
                      ? null
                      : () => setState(() => hidden = !hidden),
                  icon: Icon(
                    hidden
                        ? Icons.visibility_outlined
                        : Icons.visibility_off_outlined,
                  ),
                ),
              ),
              validator: (v) => v != null && v.isNotEmpty && v.length <= 1024
                  ? null
                  : s.text('passwordRequired'),
            ),
            const SizedBox(height: AppSpace.sm),
            Wrap(
              alignment: WrapAlignment.spaceBetween,
              crossAxisAlignment: WrapCrossAlignment.center,
              spacing: AppSpace.sm,
              children: [
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Checkbox(
                      key: const Key('remember-me'),
                      value: rememberMe,
                      semanticLabel: s.text('rememberMe'),
                      onChanged: auth.busy
                          ? null
                          : (v) => setState(() => rememberMe = v ?? false),
                    ),
                    Flexible(
                      child: GestureDetector(
                        onTap: auth.busy
                            ? null
                            : () => setState(() => rememberMe = !rememberMe),
                        child: Text(
                          s.text('rememberMe'),
                          style: theme.textTheme.bodyMedium,
                        ),
                      ),
                    ),
                  ],
                ),
                TextButton(
                  key: const Key('forgot-password'),
                  onPressed: auth.busy ? null : forgotPassword,
                  child: Text(s.text('forgotPassword')),
                ),
              ],
            ),
            if (rememberMe)
              Padding(
                padding: const EdgeInsets.only(top: AppSpace.sm),
                child: Text(
                  s.text('rememberHint'),
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
            if (auth.error != null)
              Padding(
                padding: const EdgeInsets.only(top: AppSpace.lg),
                child: StatusBanner(
                  message: s.text(auth.error!),
                  kind: StatusKind.error,
                ),
              ),
            const SizedBox(height: AppSpace.xl),
            FilledButton(
              key: const Key('sign-in'),
              onPressed: auth.busy ? null : submit,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (auth.busy) ...[
                    SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(width: AppSpace.md),
                  ],
                  Flexible(
                    child: Text(s.text(auth.busy ? 'signingIn' : 'login')),
                  ),
                  if (!auth.busy) ...[
                    const SizedBox(width: AppSpace.md),
                    const Icon(Icons.arrow_forward_rounded, size: 20),
                  ],
                ],
              ),
            ),
            const SizedBox(height: AppSpace.xl),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  Icons.verified_user_outlined,
                  size: 16,
                  color: theme.colorScheme.onSurfaceVariant,
                ),
                const SizedBox(width: AppSpace.sm),
                Flexible(
                  child: Text(
                    s.text('privateAccess'),
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
