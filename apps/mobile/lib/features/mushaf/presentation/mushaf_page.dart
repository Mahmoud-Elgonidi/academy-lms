import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/state/load_cubit.dart';
import '../domain/mushaf.dart';
import 'widgets/mushaf_pane.dart';

/// A lesson's shared mushaf on a screen of its own, opened from the
/// lesson (for every lesson, also one on Zoom or Meet in its own app): the
/// teacher turns the pages and marks words; the student and their guardians
/// follow the teacher's page, live, and may look elsewhere and come back.
class LessonMushafPage extends StatefulWidget {
  const LessonMushafPage({required this.lessonId, super.key});

  final String lessonId;

  @override
  State<LessonMushafPage> createState() => _LessonMushafPageState();
}

class _LessonMushafPageState extends State<LessonMushafPage> {
  late final _place = MushafPlace(widget.lessonId);

  @override
  Widget build(BuildContext context) => BlocProvider<LoadCubit<LessonMushaf>>(
    create: (c) => mushafCubit(c, _place)..load(),
    child: Scaffold(
      appBar: AppBar(title: Text(context.l10n.mushafTitle)),
      body: MushafPane(place: _place),
    ),
  );
}
