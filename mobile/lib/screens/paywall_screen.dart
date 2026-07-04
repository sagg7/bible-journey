import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show PlatformException;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:purchases_flutter/purchases_flutter.dart';

import '../core/api.dart';
import '../core/theme.dart';

class PaywallScreen extends ConsumerStatefulWidget {
  const PaywallScreen({super.key});

  @override
  ConsumerState<PaywallScreen> createState() => _PaywallScreenState();
}

class _PaywallScreenState extends ConsumerState<PaywallScreen> {
  Future<Offerings>? _offerings;
  bool _purchasing = false;

  @override
  void initState() {
    super.initState();
    _offerings = Purchases.getOfferings();
  }

  Future<void> _purchase(Package package) async {
    setState(() => _purchasing = true);
    try {
      await Purchases.purchase(PurchaseParams.package(package));
      ref.invalidate(meProvider);
      if (mounted) context.pop();
    } catch (e) {
      final cancelled = e is PlatformException &&
          PurchasesErrorHelper.getErrorCode(e) == PurchasesErrorCode.purchaseCancelledError;
      if (!cancelled && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No se pudo completar la compra. Intenta de nuevo.')),
        );
      }
    } finally {
      if (mounted) setState(() => _purchasing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('Suscripción')),
      body: FutureBuilder<Offerings>(
        future: _offerings,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }

          final packages = snapshot.data?.current?.availablePackages ?? [];

          if (snapshot.hasError || packages.isEmpty) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.cloud_off, size: 44, color: cs.onSurfaceVariant),
                    const SizedBox(height: 12),
                    Text(
                      'No se pudieron cargar los planes de suscripción.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: cs.onSurfaceVariant),
                    ),
                  ],
                ),
              ),
            );
          }

          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 24, 20, 40),
            children: [
              Icon(Icons.auto_stories, size: 48, color: BjColors.accentPrimary),
              const SizedBox(height: 16),
              Text(
                'Sigue la historia completa',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              Text(
                'Ya viviste "Los patriarcas" y "David y Salomón". Con la suscripción desbloqueas los 540 eventos del plan cronológico completo, Ezra ilimitado y el contenido de Espíritu de Profecía.',
                textAlign: TextAlign.center,
                style: TextStyle(color: cs.onSurfaceVariant, height: 1.5),
              ),
              const SizedBox(height: 28),
              for (final package in packages)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: cs.outline, width: 0.5),
                      color: cs.surfaceContainer,
                    ),
                    child: ListTile(
                      contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                      title: Text(
                        package.storeProduct.title,
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      subtitle: Text(package.storeProduct.priceString),
                      trailing: _purchasing
                          ? const SizedBox(
                              width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                          : FilledButton(
                              onPressed: () => _purchase(package),
                              child: const Text('Suscribirse'),
                            ),
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
