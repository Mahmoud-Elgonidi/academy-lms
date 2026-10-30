import 'package:flutter/material.dart';

import '../../../../../shared/motion/motion.dart';

/// Where the floating self view rests.
enum SelfCorner { topLeft, topRight, bottomLeft, bottomRight }

/// My own picture, small over the stage: dragged anywhere with a finger, it
/// settles in the nearest corner when let go (at first the top corner on the
/// reading side's end). Fills the stage it is put in; only the picture takes
/// touches.
class FloatingSelf extends StatefulWidget {
  const FloatingSelf({required this.child, required this.size, this.insets = const EdgeInsets.all(12), super.key});

  final Widget child;
  final Size size;

  /// Kept clear around the edges (the bars over the picture in landscape).
  final EdgeInsets insets;

  @override
  State<FloatingSelf> createState() => _FloatingSelfState();
}

class _FloatingSelfState extends State<FloatingSelf> {
  SelfCorner? _corner;

  /// Where it is while a finger holds it.
  Offset? _held;

  Offset _at(SelfCorner corner, Size box) {
    final i = widget.insets;
    final s = widget.size;
    final left = i.left;
    final right = box.width - s.width - i.right;
    final top = i.top;
    final bottom = box.height - s.height - i.bottom;
    return switch (corner) {
      SelfCorner.topLeft => Offset(left, top),
      SelfCorner.topRight => Offset(right, top),
      SelfCorner.bottomLeft => Offset(left, bottom),
      SelfCorner.bottomRight => Offset(right, bottom),
    };
  }

  Offset _clamp(Offset o, Size box) => Offset(
    o.dx.clamp(0, (box.width - widget.size.width).clamp(0, double.infinity)).toDouble(),
    o.dy.clamp(0, (box.height - widget.size.height).clamp(0, double.infinity)).toDouble(),
  );

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final box = constraints.biggest;
      final rtl = Directionality.of(context) == TextDirection.rtl;
      final corner = _corner ?? (rtl ? SelfCorner.topLeft : SelfCorner.topRight);
      final pos = _clamp(_held ?? _at(corner, box), box);
      return Stack(
        children: [
          AnimatedPositioned(
            duration: _held != null || context.calm ? Duration.zero : const Duration(milliseconds: 240),
            curve: Curves.easeOutCubic,
            left: pos.dx,
            top: pos.dy,
            width: widget.size.width,
            height: widget.size.height,
            child: GestureDetector(
              key: const Key('self-view'),
              onPanStart: (_) => setState(() => _held = pos),
              onPanUpdate: (d) => setState(() => _held = _clamp((_held ?? pos) + d.delta, box)),
              onPanEnd: (_) {
                final at = _held ?? pos;
                final centre = at + Offset(widget.size.width / 2, widget.size.height / 2);
                final leftHalf = centre.dx < box.width / 2;
                final topHalf = centre.dy < box.height / 2;
                setState(() {
                  _held = null;
                  _corner = topHalf
                      ? (leftHalf ? SelfCorner.topLeft : SelfCorner.topRight)
                      : (leftHalf ? SelfCorner.bottomLeft : SelfCorner.bottomRight);
                });
              },
              child: DecoratedBox(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: const [BoxShadow(color: Color(0x66000000), blurRadius: 16, offset: Offset(0, 6))],
                ),
                child: widget.child,
              ),
            ),
          ),
        ],
      );
    },
  );
}
