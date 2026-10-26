import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/school_brand.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import '../communication/push_service.dart';

class SupportScreen extends StatefulWidget {
  const SupportScreen({super.key, required this.auth, this.push});
  final AuthController auth;
  final SchoolPushService? push;
  @override
  State<SupportScreen> createState() => _SupportScreenState();
}

class _SupportScreenState extends State<SupportScreen> {
  String version = '';
  String? notice;
  bool busy = false;
  @override
  void initState() {
    super.initState();
    PackageInfo.fromPlatform()
        .then((v) {
          if (mounted) {
            setState(() => version = '${v.version} (${v.buildNumber})');
          }
        })
        .catchError((_) {
          if (mounted) setState(() => version = '1.0.0');
        });
  }

  Future<void> pushAction() async {
    setState(() => busy = true);
    try {
      if (widget.push!.enabled) {
        await widget.push!.disable();
      } else {
        final ok = await widget.push!.enable();
        if (!ok) notice = 'pushPermissionHelp';
      }
    } catch (_) {
      notice = 'network';
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('aboutSupport'))),
      body: ListView(
        padding: const EdgeInsets.all(AppSpace.lg),
        children: [
          const Center(child: SchoolLogo(size: 96)),
          const SizedBox(height: AppSpace.lg),
          Text(
            s.text('schoolName'),
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleLarge,
          ),
          Text(s.text('campus'), textAlign: TextAlign.center),
          Text(
            '${s.text('appVersion')}: $version',
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: AppSpace.xl),
          for (final key in [
            'accountSupport',
            'privacyPolicy',
            'termsConditions',
          ])
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpace.lg),
              child: SurfaceCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      s.text(key),
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: AppSpace.md),
                    Text(s.text('${key}Body')),
                  ],
                ),
              ),
            ),
          if (widget.auth.status == AuthStatus.signedIn)
            SurfaceCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    s.text('pushNotifications'),
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  Text(
                    s.text(
                      SchoolPushService.configured
                          ? 'pushPrivacy'
                          : 'pushNotConfigured',
                    ),
                  ),
                  if (SchoolPushService.configured && widget.push != null)
                    FilledButton(
                      onPressed: busy ? null : pushAction,
                      child: Text(
                        s.text(
                          widget.push!.enabled ? 'disablePush' : 'enablePush',
                        ),
                      ),
                    ),
                  if (notice != null) Text(s.text(notice!)),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
