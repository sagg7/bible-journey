import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/local_progress.dart';

/// Envuelve el contenido de lectura para permitir ajustar el tamaño de texto
/// con el gesto de pellizco (dos dedos), sin interferir con el scroll normal
/// de un dedo. Usa [Listener] en vez de [GestureDetector] porque no reclama
/// el gesto — así el scroll y los taps/long-press de cada versículo siguen
/// funcionando igual.
class PinchZoomListener extends ConsumerStatefulWidget {
  final Widget child;
  const PinchZoomListener({super.key, required this.child});

  @override
  ConsumerState<PinchZoomListener> createState() => _PinchZoomListenerState();
}

class _PinchZoomListenerState extends ConsumerState<PinchZoomListener> {
  final Map<int, Offset> _pointers = {};
  double? _startDistance;
  double _baseScale = 1.0;

  double _currentDistance() {
    final positions = _pointers.values.toList();
    return (positions[0] - positions[1]).distance;
  }

  void _onPointerDown(PointerDownEvent event) {
    _pointers[event.pointer] = event.position;
    if (_pointers.length == 2) {
      _startDistance = _currentDistance();
      _baseScale = ref.read(localProgressProvider).value?.fontScale ?? 1.0;
    }
  }

  void _onPointerMove(PointerMoveEvent event) {
    if (!_pointers.containsKey(event.pointer)) return;
    _pointers[event.pointer] = event.position;
    final start = _startDistance;
    if (_pointers.length == 2 && start != null && start > 0) {
      final factor = _currentDistance() / start;
      final next = (_baseScale * factor).clamp(kMinFontScale, kMaxFontScale);
      ref.read(pinchFontScaleProvider.notifier).state = next;
    }
  }

  void _endPinch() {
    final live = ref.read(pinchFontScaleProvider);
    if (live != null) {
      ref.read(localProgressProvider.notifier).setFontScale(live);
      ref.read(pinchFontScaleProvider.notifier).state = null;
    }
    _startDistance = null;
  }

  void _onPointerUp(PointerUpEvent event) {
    _pointers.remove(event.pointer);
    if (_pointers.length < 2) _endPinch();
  }

  void _onPointerCancel(PointerCancelEvent event) {
    _pointers.remove(event.pointer);
    if (_pointers.length < 2) _endPinch();
  }

  @override
  Widget build(BuildContext context) {
    return Listener(
      onPointerDown: _onPointerDown,
      onPointerMove: _onPointerMove,
      onPointerUp: _onPointerUp,
      onPointerCancel: _onPointerCancel,
      behavior: HitTestBehavior.translucent,
      child: widget.child,
    );
  }
}
