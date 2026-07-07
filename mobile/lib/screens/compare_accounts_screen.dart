import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api.dart';
import '../core/local_progress.dart';
import '../core/strings.dart';
import '../core/theme.dart';
import '../models/models.dart';

class CompareAccountsScreen extends ConsumerStatefulWidget {
  final int groupId;
  const CompareAccountsScreen({super.key, required this.groupId});

  @override
  ConsumerState<CompareAccountsScreen> createState() =>
      _CompareAccountsScreenState();
}

class _CompareAccountsScreenState extends ConsumerState<CompareAccountsScreen>
    with SingleTickerProviderStateMixin {
  TabController? _tabs;

  @override
  void dispose() {
    _tabs?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final groupAsync = ref.watch(compareGroupProvider(widget.groupId));
    final s = AppStrings(ref.watch(localeProvider));
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return groupAsync.when(
      loading: () => const Scaffold(body: Center(child: CircularProgressIndicator())),
      error: (e, _) => Scaffold(
        appBar: AppBar(title: Text(s.t('compararRelatos'))),
        body: Center(child: Text(e.toString())),
      ),
      data: (group) {
        // Init tabs once we have account count
        if (_tabs == null || _tabs!.length != group.accounts.length) {
          _tabs?.dispose();
          _tabs = TabController(length: group.accounts.length, vsync: this);
        }

        return Scaffold(
          appBar: AppBar(
            title: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  s.t('compararRelatos'),
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: BjColors.accentBronze,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                Text(
                  group.titleEs,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
            bottom: group.accounts.isEmpty
                ? null
                : PreferredSize(
                    preferredSize: const Size.fromHeight(44),
                    child: _AccountTabBar(
                      controller: _tabs!,
                      accounts: group.accounts,
                      isDark: isDark,
                    ),
                  ),
          ),
          body: group.accounts.isEmpty
              ? Center(child: Text('Sin relatos disponibles.'))
              : Column(
                  children: [
                    // Disclaimer banner
                    if (group.disclaimerEs != null)
                      _DisclaimerBanner(text: group.disclaimerEs!, isDark: isDark),

                    // Relation level chip
                    if (group.relationLevel != null)
                      _RelationLevelRow(level: group.relationLevel!, isDark: isDark),

                    // Account content tabs
                    Expanded(
                      child: TabBarView(
                        controller: _tabs,
                        children: group.accounts
                            .map((a) => _AccountTab(
                                  account: a,
                                  isDark: isDark,
                                  s: s,
                                ))
                            .toList(),
                      ),
                    ),

                    // Key differences + editorial summary footer
                    if (group.keyDifferencesEs.isNotEmpty ||
                        group.editorialSummaryEs != null)
                      _FooterSection(group: group, isDark: isDark, s: s),
                  ],
                ),
        );
      },
    );
  }
}

// ─── Account tab bar ──────────────────────────────────────────────────────────

class _AccountTabBar extends StatelessWidget {
  final TabController controller;
  final List<CompareAccount> accounts;
  final bool isDark;

  const _AccountTabBar({
    required this.controller,
    required this.accounts,
    required this.isDark,
  });

  @override
  Widget build(BuildContext context) {
    return TabBar(
      controller: controller,
      isScrollable: true,
      tabAlignment: TabAlignment.start,
      labelColor: BjColors.accentBronze,
      unselectedLabelColor: isDark
          ? BjColors.textPrimaryDark.withValues(alpha: 0.5)
          : BjColors.textPrimaryLight.withValues(alpha: 0.5),
      indicatorColor: BjColors.accentBronze,
      dividerColor:
          (isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight)
              .withValues(alpha: 0.1),
      labelStyle:
          const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
      tabs: accounts.map((a) => Tab(text: a.displayReference)).toList(),
    );
  }
}

// ─── Disclaimer banner ────────────────────────────────────────────────────────

class _DisclaimerBanner extends StatelessWidget {
  final String text;
  final bool isDark;

  const _DisclaimerBanner({required this.text, required this.isDark});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      color: BjColors.certaintyDebated.withValues(alpha: 0.1),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.info_outline, size: 14, color: BjColors.certaintyDebated),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                color: BjColors.certaintyDebated,
                fontSize: 12,
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Relation level row ───────────────────────────────────────────────────────

class _RelationLevelRow extends StatelessWidget {
  final String level;
  final bool isDark;

  const _RelationLevelRow({required this.level, required this.isDark});

  @override
  Widget build(BuildContext context) {
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          Text(
            'Nivel de relación: ',
            style: TextStyle(
              color: textColor.withValues(alpha: 0.6),
              fontSize: 12,
            ),
          ),
          CertaintyBadge(label: level, compact: true),
        ],
      ),
    );
  }
}

// ─── Account content tab ──────────────────────────────────────────────────────

class _AccountTab extends ConsumerWidget {
  final CompareAccount account;
  final bool isDark;
  final AppStrings s;

  const _AccountTab({required this.account, required this.isDark, required this.s});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;
    final fontFamily =
        ref.watch(localProgressProvider).value?.fontFamily ?? kDefaultScriptureFont;

    // Load passage text if available
    final passageAsync = account.hasText
        ? ref.watch(passageTextProvider(account.id))
        : null;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Reference header
          Text(
            account.displayReference,
            style: theme.textTheme.titleMedium?.copyWith(
              color: BjColors.accentBronze,
              fontWeight: FontWeight.w700,
            ),
          ),

          if (account.displayLabelEs != null) ...[
            const SizedBox(height: 4),
            Text(
              account.displayLabelEs!,
              style: theme.textTheme.labelSmall?.copyWith(
                color: textColor.withValues(alpha: 0.55),
              ),
            ),
          ],

