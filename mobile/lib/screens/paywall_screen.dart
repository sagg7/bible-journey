import 'dart:async';

import 'package:flutter/foundation.dart'
    show TargetPlatform, defaultTargetPlatform;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show PlatformException;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:purchases_flutter/purchases_flutter.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/auth.dart';
import '../core/theme.dart';
import '../models/models.dart';

class PaywallScreen extends ConsumerStatefulWidget {
  const PaywallScreen({super.key});

  @override
  ConsumerState<PaywallScreen> createState() => _PaywallScreenState();
}

class _PaywallScreenState extends ConsumerState<PaywallScreen> {
  static final Uri _privacyUri = Uri.parse(
    'https://biblejourney-api.codeshore.net/privacy',
  );
  static final Uri _termsUri = Uri.parse(
    'https://biblejourney-api.codeshore.net/terms',
  );

  Future<Offerings>? _offerings;
  bool _purchasing = false;
  bool _restoring = false;
  bool _activationPending = false;
  String? _activationMessage;

  @override
  void initState() {
    super.initState();
    _offerings = _loadOfferings();
  }

  Future<Offerings> _loadOfferings() async {
    if (!isRevenueCatConfigured) {
      throw StateError('RevenueCat is not configured for this build.');
    }

    await _identifyCurrentUser();
    return Purchases.getOfferings().timeout(const Duration(seconds: 10));
  }

  Future<MeProfile> _identifyCurrentUser() async {
    final profile = await ref.read(meProvider.future);
    if (profile == null) {
      throw StateError('A Bible Journey account is required.');
    }

    final expectedId = profile.id.toString();
    if (await Purchases.appUserID != expectedId) {
      await Purchases.logIn(expectedId);
    }
    if (await Purchases.appUserID != expectedId) {
      throw StateError('RevenueCat user identification failed.');
    }

    return profile;
  }

  bool _hasPremiumEntitlement(CustomerInfo info) {
    const acceptedIdentifiers = {'premium', 'Bible Journey Pro'};
    return info.entitlements.active.keys.any(acceptedIdentifiers.contains);
  }

  String get _storeName =>
      defaultTargetPlatform == TargetPlatform.iOS ? 'App Store' : 'Google Play';

