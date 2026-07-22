import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api.dart';
import '../core/auth.dart';
import '../core/strings.dart';

class AuthScreen extends ConsumerStatefulWidget {
  /// Ruta a la que navegar después de autenticarse (null → home).
  final String? next;
  const AuthScreen({super.key, this.next});

  @override
  ConsumerState<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends ConsumerState<AuthScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;

  // Login
  final _emailCtrl = TextEditingController();
  final _passCtrl = TextEditingController();

  // Registro
  final _nameCtrl = TextEditingController();
  final _regEmailCtrl = TextEditingController();
  final _regPassCtrl = TextEditingController();

  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    _tabs.addListener(() => setState(() => _error = null));
  }

  @override
  void dispose() {
    _tabs.dispose();
    _emailCtrl.dispose();
    _passCtrl.dispose();
    _nameCtrl.dispose();
    _regEmailCtrl.dispose();
    _regPassCtrl.dispose();
    super.dispose();
  }

  Future<void> _onSuccess(String token) async {
    await ref.read(authProvider.notifier).save(token);
    ref.invalidate(meProvider);
    try {
      // Completa la identificacion de RevenueCat antes de abrir el paywall.
      await ref.read(meProvider.future);
    } catch (_) {
      // El login de Bible Journey sigue siendo valido si la tienda no responde.
    }
    if (!mounted) return;
    final next = widget.next;
    if (next != null && next.isNotEmpty) {
      context.go(next);
    } else {
      context.go('/');
    }
  }

  Future<void> _login() async {
    final email = _emailCtrl.text.trim();
    final pass = _passCtrl.text;
    if (email.isEmpty || pass.isEmpty) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ref.read(apiProvider).login(email, pass);
      await _onSuccess(data['token'] as String);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _register() async {
    final name = _nameCtrl.text.trim();
    final email = _regEmailCtrl.text.trim();
    final pass = _regPassCtrl.text;
    if (name.isEmpty || email.isEmpty || pass.isEmpty) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ref.read(apiProvider).register(name, email, pass);
      await _onSuccess(data['token'] as String);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings(ref.watch(localeProvider));
    return Scaffold(
      appBar: AppBar(
        title: Text(s.t('account')),
        bottom: TabBar(
          controller: _tabs,
          tabs: [
            Tab(text: s.t('login')),
            Tab(text: s.t('register')),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: [
          _FormTab(
            fields: [
              _Field(
                label: s.t('email'),
                ctrl: _emailCtrl,
                keyboard: TextInputType.emailAddress,
              ),
              _Field(label: s.t('password'), ctrl: _passCtrl, obscure: true),
            ],
            submitLabel: s.t('login'),
            onSubmit: _login,
            loading: _loading,
            error: _error,
          ),
          _FormTab(
            fields: [
              _Field(label: s.t('name'), ctrl: _nameCtrl),
              _Field(
                label: s.t('email'),
                ctrl: _regEmailCtrl,
                keyboard: TextInputType.emailAddress,
              ),
              _Field(label: s.t('password'), ctrl: _regPassCtrl, obscure: true),
            ],
            submitLabel: s.t('register'),
            onSubmit: _register,
            loading: _loading,
            error: _error,
          ),
        ],
      ),
    );
  }
}

class _Field {
  final String label;
  final TextEditingController ctrl;
  final bool obscure;
  final TextInputType keyboard;
  const _Field({
    required this.label,
    required this.ctrl,
    this.obscure = false,
    this.keyboard = TextInputType.text,
  });
}

class _FormTab extends StatefulWidget {
  final List<_Field> fields;
  final String submitLabel;
  final VoidCallback onSubmit;
  final bool loading;
  final String? error;
  const _FormTab({
    required this.fields,
    required this.submitLabel,
    required this.onSubmit,
    required this.loading,
    this.error,
  });

  @override
  State<_FormTab> createState() => _FormTabState();
}

class _FormTabState extends State<_FormTab> {
  final Map<int, bool> _obscured = {};

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const SizedBox(height: 8),
          for (int i = 0; i < widget.fields.length; i++) ...[
            _buildField(i),
            const SizedBox(height: 16),
          ],
          if (widget.error != null) ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.errorContainer,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                widget.error!,
                style: TextStyle(
                  color: Theme.of(context).colorScheme.onErrorContainer,
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],
          FilledButton(
            onPressed: widget.loading ? null : widget.onSubmit,
            style: FilledButton.styleFrom(
              minimumSize: const Size.fromHeight(48),
            ),
            child: widget.loading
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : Text(widget.submitLabel),
          ),
        ],
      ),
    );
  }

  Widget _buildField(int i) {
    final f = widget.fields[i];
    final isObscured = _obscured[i] ?? f.obscure;
    return TextField(
      controller: f.ctrl,
      keyboardType: f.keyboard,
      obscureText: isObscured,
      textInputAction: i < widget.fields.length - 1
          ? TextInputAction.next
          : TextInputAction.done,
      onSubmitted: i == widget.fields.length - 1
          ? (_) => widget.onSubmit()
          : null,
      decoration: InputDecoration(
        labelText: f.label,
        border: const OutlineInputBorder(),
        suffixIcon: f.obscure
            ? IconButton(
                icon: Icon(
                  isObscured ? Icons.visibility_off : Icons.visibility,
                ),
                onPressed: () => setState(() => _obscured[i] = !isObscured),
              )
            : null,
      ),
    );
  }
}