          const SizedBox(height: 8),
          Row(
            children: [
              CertaintyBadge(label: account.confidence, compact: true),
              const SizedBox(width: 8),
              Text(
                _roleLabel(account.role),
                style: theme.textTheme.labelSmall?.copyWith(
                  color: textColor.withValues(alpha: 0.55),
                ),
              ),
            ],
          ),

          const SizedBox(height: 20),
          Divider(color: textColor.withValues(alpha: 0.08)),
          const SizedBox(height: 16),

          // Passage text or placeholder
          if (!account.hasText)
            _TextUnavailable(isDark: isDark, theme: theme, textColor: textColor)
          else if (passageAsync == null)
            const Center(child: CircularProgressIndicator())
          else
            passageAsync.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (_, _) => _TextUnavailable(
                  isDark: isDark, theme: theme, textColor: textColor),
              data: (pt) => pt.hasText
                  ? _VersesList(
                      verses: pt.verses,
                      translationName: pt.translationName,
                      textColor: textColor,
                      theme: theme,
                      isDark: isDark,
                      fontFamily: fontFamily,
                    )
                  : _TextUnavailable(
                      isDark: isDark, theme: theme, textColor: textColor),
            ),
        ],
      ),
    );
  }

  String _roleLabel(String role) {
    const map = {
      'narrative_anchor': 'Relato principal',
      'parallel_account': 'Relato paralelo',
      'complementary_account': 'Relato complementario',
      'prophetic_context': 'Contexto profético',
      'poetic_literary_mirror': 'Espejo poético',
    };
    return map[role] ?? role;
  }
}

// ─── Verses list ──────────────────────────────────────────────────────────────

class _VersesList extends StatelessWidget {
  final List<Map<String, dynamic>> verses;
  final String? translationName;
  final Color textColor;
  final ThemeData theme;
  final bool isDark;
  final String fontFamily;

  const _VersesList({
    required this.verses,
    this.translationName,
    required this.textColor,
    required this.theme,
    required this.isDark,
    this.fontFamily = kDefaultScriptureFont,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (translationName != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Text(
              translationName!,
              style: theme.textTheme.labelSmall?.copyWith(
                color: BjColors.accentBronze,
                fontWeight: FontWeight.w600,
                letterSpacing: 0.5,
              ),
            ),
          ),
        ...verses.map((v) => Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: RichText(
                text: TextSpan(
                  children: [
                    TextSpan(
                      text: '${v['v']} ',
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: textColor.withValues(alpha: 0.4),
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    TextSpan(
                      text: '${v['t']}',
                      style: scriptureTextStyle(
                              fontSize: 15, height: 1.75, fontFamily: fontFamily)
                          .copyWith(color: textColor),
                    ),
                  ],
                ),
              ),
            )),
      ],
    );
  }
}

class _TextUnavailable extends StatelessWidget {
  final bool isDark;
  final ThemeData theme;
  final Color textColor;

  const _TextUnavailable({
    required this.isDark,
    required this.theme,
    required this.textColor,
  });

  @override
  Widget build(BuildContext context) {
    final borderColor = isDark ? BjColors.surfaceBorder : const Color(0xFFE0DDD8);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        border: Border.all(color: borderColor),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        'Texto de referencia · abre este pasaje en tu Biblia.',
        style: theme.textTheme.bodyMedium?.copyWith(
          color: textColor.withValues(alpha: 0.55),
          fontStyle: FontStyle.italic,
        ),
      ),
    );
  }
}

// ─── Footer: key differences + editorial summary ──────────────────────────────

class _FooterSection extends StatefulWidget {
  final CompareGroupDetail group;
  final bool isDark;
  final AppStrings s;

  const _FooterSection({required this.group, required this.isDark, required this.s});

  @override
  State<_FooterSection> createState() => _FooterSectionState();
}

class _FooterSectionState extends State<_FooterSection> {
  bool _showSummary = false;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = widget.isDark;
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;
    final bg = isDark ? BjColors.surfaceRaised : Colors.white;
    final borderColor = isDark ? BjColors.surfaceBorder : const Color(0xFFE0DDD8);

    return Container(
      decoration: BoxDecoration(
        color: bg,
        border: Border(top: BorderSide(color: borderColor, width: 0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Key differences
          if (widget.group.keyDifferencesEs.isNotEmpty)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  BjSectionLabel(widget.s.t('diferenciasClav')),
                  const SizedBox(height: 10),
                  ...widget.group.keyDifferencesEs.map((d) => Padding(
                        padding: const EdgeInsets.only(bottom: 6),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('• ',
                                style: TextStyle(
                                    color: BjColors.accentBronze,
                                    fontWeight: FontWeight.w700)),
                            Expanded(
                              child: Text(
                                d,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: textColor,
                                  height: 1.4,
                                ),
                              ),
                            ),
                          ],
                        ),
                      )),
                ],
              ),
            ),

          // Editorial summary expandable
          if (widget.group.editorialSummaryEs != null) ...[
            InkWell(
              onTap: () => setState(() => _showSummary = !_showSummary),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                child: Row(
                  children: [
                    Icon(Icons.library_books_outlined,
                        size: 14, color: BjColors.accentPrimary),
                    const SizedBox(width: 6),
                    Text(
                      widget.s.t('resumenEditorial'),
                      style: TextStyle(
                        color: BjColors.accentPrimary,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const Spacer(),
                    Icon(
                      _showSummary ? Icons.expand_less : Icons.expand_more,
                      size: 16,
                      color: BjColors.accentPrimary,
                    ),
                  ],
                ),
              ),
            ),
            if (_showSummary)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
                child: Text(
                  widget.group.editorialSummaryEs!,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: textColor.withValues(alpha: 0.8),
                    height: 1.5,
                  ),
                ),
              ),
          ],
        ],
      ),
    );
  }
}