  Future<void> _openLegalUrl(Uri uri) async {
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo abrir el enlace.')),
      );
    }
  }

  Future<bool> _waitForBackendPremium() async {
    for (var attempt = 0; attempt < 8; attempt++) {
      final profile = await ref.read(apiProvider).me();
      if (profile.isPremium) {
        ref.invalidate(meProvider);
        ref.invalidate(streamPlanProvider);
        ref.invalidate(crsNodeProvider);
        ref.invalidate(progressSummaryProvider);
        return true;
      }
      await Future<void>.delayed(const Duration(seconds: 2));
    }
    return false;
  }

  Future<void> _finishStoreSync(CustomerInfo info) async {
    if (!_hasPremiumEntitlement(info)) {
      throw StateError('No active RevenueCat entitlement.');
    }

    if (await _waitForBackendPremium()) {
      if (mounted) context.pop();
      return;
    }

    if (mounted) {
      setState(() {
        _activationPending = true;
        _activationMessage =
            'Tu compra está confirmada. Estamos terminando de activar Premium en tu cuenta.';
      });
    }
  }

  Future<bool> _recoverCompletedPurchase(Object error) async {
    try {
      await _identifyCurrentUser();

      final alreadyPurchased =
          error is PlatformException &&
          PurchasesErrorHelper.getErrorCode(error) ==
              PurchasesErrorCode.productAlreadyPurchasedError;

      for (var attempt = 0; attempt < 4; attempt++) {
        final info = alreadyPurchased && attempt == 0
            ? await Purchases.restorePurchases()
            : await Purchases.getCustomerInfo();

        if (_hasPremiumEntitlement(info)) {
          await _finishStoreSync(info);
          return true;
        }

        final profile = await ref.read(apiProvider).me();
        if (profile.isPremium) {
          ref.invalidate(meProvider);
          ref.invalidate(streamPlanProvider);
          ref.invalidate(crsNodeProvider);
          ref.invalidate(progressSummaryProvider);
          if (mounted) context.pop();
          return true;
        }

        if (attempt < 3) {
          await Future<void>.delayed(const Duration(seconds: 2));
        }
      }
    } catch (_) {
      // Preserve the original purchase error when recovery is not possible.
    }

    return false;
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
    if (error is StateError &&
        error.message == 'RevenueCat is not configured for this build.') {
      return 'Las compras no están configuradas en esta compilación.';
    }

    if (error is TimeoutException) {
      return 'La tienda tardó demasiado en responder. Intenta de nuevo en unos segundos.';
    }

    if (error is PlatformException) {
      final code = PurchasesErrorHelper.getErrorCode(error);
      final details = '${error.message ?? ''} ${error.details ?? ''}'
          .toLowerCase();

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
      await _identifyCurrentUser();
      final result = await Purchases.purchase(PurchaseParams.package(package));
      await _finishStoreSync(result.customerInfo);
    } catch (e) {
      final cancelled =
          e is PlatformException &&
          PurchasesErrorHelper.getErrorCode(e) ==
              PurchasesErrorCode.purchaseCancelledError;
      final recovered = !cancelled && await _recoverCompletedPurchase(e);
      if (!cancelled && !recovered && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '$_storeName no devolvió la confirmación. Si aprobaste el pago, usa Restaurar compra; no vuelvas a pagar.',
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _purchasing = false);
    }
  }

  Future<void> _restore() async {
    setState(() {
      _restoring = true;
      _activationMessage = null;
    });
    try {
      await _identifyCurrentUser();
      final info = await Purchases.restorePurchases();
      if (!_hasPremiumEntitlement(info)) {
        if (mounted) {
          setState(() {
            _activationPending = false;
            _activationMessage =
                'No encontramos una suscripción activa en esta cuenta de $_storeName.';
          });
        }
        return;
      }
      await _finishStoreSync(info);
    } catch (e) {
      if (mounted) {
        setState(() {
          _activationMessage =
              'No se pudo restaurar la compra. Verifica la cuenta de $_storeName e intenta de nuevo.';
        });
      }
    } finally {
      if (mounted) setState(() => _restoring = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final loggedIn = ref.watch(authProvider) != null;

    if (!loggedIn) {
      return Scaffold(
        appBar: AppBar(title: const Text('Suscripción')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.lock_outline, size: 44, color: cs.onSurfaceVariant),
                const SizedBox(height: 12),
                Text(
                  'Inicia sesión para suscribirte. Necesitamos tu cuenta para vincular la compra.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: cs.onSurfaceVariant),
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: () => context.push('/auth?next=/suscripcion'),
                  child: const Text('Iniciar sesión'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    if (_activationPending) {
      return Scaffold(
        appBar: AppBar(title: const Text('Suscripción')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.verified_outlined, size: 48, color: cs.primary),
                const SizedBox(height: 16),
                Text(
                  _activationMessage ?? 'Tu compra está confirmada.',
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 20),
                OutlinedButton.icon(
                  onPressed: _restoring ? null : _restore,
                  icon: _restoring
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.restore),
                  label: const Text('Sincronizar de nuevo'),
                ),
              ],
            ),
          ),
        ),
      );
    }

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
                          _offerings = _loadOfferings();
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
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Desbloquea los 540 eventos del plan cronológico completo, todas las traducciones disponibles, audio narrado y Ezra ilimitado.',
                textAlign: TextAlign.center,
                style: TextStyle(color: cs.onSurfaceVariant, height: 1.5),
              ),
              const SizedBox(height: 28),
              for (final package in packages)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: cs.outline, width: 0.5),
                      color: cs.surfaceContainer,
                    ),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 20,
                        vertical: 14,
                      ),
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
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                      ),
                                    ),
                                  )
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
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: (_purchasing || _restoring) ? null : _restore,
                icon: _restoring
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.restore),
                label: const Text('Restaurar compra'),
              ),
              const SizedBox(height: 16),
              Text(
                'El pago se cobrará a tu cuenta de $_storeName al confirmar. '
                'La suscripción se renueva automáticamente por el mismo periodo '
                'y precio, salvo que la canceles antes de la renovación desde '
                'los ajustes de tu cuenta de $_storeName.',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: cs.onSurfaceVariant,
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 8),
              Wrap(
                alignment: WrapAlignment.center,
                spacing: 4,
                children: [
                  TextButton(
                    onPressed: () => _openLegalUrl(_privacyUri),
                    child: const Text('Privacidad'),
                  ),
                  TextButton(
                    onPressed: () => _openLegalUrl(_termsUri),
                    child: const Text('Términos de uso'),
                  ),
                ],
              ),
              if (_activationMessage != null) ...[
                const SizedBox(height: 12),
                Text(
                  _activationMessage!,
                  textAlign: TextAlign.center,
                  style: TextStyle(color: cs.onSurfaceVariant),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}
