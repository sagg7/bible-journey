import 'dart:async';

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
    _offerings = Purchases.getOfferings()
        .timeout(const Duration(seconds: 10));
  }

  String _periodLabel(PackageType type) {
    switch (type) {
      case PackageType.annual:
        return '/año';
      case PackageType.sixMonth:
        return '/6 meses';
      case PackageType.threeMonth:
        return '/3 meses';
      case PackageType.twoMonth:
        return '/2 meses';
      case PackageType.monthly:
        return '/mes';
      case PackageType.weekly:
        return '/semana';
      default:
        return '';
    }
  }

  String _offeringsErrorMessage(Object? error, {required bool hasNoPackages}) {
    if (error is TimeoutException) {
      return 'La tienda tardó demasiado en responder. Intenta de nuevo en unos segundos.';
    }

    if (error is PlatformException) {
      final code = PurchasesErrorHelper.getErrorCode(error);
      final details = '${error.message ?? ''} ${error.details ?? ''}'.toLowerCase();

      if (code == PurchasesErrorCode.purchaseNotAllowedError ||
          details.contains('billing_unavailable') ||
          details.contains('billing is not available')) {
        return 'Las compras no están disponibles en este dispositivo. Para probar planes en Android, instala la app desde Google Play con una cuenta tester.';
      }
    }

    if (hasNoPackages) {
      return 'No hay planes disponibles en este momento.';
    }

    return 'No se pudieron cargar los planes de suscripción. Intenta de nuevo.';
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
            final message = _offeringsErrorMessage(
              snapshot.error,
              hasNoPackages: packages.isEmpty,
            );

            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.cloud_off, size: 44, color: cs.onSurfaceVariant),
                    const SizedBox(height: 12),
                    Text(
                      message,
                      textAlign: TextAlign.center,
                      style: TextStyle(color: cs.onSurfaceVariant),
                    ),
                    const SizedBox(height: 16),
                    OutlinedButton(
                      onPressed: () {
                        setState(() {
                          _offerings = Purchases.getOfferings()
                              .timeout(const Duration(seconds: 10));
                        });
                      },
                      child: const Text('Reintentar'),
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
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            package.storeProduct.title,
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            '${package.storeProduct.priceString}${_periodLabel(package.packageType)}',
                          ),
                          const SizedBox(height: 12),
                          SizedBox(
                            width: double.infinity,
                            child: _purchasing
                                ? const Center(
                                    child: SizedBox(
                                        width: 20,
                                        height: 20,
                                        child: CircularProgressIndicator(strokeWidth: 2)))
                                : FilledButton(
                                    onPressed: () => _purchase(package),
                                    child: const Text('Suscribirse'),
                                  ),
                          ),
                        ],
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
