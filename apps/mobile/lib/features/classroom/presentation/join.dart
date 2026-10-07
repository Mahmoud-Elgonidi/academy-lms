import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../shared/widgets/lms.dart';
import '../data/classroom_repository.dart';

/// Joins a lesson: a LiveKit room opens inside the app; an outside link
/// (Zoom, Meet) opens in its own app.
Future<void> joinLesson(BuildContext context, String lessonId) async {
  showSnack(context, context.l10n.joining);
  try {
    final ticket = await context.read<ClassroomRepository>().join(lessonId);
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    if (ticket.isLiveKit) {
      await context.push('/classroom/$lessonId', extra: ticket);
    } else {
      await launchUrl(Uri.parse(ticket.url), mode: LaunchMode.externalApplication);
    }
  } on ApiProblem catch (p) {
    if (context.mounted) showSnack(context, context.problemText(p));
  }
}
