import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/auth.dart';
import '../core/theme.dart';
import 'ezra_v2_screen.dart';

class EzraTabScreen extends ConsumerWidget {
  const EzraTabScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authToken = ref.watch(authProvider);
    if (authToken != null) {
      return EzraV2Screen(nodeTitle: '');
    }
    return const _EzraIntro();
  }
}

class _EzraIntro extends StatefulWidget {
  const _EzraIntro();

  @override
  State<_EzraIntro> createState() => _EzraIntroState();
}

class _EzraIntroState extends State<_EzraIntro> {
  static const _suggestions = [
    '¿Por qué Samuel temía ungir a David?',
    '¿Qué sucede después del relato de Goliat?',
    '¿Quién era Isaí y cuál era su linaje?',
    '¿Por qué la unción de David fue en secreto?',
    'Muéstrame conexiones con los Salmos',
    '¿Qué significa que Dios "mira el corazón"?',
  ];

  static const _capabilities = [
    (Icons.person_outline, 'Personajes y sus historias'),
    (Icons.location_on_outlined, 'Lugares y contexto geográfico'),
    (Icons.access_time_outlined, 'Cronología y datación'),
    (Icons.menu_book_outlined, 'Relatos relacionados y paralelos'),
    (Icons.history_edu_outlined, 'Contexto histórico y cultural'),
    (Icons.link_outlined, 'Conexiones poéticas y proféticas'),
  ];

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Ezra'),
        actions: [
          TextButton(
            onPressed: () => context.push('/auth'),
            child: Text(
              'Iniciar sesión',
              style: TextStyle(color: BjColors.accentPrimary, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 100),
        children: [
          const SizedBox(height: 8),

          // Header
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: BjColors.accentPrimary.withValues(alpha: 0.12),
                  border: Border.all(color: BjColors.accentPrimary.withValues(alpha: 0.25), width: 1),
                ),
                child: Icon(Icons.auto_awesome, size: 26, color: BjColors.accentPrimary),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Ezra',
                      style: TextStyle(
                        color: textColor,
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      'Tu guía para entender el contexto bíblico',
                      style: TextStyle(
                        color: textColor.withValues(alpha: 0.6),
                        fontSize: 13,
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),

          const SizedBox(height: 24),

          // Capabilities
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: cs.surfaceContainer,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: cs.outline, width: 0.5),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Puedes preguntarle sobre:',
                  style: TextStyle(
                    color: textColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 12),
                ..._capabilities.map((cap) => Padding(
                  padding: const EdgeInsets.only(bottom: 9),
                  child: Row(
                    children: [
                      Icon(cap.$1, size: 16, color: BjColors.accentPrimary.withValues(alpha: 0.8)),
                      const SizedBox(width: 10),
                      Text(
                        cap.$2,
                        style: TextStyle(
                          color: textColor.withValues(alpha: 0.75),
                          fontSize: 13,
                          height: 1.3,
                        ),
                      ),
                    ],
                  ),
                )),
              ],
            ),
          ),

          const SizedBox(height: 20),

          // Suggested questions
          Text(
            'Preguntas frecuentes',
            style: TextStyle(
              color: textColor.withValues(alpha: 0.45),
              fontSize: 11,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.0,
            ),
          ),
          const SizedBox(height: 10),

          ..._suggestions.map((q) => Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: GestureDetector(
              onTap: () => context.push('/auth'),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(
                  color: cs.surfaceContainer,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: cs.outline, width: 0.5),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        q,
                        style: TextStyle(
                          color: textColor.withValues(alpha: 0.8),
                          fontSize: 13,
                          height: 1.35,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Icon(
                      Icons.arrow_forward,
                      size: 14,
                      color: BjColors.accentPrimary.withValues(alpha: 0.6),
                    ),
                  ],
                ),
              ),
            ),
          )),

          const SizedBox(height: 24),

          // Structured response preview
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: BjColors.accentPrimary.withValues(alpha: 0.05),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: BjColors.accentPrimary.withValues(alpha: 0.15), width: 0.5),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.info_outline, size: 14, color: BjColors.accentPrimary),
                    const SizedBox(width: 6),
                    Text(
                      'Respuestas estructuradas',
                      style: TextStyle(
                        color: BjColors.accentPrimary,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  'Cada respuesta incluye: base bíblica, contexto histórico, inferencia editorial y nivel de certeza. No opinión, sino evidencia.',
                  style: TextStyle(
                    color: textColor.withValues(alpha: 0.65),
                    fontSize: 12,
                    height: 1.5,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 24),

          // CTA
          FilledButton.icon(
            onPressed: () => context.push('/auth'),
            icon: const Icon(Icons.auto_awesome, size: 18),
            label: const Text('Iniciar sesión para conversar con Ezra'),
          ),
          const SizedBox(height: 10),
          Center(
            child: Text(
              'Las conversaciones se guardan en tu cuenta.',
              style: TextStyle(
                color: textColor.withValues(alpha: 0.4),
                fontSize: 11,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
