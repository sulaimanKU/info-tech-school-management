import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../design/semantic_tokens.dart';
import '../../l10n/strings.dart';
import '../auth/auth_controller.dart';
import 'widgets/account_sheet.dart';
import 'widgets/module_card.dart';
import 'widgets/welcome_panel.dart';
import 'widgets/dashboard_overview.dart';
import 'widgets/quick_actions.dart';
import '../../shared/widgets/surface_card.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final scroll = ScrollController();
  final modules = GlobalKey();
  final dashboard = GlobalKey<DashboardOverviewState>();
  int destination = 0;
  @override
  void dispose() {
    scroll.dispose();
    super.dispose();
  }

  void navigate(int value) {
    setState(() => destination = value);
    if (value == 0) {
      scroll.animateTo(
        0,
        duration: AppMotion.duration(context),
        curve: Curves.easeOutCubic,
      );
    } else if (modules.currentContext != null) {
      Scrollable.ensureVisible(
        modules.currentContext!,
        duration: AppMotion.duration(context),
        curve: Curves.easeOutCubic,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return LayoutBuilder(
      builder: (context, c) {
        final wide = c.maxWidth >= AppSpace.wideBreakpoint;
        final body = RefreshIndicator(
          onRefresh: () async {
            await widget.auth.refreshProfile();
            await dashboard.currentState?.load();
          },
          child: SingleChildScrollView(
            controller: scroll,
            physics: const AlwaysScrollableScrollPhysics(),
            padding: AppSpace.pagePadding(c.maxWidth),
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(
                  maxWidth: AppSpace.contentWidth,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    WelcomePanel(
                      user: widget.auth.user!,
                      onAccount: () => showAccountSheet(context, widget.auth),
                    ),
                    const SizedBox(height: AppSpace.xl),
                    DashboardOverview(
                      key: dashboard,
                      auth: widget.auth,
                      quickActions: QuickActions(
                        auth: widget.auth,
                        onReturn: () => dashboard.currentState?.load(),
                      ),
                    ),
                    const SizedBox(height: AppSpace.xxl),
                    Text(
                      key: modules,
                      s.text('schoolModules'),
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: AppSpace.sm),
                    Text(
                      s.text('moduleIntro'),
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: AppSpace.lg),
                    FutureModuleGrid(auth: widget.auth),
                    const SizedBox(height: AppSpace.xl),
                    if (widget.auth.error != null)
                      StatusBanner(
                        message: s.text(widget.auth.error!),
                        kind: StatusKind.error,
                      ),
                    Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: OutlinedButton.icon(
                        key: const Key('home-logout'),
                        onPressed: widget.auth.busy
                            ? null
                            : () => confirmLogout(context, widget.auth),
                        icon: const Icon(Icons.logout_rounded),
                        label: Text(
                          s.text(widget.auth.busy ? 'signingOut' : 'logout'),
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpace.lg),
                  ],
                ),
              ),
            ),
          ),
        );
        if (wide) {
          return Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              NavigationRail(
                selectedIndex: destination,
                onDestinationSelected: navigate,
                labelType: NavigationRailLabelType.all,
                destinations: [
                  NavigationRailDestination(
                    icon: const Icon(Icons.dashboard_outlined),
                    selectedIcon: const Icon(Icons.dashboard_rounded),
                    label: Text(s.text('overview')),
                  ),
                  NavigationRailDestination(
                    icon: const Icon(Icons.grid_view_outlined),
                    selectedIcon: const Icon(Icons.grid_view_rounded),
                    label: Text(s.text('schoolModules')),
                  ),
                ],
              ),
              const VerticalDivider(width: 1),
              Expanded(child: body),
            ],
          );
        }
        return Column(
          children: [
            Expanded(child: body),
            NavigationBar(
              selectedIndex: destination,
              onDestinationSelected: navigate,
              destinations: [
                NavigationDestination(
                  icon: const Icon(Icons.dashboard_outlined),
                  selectedIcon: const Icon(Icons.dashboard_rounded),
                  label: s.text('overview'),
                ),
                NavigationDestination(
                  icon: const Icon(Icons.grid_view_outlined),
                  selectedIcon: const Icon(Icons.grid_view_rounded),
                  label: s.text('schoolModules'),
                ),
              ],
            ),
          ],
        );
      },
    );
  }
}
