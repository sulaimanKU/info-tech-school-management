import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

import '../../core/api_client.dart';
import '../auth/auth_controller.dart';

class SchoolPushService {
  SchoolPushService(this.auth, {required this.onOpen});
  final AuthController auth;
  final void Function(String) onOpen;
  static const configured = bool.fromEnvironment('FCM_ENABLED');
  StreamSubscription<String>? refresh;
  StreamSubscription<RemoteMessage>? opened;
  String? registeredUser, deviceId;
  bool enabled = false, working = false, disposed = false;
  Future<void> initialize() async {
    if (!configured) return;
    try {
      await Firebase.initializeApp(
        options: const FirebaseOptions(
          apiKey: String.fromEnvironment('FIREBASE_API_KEY'),
          appId: String.fromEnvironment('FIREBASE_APP_ID'),
          messagingSenderId: String.fromEnvironment('FIREBASE_SENDER_ID'),
          projectId: String.fromEnvironment('FIREBASE_PROJECT_ID'),
        ),
      );
      await FirebaseMessaging.instance.setAutoInitEnabled(false);
      enabled = await auth.localStore.read('notifications.enabled') == 'true';
      opened = FirebaseMessaging.onMessageOpenedApp.listen(handle);
      final initial = await FirebaseMessaging.instance.getInitialMessage();
      if (initial != null) handle(initial);
      refresh = FirebaseMessaging.instance.onRegistered.listen((token) {
        if (enabled && auth.status == AuthStatus.signedIn) register(token);
      });
      auth.addListener(changed);
      changed();
    } catch (_) {
      enabled = false;
    }
  }

  void handle(RemoteMessage message) {
    final id = message.data['notification_id'];
    if (id is String && RegExp(r'^[0-9a-fA-F-]{36}$').hasMatch(id)) onOpen(id);
  }

  void changed() {
    if (disposed || working) return;
    if (auth.status != AuthStatus.signedIn) {
      registeredUser = null;
      deviceId = null;
      return;
    }
    if (enabled && registeredUser != auth.user?['id']) {
      registerCurrent();
    }
  }

  Future<void> registerCurrent() async {
    if (working ||
        !configured ||
        !enabled ||
        auth.status != AuthStatus.signedIn) {
      return;
    }
    working = true;
    try {
      await FirebaseMessaging.instance.register();
    } catch (_) {
    } finally {
      working = false;
    }
  }

  Future<void> register(String token) async {
    final owner = auth.user?['id'];
    if (disposed || !enabled || owner == null) return;
    try {
      final r = await auth.call(
        'push-devices',
        body: {'token': token, 'platform': 'android'},
      );
      if (auth.user?['id'] == owner) {
        deviceId = r['id'] as String;
        registeredUser = owner as String;
      }
    } on ApiFailure {
      /* In-app notification history remains available; retry from settings. */
    }
  }

  Future<bool> enable() async {
    if (!configured) return false;
    final permissions = await FirebaseMessaging.instance.requestPermission();
    if (permissions.authorizationStatus != AuthorizationStatus.authorized &&
        permissions.authorizationStatus != AuthorizationStatus.provisional) {
      return false;
    }
    enabled = true;
    await auth.localStore.write('notifications.enabled', 'true');
    await FirebaseMessaging.instance.setAutoInitEnabled(true);
    await registerCurrent();
    return enabled;
  }

  Future<void> disable() async {
    if (deviceId != null && auth.status == AuthStatus.signedIn) {
      await auth.call('push-devices/$deviceId', method: 'DELETE');
    }
    if (configured) {
      await FirebaseMessaging.instance.unregister();
      await FirebaseMessaging.instance.setAutoInitEnabled(false);
    }
    enabled = false;
    registeredUser = null;
    deviceId = null;
    await auth.localStore.remove('notifications.enabled');
  }

  void dispose() {
    disposed = true;
    auth.removeListener(changed);
    refresh?.cancel();
    opened?.cancel();
  }
}
