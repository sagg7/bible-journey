import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:purchases_flutter/purchases_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';

const _tokenKey = 'bible_journey_token';

class AuthNotifier extends StateNotifier<String?> {
  AuthNotifier() : super(null) {
    _load();
  }

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    state = prefs.getString(_tokenKey);
  }

  Future<void> save(String token) async {
    state = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);
  }

  Future<void> logout() async {
    state = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    if (!kIsWeb) {
      try {
        await Purchases.logOut();
      } catch (_) {}
    }
  }
}

/// Token de Sanctum persistido en SharedPreferences (null = invitado).
final authProvider = StateNotifierProvider<AuthNotifier, String?>(
  (ref) => AuthNotifier(),
);
