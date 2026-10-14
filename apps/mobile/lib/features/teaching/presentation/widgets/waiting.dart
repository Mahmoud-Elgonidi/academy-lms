import 'package:flutter/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../data/teaching_repository.dart';
import '../../domain/teaching.dart';

/// What waits on the teacher (reports to write, homework to mark), read once
/// for all their tabs: the Reports tab's count, Today's tiles, the marking
/// row. It follows lessons and homework live; whoever sends a report or a
/// mark loads it again.
LoadCubit<TeacherTodo> waitingOnTeacher(BuildContext c) =>
    LoadCubit<TeacherTodo>(c.read<TeachingRepository>().todo, refreshOn: c.read<Live>().on({Live.lessons, Live.homework}));

/// Reads again what waits on the teacher, when the tabs hold it.
Future<void> reloadWaiting(BuildContext context) async {
  final LoadCubit<TeacherTodo> cubit;
  try {
    cubit = context.read<LoadCubit<TeacherTodo>>();
  } on ProviderNotFoundException {
    return;
  }
  await cubit.load();
}
