import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../screens/auth_screen.dart';
import '../screens/canonical_chapter_screen.dart';
import '../screens/crs_reader_screen.dart';
import '../screens/compare_accounts_screen.dart';
import '../screens/ezra_v2_screen.dart';
import '../screens/home_screen.dart';
import '../screens/paywall_screen.dart';
import '../screens/route_screen.dart';
import '../screens/routes_list_screen.dart';
import '../screens/translations_screen.dart';
import '../screens/app_shell.dart';
import '../screens/read_screen.dart';
import '../screens/ezra_tab_screen.dart';

final _rootNavigatorKey = GlobalKey<NavigatorState>(debugLabel: 'root');

final appRouter = GoRouter(
  navigatorKey: _rootNavigatorKey,
  initialLocation: '/',
  routes: [
    // ── Auth (outside shell) ──────────────────────────
    GoRoute(
      path: '/auth',
      parentNavigatorKey: _rootNavigatorKey,
      builder: (c, s) => AuthScreen(next: s.uri.queryParameters['next']),
    ),

    // ── Full-screen detail routes (outside shell) ─────
    // Compare Accounts
    GoRoute(
      path: '/compare/:groupId',
      parentNavigatorKey: _rootNavigatorKey,
      builder: (c, s) => CompareAccountsScreen(
        groupId: int.parse(s.pathParameters['groupId']!),
      ),
    ),

    // Ezra V2 — contextual, with node_id / plan_id query params
    GoRoute(
      path: '/ezra/v2',
      parentNavigatorKey: _rootNavigatorKey,
      builder: (c, s) => EzraV2Screen(
        nodeId: int.tryParse(s.uri.queryParameters['node_id'] ?? ''),
        planId: int.tryParse(s.uri.queryParameters['plan_id'] ?? ''),
        nodeTitle: s.uri.queryParameters['title'] ?? '',
      ),
    ),

    // Translations selector
    GoRoute(
      path: '/traducciones',
      parentNavigatorKey: _rootNavigatorKey,
      builder: (c, s) => const TranslationsScreen(),
    ),

    // Paywall / subscription
    GoRoute(
      path: '/suscripcion',
      parentNavigatorKey: _rootNavigatorKey,
      builder: (c, s) => const PaywallScreen(),
    ),

    // CRS reader — plan/:planId/node/:nodeId
    GoRoute(
      path: '/crs/:planId/:nodeId',
      parentNavigatorKey: _rootNavigatorKey,
      builder: (c, s) => CrsReaderScreen(
        planId: int.parse(s.pathParameters['planId']!),
        nodeId: int.parse(s.pathParameters['nodeId']!),
      ),
    ),

    // Canonical chapter reader
    GoRoute(
      path: '/canonical/:osisCode/:chapter',
      parentNavigatorKey: _rootNavigatorKey,
      builder: (c, s) => CanonicalChapterScreen(
        osisCode: s.pathParameters['osisCode']!,
        chapter: int.parse(s.pathParameters['chapter']!),
      ),
    ),

    // ── 4-tab shell ───────────────────────────────────
    StatefulShellRoute.indexedStack(
      parentNavigatorKey: _rootNavigatorKey,
      builder: (context, state, navigationShell) =>
          AppShell(navigationShell: navigationShell),
      branches: [
        // Tab 0: Inicio
        StatefulShellBranch(
          navigatorKey: GlobalKey<NavigatorState>(debugLabel: 'inicio'),
          routes: [
            GoRoute(
              path: '/',
              builder: (c, s) => const HomeScreen(),
            ),
          ],
        ),

        // Tab 1: Leer
        StatefulShellBranch(
          navigatorKey: GlobalKey<NavigatorState>(debugLabel: 'leer'),
          routes: [
            GoRoute(
              path: '/leer',
              builder: (c, s) => const ReadScreen(),
            ),
          ],
        ),

        // Tab 2: Rutas
        StatefulShellBranch(
          navigatorKey: GlobalKey<NavigatorState>(debugLabel: 'rutas'),
          routes: [
            GoRoute(
              path: '/rutas',
              builder: (c, s) => const RoutesListScreen(),
              routes: [
                GoRoute(
                  path: ':slug',
                  builder: (c, s) => RouteScreen(
                    slug: s.pathParameters['slug']!,
                    era: s.extra is Era ? s.extra as Era : null,
                  ),
                ),
              ],
            ),
          ],
        ),

        // Tab 3: Ezra
        StatefulShellBranch(
          navigatorKey: GlobalKey<NavigatorState>(debugLabel: 'ezra'),
          routes: [
            GoRoute(
              path: '/ezra',
              builder: (c, s) => const EzraTabScreen(),
            ),
          ],
        ),
      ],
    ),
  ],
);
