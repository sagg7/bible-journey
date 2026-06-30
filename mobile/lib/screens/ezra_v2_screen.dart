import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../core/api.dart';
import '../core/strings.dart';
import '../core/theme.dart';
import '../models/models.dart';

// ─── Message model ────────────────────────────────────────────────────────────

class _V2Message {
  final String text;
  final bool fromUser;
  final EzraStructuredResponse? response;
  const _V2Message(this.text, this.fromUser, [this.response]);
}

// ─── Screen ───────────────────────────────────────────────────────────────────

class EzraV2Screen extends ConsumerStatefulWidget {
  final int? nodeId;
  final int? planId;
  final String nodeTitle;

  const EzraV2Screen({
    super.key,
    this.nodeId,
    this.planId,
    this.nodeTitle = '',
  });

  @override
  ConsumerState<EzraV2Screen> createState() => _EzraV2ScreenState();
}

class _EzraV2ScreenState extends ConsumerState<EzraV2Screen> {
  final _controller = TextEditingController();
  final _scroll = ScrollController();
  final List<_V2Message> _messages = [];
  bool _sending = false;

  List<String> get _suggestions => widget.nodeTitle.isNotEmpty
      ? [
          '¿Por qué este pasaje está en este punto de la historia?',
          '¿Qué contexto histórico necesito conocer?',
          '¿Cómo se conecta esto con el resto de la Biblia?',
        ]
      : [
          '¿Qué es la cronología bíblica y por qué importa?',
          '¿Cómo organizas los libros históricos?',
          '¿Qué tan confiables son las fechas de los eventos bíblicos?',
        ];

  @override
  void dispose() {
    _controller.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) {
        _scroll.animateTo(
          _scroll.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _send([String? preset]) async {
    final q = (preset ?? _controller.text).trim();
    if (q.isEmpty || _sending) return;

    setState(() {
      _messages.add(_V2Message(q, true));
      _sending = true;
      _controller.clear();
    });
    _scrollToBottom();

    try {
      final response = await ref.read(apiProvider).askEzraV2(
            q,
            nodeId: widget.nodeId,
            planId: widget.planId,
          );
      setState(() => _messages.add(_V2Message(response.directAnswer, false, response)));
    } on ApiException catch (e) {
      final s = AppStrings(ref.read(localeProvider));
      final msg = e.status == 503
          ? s.t('ezraUnavailable')
          : (e.status == 401 ? 'Inicia sesión para hablar con Ezra.' : e.message);
      setState(() => _messages.add(_V2Message(msg, false)));
    } finally {
      if (mounted) setState(() => _sending = false);
      _scrollToBottom();
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings(ref.watch(localeProvider));
    final title = widget.nodeTitle.isNotEmpty ? 'Ezra · ${widget.nodeTitle}' : 'Ezra';

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: Column(
        children: [
          Expanded(
            child: _messages.isEmpty
                ? _EzraV2Welcome(
                    intro: widget.nodeId != null ? s.t('ezraIntro') : s.t('ezraIntroGlobal'),
                    suggestions: _suggestions,
                    onSuggestion: _send,
                  )
                : ListView.builder(
                    controller: _scroll,
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                    itemCount: _messages.length,
                    itemBuilder: (_, i) {
                      final m = _messages[i];
                      if (m.fromUser) return _UserBubble(text: m.text);
                      if (m.response != null) {
                        return _StructuredResponseCard(response: m.response!);
                      }
                      return _EzraBubble(text: m.text);
                    },
                  ),
          ),
          if (_sending)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: SizedBox(
                height: 18,
                width: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
          _InputBar(
            controller: _controller,
            sending: _sending,
            hint: s.t('ezraHint'),
            onSend: _send,
          ),
        ],
      ),
    );
  }
}

// ─── Welcome ──────────────────────────────────────────────────────────────────

class _EzraV2Welcome extends StatelessWidget {
  final String intro;
  final List<String> suggestions;
  final void Function(String) onSuggestion;

  const _EzraV2Welcome({
    required this.intro,
    required this.suggestions,
    required this.onSuggestion,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: BjColors.accentPrimary.withValues(alpha: 0.12),
              ),
              child: Icon(Icons.auto_awesome, size: 22, color: BjColors.accentPrimary),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                intro,
                style: TextStyle(color: cs.onSurface, fontSize: 14, height: 1.5),
              ),
            ),
          ],
        ),
        const SizedBox(height: 24),
        BjSectionLabel('Preguntas sugeridas'),
        const SizedBox(height: 12),
        ...suggestions.map(
          (q) => Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: InkWell(
              onTap: () => onSuggestion(q),
              borderRadius: BorderRadius.circular(10),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(
                  color: cs.surfaceContainer,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: cs.outline, width: 0.5),
                ),
                child: Text(
                  q,
                  style: TextStyle(color: cs.onSurface, fontSize: 13, height: 1.4),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

// ─── Message bubbles ──────────────────────────────────────────────────────────

class _UserBubble extends StatelessWidget {
  final String text;
  const _UserBubble({required this.text});

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerRight,
      child: Container(
        margin: const EdgeInsets.only(bottom: 12, left: 48),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: BjColors.accentPrimary,
          borderRadius: const BorderRadius.only(
            topLeft: Radius.circular(14),
            topRight: Radius.circular(4),
            bottomLeft: Radius.circular(14),
            bottomRight: Radius.circular(14),
          ),
        ),
        child: Text(
          text,
          style: const TextStyle(color: Colors.white, fontSize: 14, height: 1.4),
        ),
      ),
    );
  }
}

