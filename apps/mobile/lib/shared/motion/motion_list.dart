import 'package:flutter/material.dart';

import 'motion.dart';

/// A list whose rows open into place when they arrive and close when they
/// leave, matched by [idOf], so the rest never jumps. Rows that only moved
/// are simply shown in their new order.
class MotionList<T> extends StatefulWidget {
  const MotionList({
    required this.items,
    required this.idOf,
    required this.itemBuilder,
    this.gap = 12,
    this.padding,
    this.physics,
    this.shrinkWrap = false,
    super.key,
  });

  final List<T> items;
  final Object Function(T item) idOf;
  final Widget Function(BuildContext context, T item) itemBuilder;
  final double gap;
  final EdgeInsetsGeometry? padding;
  final ScrollPhysics? physics;
  final bool shrinkWrap;

  @override
  State<MotionList<T>> createState() => _MotionListState<T>();
}

class _MotionListState<T> extends State<MotionList<T>> {
  final _list = GlobalKey<AnimatedListState>();
  late List<T> _shown = List.of(widget.items);

  static const _time = Duration(milliseconds: 320);

  @override
  void didUpdateWidget(MotionList<T> old) {
    super.didUpdateWidget(old);
    final state = _list.currentState;
    final next = widget.items;
    final nextIds = {for (final i in next) widget.idOf(i)};
    final still = context.calm;
    // Leaving rows close where they are, last first so the indexes hold.
    for (var i = _shown.length - 1; i >= 0; i--) {
      final item = _shown[i];
      if (nextIds.contains(widget.idOf(item))) continue;
      _shown.removeAt(i);
      state?.removeItem(i, (context, animation) => _row(context, item, animation), duration: still ? Duration.zero : _time);
    }
    // New rows open at their place.
    final shownIds = {for (final i in _shown) widget.idOf(i)};
    for (var i = 0; i < next.length; i++) {
      if (shownIds.contains(widget.idOf(next[i]))) continue;
      final at = i.clamp(0, _shown.length);
      _shown.insert(at, next[i]);
      state?.insertItem(at, duration: still ? Duration.zero : _time);
    }
    // The rest take their new order and content.
    _shown = List.of(next);
  }

  Widget _row(BuildContext context, T item, Animation<double> animation) {
    final curved = CurvedAnimation(parent: animation, curve: Curves.easeOutCubic, reverseCurve: Curves.easeInCubic);
    return SizeTransition(
      sizeFactor: curved,
      child: FadeTransition(
        opacity: curved,
        child: Padding(
          padding: EdgeInsets.only(bottom: widget.gap),
          child: widget.itemBuilder(context, item),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => AnimatedList(
    key: _list,
    initialItemCount: _shown.length,
    padding: widget.padding,
    physics: widget.physics,
    shrinkWrap: widget.shrinkWrap,
    itemBuilder: (context, i, animation) => _row(context, _shown[i], animation),
  );
}
