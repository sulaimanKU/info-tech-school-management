import '../support/support_screen.dart';

import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/school_brand.dart';
import '../../shared/widgets/polished_components.dart';
import 'auth_controller.dart';
import 'widgets/staff_login_form.dart';
import 'widgets/parent_login_form.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  bool parent = false;
  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final wide = constraints.maxWidth >= AppSpace.wideBreakpoint;
      final s = Strings.of(context);
      final login = Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SegmentedButton<bool>(
            segments: [
              ButtonSegment(
                value: false,
                icon: const Icon(Icons.badge_outlined),
                label: Text(s.text('staffLogin')),
              ),
              ButtonSegment(
                value: true,
                icon: const Icon(Icons.family_restroom_outlined),
                label: Text(s.text('parentLogin')),
              ),
            ],
            selected: {parent},
            onSelectionChanged: widget.auth.busy
                ? null
                : (v) => setState(() {
                    parent = v.first;
                    widget.auth.error = null;
                  }),
          ),
          const SizedBox(height: AppSpace.lg),
          parent
              ? ParentLoginForm(auth: widget.auth)
              : StaffLoginForm(auth: widget.auth, showLogo: wide),
          TextButton.icon(
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute<void>(
                builder: (_) => SupportScreen(auth: widget.auth),
              ),
            ),
            icon: const Icon(Icons.help_outline),
            label: Text(s.text('aboutSupport')),
          ),
        ],
      );
      return SingleChildScrollView(
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        padding: AppSpace.pagePadding(constraints.maxWidth),
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: AppSpace.contentWidth),
            child: Reveal(
              child: wide
                  ? Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Expanded(
                          flex: 6,
                          child: CampusHero(expanded: true),
                        ),
                        const SizedBox(width: AppSpace.xxl),
                        Expanded(flex: 5, child: login),
                      ],
                    )
                  : Column(
                      children: [
                        const CampusHero(),
                        const SizedBox(height: AppSpace.xl),
                        login,
                      ],
                    ),
            ),
          ),
        ),
      );
    },
  );
}
