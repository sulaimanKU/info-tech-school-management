import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/api_client.dart';
import '../../../design/app_theme.dart';
import '../../../design/semantic_tokens.dart';
import '../../../l10n/strings.dart';
import '../../../shared/widgets/surface_card.dart';
import '../auth_controller.dart';

class ParentLoginForm extends StatefulWidget {
  const ParentLoginForm({super.key, required this.auth});
  final AuthController auth;
  @override
  State<ParentLoginForm> createState() => _ParentLoginFormState();
}

class _ParentLoginFormState extends State<ParentLoginForm> {
  final cnic = TextEditingController();
  final otp = TextEditingController();
  final form = GlobalKey<FormState>();
  String? challenge, error;
  bool busy = false, remember = false;
  int cooldown = 0;
  int expires = 0;
  Timer? timer;
  @override
  void dispose() {
    timer?.cancel();
    cnic.dispose();
    otp.dispose();
    super.dispose();
  }

  Future<void> request() async {
    if (busy || cooldown > 0) return;
    if (!RegExp(r'^\d{13}$').hasMatch(cnic.text)) {
      form.currentState!.validate();
      return;
    }
    setState(() {
      busy = true;
      error = null;
      widget.auth.error = null;
    });
    try {
      final data = await widget.auth.requestParentCode(
        cnic.text.replaceAll(RegExp(r'[ -]'), ''),
      );
      if (!mounted) return;
      setState(() {
        challenge = data['challenge_id'] as String;
        cooldown = (data['resend_after'] as num).toInt();
        expires = (data['expires_in'] as num).toInt();
        otp.clear();
      });
      timer?.cancel();
      timer = Timer.periodic(const Duration(seconds: 1), (t) {
        if (!mounted) {
          t.cancel();
          return;
        }
        setState(() {
          if (cooldown > 0) cooldown--;
          if (expires > 0) expires--;
        });
        if (cooldown <= 0 && expires <= 0) t.cancel();
      });
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> verify() async {
    if (!form.currentState!.validate() || busy || widget.auth.busy) return;
    if (expires <= 0) {
      setState(() => error = 'otpExpired');
      return;
    }
    setState(() => error = null);
    await widget.auth.verifyParentCode(
      challenge!,
      otp.text,
      rememberMe: remember,
    );
    if (mounted) otp.clear();
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final waiting = busy || widget.auth.busy;
    return SurfaceCard(
      accent: AppPalette.of(context).education,
      child: Form(
        key: form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              s.text('parentLogin'),
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: AppSpace.sm),
            Text(s.text('parentIntro')),
            const SizedBox(height: AppSpace.xl),
            TextFormField(
              key: const Key('parent-cnic'),
              controller: cnic,
              enabled: !waiting && challenge == null,
              textDirection: TextDirection.ltr,
              keyboardType: TextInputType.number,
              inputFormatters: [
                FilteringTextInputFormatter.digitsOnly,
                LengthLimitingTextInputFormatter(13),
              ],
              decoration: InputDecoration(
                labelText: s.text('cnic'),
                helperText: s.text('cnicHelper'),
                prefixIcon: const Icon(Icons.badge_outlined),
              ),
              validator: (v) => RegExp(r'^\d{13}$').hasMatch(v ?? '')
                  ? null
                  : s.text('cnicInvalid'),
            ),
            if (challenge != null) ...[
              const SizedBox(height: AppSpace.lg),
              StatusBanner(
                message: s.text(expires > 0 ? 'otpNeutral' : 'otpExpired'),
              ),
              if (expires > 0)
                Padding(
                  padding: const EdgeInsets.only(top: AppSpace.sm),
                  child: Text(
                    '${s.text('codeExpires')} ${expires ~/ 60}:${(expires % 60).toString().padLeft(2, '0')}',
                    textAlign: TextAlign.center,
                  ),
                ),
              const SizedBox(height: AppSpace.lg),
              TextFormField(
                key: const Key('parent-otp'),
                controller: otp,
                enabled: !waiting,
                textDirection: TextDirection.ltr,
                keyboardType: TextInputType.number,
                obscureText: true,
                inputFormatters: [
                  FilteringTextInputFormatter.digitsOnly,
                  LengthLimitingTextInputFormatter(6),
                ],
                decoration: InputDecoration(
                  labelText: s.text('otp'),
                  prefixIcon: const Icon(Icons.password_rounded),
                ),
                validator: (v) => RegExp(r'^\d{6}$').hasMatch(v ?? '')
                    ? null
                    : s.text('otpInvalid'),
              ),
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(s.text('rememberMe')),
                value: remember,
                onChanged: waiting
                    ? null
                    : (v) => setState(() => remember = v ?? false),
              ),
            ],
            if (error != null || widget.auth.error != null)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: AppSpace.lg),
                child: StatusBanner(
                  message: s.text(
                    error ??
                        (widget.auth.error == 'unauthorized'
                            ? 'otpRejected'
                            : widget.auth.error!),
                  ),
                  kind: StatusKind.error,
                ),
              ),
            const SizedBox(height: AppSpace.xl),
            FilledButton(
              onPressed: waiting
                  ? null
                  : challenge == null
                  ? request
                  : verify,
              child: waiting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(
                      s.text(challenge == null ? 'requestCode' : 'verifyCode'),
                    ),
            ),
            if (challenge != null)
              TextButton(
                onPressed: waiting || cooldown > 0
                    ? null
                    : () {
                        otp.clear();
                        request();
                      },
                child: Text(
                  cooldown > 0
                      ? '${s.text('resendCode')} ($cooldown)'
                      : s.text('resendCode'),
                ),
              ),
            if (challenge != null)
              TextButton.icon(
                key: const Key('correct-parent-identity'),
                onPressed: waiting
                    ? null
                    : () {
                        timer?.cancel();
                        setState(() {
                          challenge = null;
                          error = null;
                          cooldown = 0;
                          expires = 0;
                          otp.clear();
                          widget.auth.error = null;
                        });
                      },
                icon: const Icon(Icons.edit_outlined),
                label: Text(s.text('correctCnic')),
              ),
            TextButton(
              onPressed: waiting
                  ? null
                  : () => showDialog<void>(
                      context: context,
                      builder: (c) => AlertDialog(
                        title: Text(s.text('accountSupport')),
                        content: Text(s.text('parentSupport')),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.pop(c),
                            child: Text(s.text('close')),
                          ),
                        ],
                      ),
                    ),
              child: Text(s.text('accountSupport')),
            ),
          ],
        ),
      ),
    );
  }
}
