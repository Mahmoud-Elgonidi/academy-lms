import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/open_link.dart';
import '../data/classroom_repository.dart';

/// Lessons being joined now: a second tap while the first is on its way
/// does nothing (two rooms with the same person would push each other out).
final _joining = <String>{};

/// Joins a lesson: a LiveKit room opens inside the app; an outside link
/// (Zoom, Meet) opens in its own app.
Future<void> joinLesson(BuildContext context, String lessonId) async {
  if (!_joining.add(lessonId)) return;
  try {
    showSnack(context, context.l10n.joining);
    final ticket = await context.read<ClassroomRepository>().join(lessonId);
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    if (ticket.isLiveKit) {
      // Back from the room, the lesson may be joined again.
      _joining.remove(lessonId);
      await context.push('/classroom/$lessonId', extra: ticket);
    } else {
      await openLink(context, Uri.parse(ticket.url), mode: LaunchMode.externalApplication);
    }
  } on ApiProblem catch (p) {
    if (context.mounted) showSnack(context, context.problemText(p));
  } finally {
    _joining.remove(lessonId);
  }
}
