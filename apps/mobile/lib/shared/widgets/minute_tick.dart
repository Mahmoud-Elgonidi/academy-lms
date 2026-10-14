import 'dart:async';

import 'package:flutter/widgets.dart';

/// Builds [builder] again when the phone's clock reaches a new minute, so a
/// countdown on screen stays true. It asks the server nothing: what changes
/// in the data comes from the screen's own reload (`LoadCubit.refreshAt`).
class MinuteTick extends StatefulWidget {
  const MinuteTick({required this.builder, super.key});

  final WidgetBuilder builder;

  @override
  State<MinuteTick> createState() => _MinuteTickState();
}

class _MinuteTickState extends State<MinuteTick> {
  Timer? _next;

  @override
  void initState() {
    super.initState();
    _arm();
  }

  void _arm() {
    final now = DateTime.now();
    final minute = DateTime(now.year, now.month, now.day, now.hour, now.minute + 1);
    _next = Timer(minute.difference(now), () {
      if (!mounted) return;
      setState(() {});
      _arm();
    });
  }

  @override
  void dispose() {
    _next?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.builder(context);
}