class _EzraBubble extends StatelessWidget {
  final String text;
  const _EzraBubble({required this.text});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 12, right: 48),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: cs.surfaceContainer,
          borderRadius: const BorderRadius.only(
            topLeft: Radius.circular(4),
            topRight: Radius.circular(14),
            bottomLeft: Radius.circular(14),
            bottomRight: Radius.circular(14),
          ),
          border: Border.all(color: cs.outline, width: 0.5),
        ),
        child: Text(text, style: TextStyle(color: cs.onSurface, fontSize: 14, height: 1.5)),
      ),
    );
  }
}

// ─── Structured Response Card ─────────────────────────────────────────────────

class _StructuredResponseCard extends StatefulWidget {
  final EzraStructuredResponse response;
  const _StructuredResponseCard({required this.response});

  @override
  State<_StructuredResponseCard> createState() => _StructuredResponseCardState();
}

class _StructuredResponseCardState extends State<_StructuredResponseCard> {
  bool _showSources = false;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final r = widget.response;

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        color: cs.surfaceContainer,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: BjColors.accentPrimary.withValues(alpha: 0.25),
          width: 0.5,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Ezra header
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 0),
            child: Row(
              children: [
                Icon(Icons.auto_awesome, size: 14, color: BjColors.accentPrimary),
                const SizedBox(width: 6),
                Text(
                  'Ezra',
                  style: TextStyle(
                    color: BjColors.accentPrimary,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 8),

          // Direct answer
          _Section(
            label: 'RESPUESTA DIRECTA',
            content: r.directAnswer,
            accent: BjColors.textPrimaryDark,
            style: _Style.primary,
          ),

          // Biblical basis
          if (r.biblicalBasis != null) ...[
            _Section(
              label: 'BASE BÍBLICA',
              content: r.biblicalBasis!['reference'] as String? ?? '',
              accent: BjColors.accentBronzeLight,
              style: _Style.scripture,
              icon: Icons.menu_book_outlined,
            ),
            if ((r.biblicalBasis!['quote'] as String?)?.isNotEmpty == true)
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 0, 14, 8),
                child: Text(
                  '"${r.biblicalBasis!['quote']}"',
                  style: GoogleFonts.lora(
                    color: cs.onSurface.withValues(alpha: 0.85),
                    fontSize: 13,
                    fontStyle: FontStyle.italic,
                    height: 1.6,
                  ),
                ),
              ),
          ],

          // Historical context
          if (r.historicalContext != null)
            _Section(
              label: 'CONTEXTO HISTÓRICO',
              content: r.historicalContext!,
              accent: BjColors.certaintyHigh,
              style: _Style.context,
              icon: Icons.history_edu_outlined,
            ),

          // Editorial inference
          if (r.editorialNote != null)
            _Section(
              label: 'INFERENCIA EDITORIAL',
              content: r.editorialNote!,
              accent: BjColors.certaintyProbable,
              style: _Style.editorial,
              icon: Icons.edit_note,
            ),

          // Certainty level
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 8, 14, 0),
            child: Row(
              children: [
                Text(
                  'Nivel de certeza · ',
                  style: TextStyle(
                    color: cs.onSurfaceVariant,
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                CertaintyBadge(label: r.certaintyLevel),
              ],
            ),
          ),

          if (r.certaintyExplanation != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 4, 14, 0),
              child: Text(
                r.certaintyExplanation!,
                style: TextStyle(
                  color: cs.onSurfaceVariant,
                  fontSize: 11,
                  height: 1.4,
                ),
              ),
            ),

          // Sources
          if (r.sources.isNotEmpty) ...[
            const SizedBox(height: 8),
            InkWell(
              onTap: () => setState(() => _showSources = !_showSources),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(14, 4, 14, 4),
                child: Row(
                  children: [
                    Icon(Icons.link, size: 13, color: cs.onSurfaceVariant),
                    const SizedBox(width: 5),
                    Text(
                      'Fuentes · ${r.sources.length}',
                      style: TextStyle(
                        color: cs.onSurfaceVariant,
                        fontSize: 11,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(width: 4),
                    Icon(
                      _showSources ? Icons.expand_less : Icons.expand_more,
                      size: 13,
                      color: cs.onSurfaceVariant,
                    ),
                  ],
                ),
              ),
            ),
            if (_showSources)
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 0, 14, 8),
                child: Wrap(
                  spacing: 6,
                  runSpacing: 4,
                  children: r.sources
                      .map((f) => Chip(
                            label: Text(f, style: const TextStyle(fontSize: 11)),
                            materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                            visualDensity: VisualDensity.compact,
                          ))
                      .toList(),
                ),
              ),
          ],

          // Reflection question
          if (r.reflectionQuestion != null) ...[
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 10, 14, 0),
              child: Divider(height: 1, color: cs.outline.withValues(alpha: 0.3)),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 10, 14, 0),
              child: Text(
                r.reflectionQuestion!,
                style: TextStyle(
                  color: BjColors.accentPrimary.withValues(alpha: 0.85),
                  fontSize: 13,
                  fontStyle: FontStyle.italic,
                  height: 1.5,
                ),
              ),
            ),
          ],

          const SizedBox(height: 14),
        ],
      ),
    );
  }
}

