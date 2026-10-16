import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../schedule/domain/lesson.dart';
import '../../data/mushaf_repository.dart';

/// Opens a lesson's shared mushaf over the screen.
Future<void> openMushaf(BuildContext context, String lessonId) => context.push('/lessons/$lessonId/mushaf');

/// Whether a lesson's card is worth asking about its mushaf: not cancelled,
/// and the next one or on now. A list of past lessons asks nothing (their
/// marks are in the notebook; a lesson's own sheet still asks).
bool mushafWorthAsking(Lesson lesson, {bool next = false}) =>
    !lesson.cancelled && (next || lesson.joinOpen || lesson.status == 'in_progress');

/// The way to a lesson's mushaf, shown once the API says the lesson has one
/// (a Quran lesson of a course the hifz map belongs to); asked lazily, and
/// [builder] draws the button the place it stands in wants.
class MushafEntry extends StatefulWidget {
  const MushafEntry({required this.lessonId, required this.builder, this.onOpen, super.key});

  final String lessonId;

  /// The button, given its label and what pressing it does.
  final Widget Function(BuildContext context, String label, VoidCallback open) builder;

  /// What opening does, when not simply the mushaf's screen (a sheet closes first).
  final VoidCallback? onOpen;

  @override
  State<MushafEntry> createState() => _MushafEntryState();
}

class _MushafEntryState extends State<MushafEntry> {
  late Future<bool> _has = context.read<MushafRepository>().has(widget.lessonId);

  @override
  void didUpdateWidget(MushafEntry old) {
    super.didUpdateWidget(old);
    if (old.lessonId != widget.lessonId) _has = context.read<MushafRepository>().has(widget.lessonId);
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<bool>(
    future: _has,
    builder: (context, snap) {
      if (snap.data != true) return const SizedBox.shrink();
      return KeyedSubtree(
        key: Key('mushaf-open-${widget.lessonId}'),
        child: widget.builder(context, context.l10n.mushafOpen, widget.onOpen ?? () => openMushaf(context, widget.lessonId)),
      ).appear(context, 0);
    },
  );
}
