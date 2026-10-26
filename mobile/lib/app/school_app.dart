import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import '../design/app_theme.dart';
import '../features/communication/push_service.dart';
import 'app_preferences.dart';
import '../features/auth/auth_controller.dart';
import '../features/auth/login_screen.dart';
import '../features/home/home_screen.dart';
import '../features/parents/parent_home_screen.dart';
import '../features/communication/notification_screen.dart';
import '../features/home/widgets/account_sheet.dart';
import '../l10n/strings.dart';
import '../shared/widgets/app_toolbar.dart';
import '../shared/widgets/school_brand.dart';
import '../shared/widgets/surface_card.dart';

class SchoolApp extends StatefulWidget {
  const SchoolApp({super.key, required this.auth, this.preferences});
  final AuthController auth;
  final AppPreferences? preferences;
  @override
  State<SchoolApp> createState() => _SchoolAppState();
}

class _SchoolAppState extends State<SchoolApp> with WidgetsBindingObserver {
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) widget.auth.refreshProfile();
  }

  final navigator = GlobalKey<NavigatorState>();
  late final SchoolPushService push;
  String? pendingNotification;
  AuthStatus? previousStatus;
  Locale? locale;
  ThemeMode mode = ThemeMode.system;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    push = SchoolPushService(
      widget.auth,
      onOpen: (id) {
        pendingNotification = id;
        authChanged();
      },
    );
    push.initialize();
    previousStatus = widget.auth.status;
    widget.auth.addListener(authChanged);
    locale = widget.preferences?.locale;
    mode = widget.preferences?.mode ?? ThemeMode.system;
  }

  void authChanged() {
    final status = widget.auth.status;
    final signedOut =
        previousStatus == AuthStatus.signedIn && status != AuthStatus.signedIn;
    previousStatus = status;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      if (signedOut) {
        pendingNotification = null;
        navigator.currentState?.popUntil((route) => route.isFirst);
      }
      if (status == AuthStatus.signedIn && pendingNotification != null) {
        pendingNotification = null;
        navigator.currentState?.push(
          MaterialPageRoute<void>(
            builder: (_) => NotificationScreen(auth: widget.auth),
          ),
        );
      }
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    widget.auth.removeListener(authChanged);
    push.dispose();
    super.dispose();
  }

  Future<void> persistPreferences(BuildContext context) async {
    try {
      await widget.preferences?.save(locale: locale, mode: mode);
    } catch (_) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(Strings.of(context).text('storage'))),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'INFO-TECH School',
    navigatorKey: navigator,
    debugShowCheckedModeBanner: false,
    locale: locale,
    supportedLocales: const [Locale('en'), Locale('ur')],
    localizationsDelegates: GlobalMaterialLocalizations.delegates,
    theme: AppTheme.build(Brightness.light),
    darkTheme: AppTheme.build(Brightness.dark),
    themeMode: mode,
    home: AnimatedBuilder(
      animation: widget.auth,
      builder: (context, _) {
        final auth = widget.auth;
        final s = Strings.of(context);
        return Scaffold(
          appBar: AppBar(
            titleSpacing: AppSpace.lg,
            title: const SchoolBrand(),
            bottom: const PreferredSize(
              preferredSize: Size.fromHeight(1),
              child: Divider(height: 1),
            ),
            actions: [
              AppToolbar(
                themeMode: mode,
                onLanguage: () {
                  setState(
                    () => locale =
                        Localizations.localeOf(context).languageCode == 'ur'
                        ? const Locale('en')
                        : const Locale('ur'),
                  );
                  persistPreferences(context);
                },
                onTheme: (value) {
                  setState(() => mode = value);
                  persistPreferences(context);
                },
              ),
              if (auth.status == AuthStatus.signedIn)
                IconButton(
                  tooltip: s.text('notifications'),
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute<void>(
                      builder: (_) => NotificationScreen(auth: auth),
                    ),
                  ),
                  icon: const Icon(Icons.notifications_outlined),
                ),
              if (auth.status == AuthStatus.signedIn)
                IconButton(
                  key: const Key('account-menu'),
                  tooltip: s.text('account'),
                  onPressed: () => showAccountSheet(context, auth, push: push),
                  icon: const Icon(Icons.account_circle_outlined),
                ),
              const SizedBox(width: AppSpace.sm),
            ],
          ),
          body: SafeArea(
            child: switch (auth.status) {
              AuthStatus.initializing => Center(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(AppSpace.xl),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const SchoolLogo(size: 96),
                      const SizedBox(height: AppSpace.xl),
                      Text(
                        s.text('loadingWorkspace'),
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const SizedBox(height: AppSpace.xl),
                      const CircularProgressIndicator(),
                    ],
                  ),
                ),
              ),
              AuthStatus.signedOut => LoginScreen(auth: auth),
              AuthStatus.signedIn =>
                auth.isParent
                    ? ParentHomeScreen(auth: auth)
                    : HomeScreen(auth: auth),
              AuthStatus.reconnect => Center(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(AppSpace.xl),
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 460),
                    child: SurfaceCard(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const SchoolLogo(size: 80),
                          const SizedBox(height: AppSpace.xl),
                          StatusBanner(
                            message: s.text(auth.error ?? 'network'),
                            kind: StatusKind.error,
                          ),
                          const SizedBox(height: AppSpace.xl),
                          FilledButton.icon(
                            onPressed: auth.initialize,
                            icon: const Icon(Icons.refresh_rounded),
                            label: Text(s.text('retry')),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            },
          ),
        );
      },
    ),
  );
}