enum _Style { primary, scripture, context, editorial }

class _Section extends StatelessWidget {
  final String label;
  final String content;
  final Color accent;
  final _Style style;
  final IconData? icon;

  const _Section({
    required this.label,
    required this.content,
    required this.accent,
    required this.style,
    this.icon,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(14, 8, 14, 3),
          child: Row(
            children: [
              if (icon != null) ...[
                Icon(icon, size: 12, color: accent),
                const SizedBox(width: 4),
              ],
              Text(
                label,
                style: TextStyle(
                  color: accent,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.8,
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(14, 0, 14, 6),
          child: style == _Style.scripture
              ? Text(
                  content,
                  style: GoogleFonts.lora(
                    color: cs.onSurface,
                    fontSize: 13,
                    height: 1.6,
                  ),
                )
              : Text(
                  content,
                  style: TextStyle(color: cs.onSurface, fontSize: 13, height: 1.5),
                ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14),
          child: Divider(height: 1, color: cs.outline.withValues(alpha: 0.25)),
        ),
      ],
    );
  }
}

// ─── Input bar ────────────────────────────────────────────────────────────────

class _InputBar extends StatelessWidget {
  final TextEditingController controller;
  final bool sending;
  final String hint;
  final void Function([String?]) onSend;

  const _InputBar({
    required this.controller,
    required this.sending,
    required this.hint,
    required this.onSend,
  });

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
        decoration: BoxDecoration(
          color: Theme.of(context).scaffoldBackgroundColor,
          border: Border(
            top: BorderSide(
              color: Theme.of(context).colorScheme.outline.withValues(alpha: 0.3),
            ),
          ),
        ),
        child: Row(
          children: [
            Expanded(
              child: TextField(
                controller: controller,
                minLines: 1,
                maxLines: 4,
                decoration: InputDecoration(
                  hintText: hint,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(22)),
                  contentPadding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                ),
                onSubmitted: (_) => onSend(),
              ),
            ),
            const SizedBox(width: 8),
            IconButton.filled(
              onPressed: sending ? null : () => onSend(),
              style: IconButton.styleFrom(
                backgroundColor: BjColors.accentPrimary,
                foregroundColor: Colors.white,
              ),
              icon: sending
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                          color: Colors.white, strokeWidth: 2),
                    )
                  : const Icon(Icons.send_rounded),
            ),
          ],
        ),
      ),
    );
  }
}
