import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/done_moment.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../support/data/support_repository.dart';
import '../../domain/trial_report.dart';
import 'start_plan_cubit.dart';

/// "Start with this plan", until signing up and paying come to the app (J3):
/// says plainly what happens (the academy's team sets up the course and
/// sends the invoice, with the offer while it is open), shows the request
/// as it will reach the academy, takes an optional line (days and times
/// that suit), and sends it to Help. Where Help is not open to the family,
/// it says to reach the academy the usual way.
class StartPlanSheet extends StatefulWidget {
  const StartPlanSheet({required this.report, super.key});

  final TrialReport report;

  /// Opens the sheet; the ticket's id once the request reached the academy.
  static Future<String?> show(BuildContext context, {required TrialReport report, String? learnerId}) async {
    final sent = await showActionSheet<String>(
      context,
      builder: (_) => BlocProvider(
        create: (_) => StartPlanCubit(context.read<SupportRepository>(), learnerId: learnerId, lessonId: report.id)..load(),
        child: StartPlanSheet(report: report),
      ),
    );
    if (sent != null && context.mounted) DoneMoment.show(context, title: context.l10n.trialStartSent);
    return sent;
  }

  @override
  State<StartPlanSheet> createState() => _StartPlanSheetState();
}

class _StartPlanSheetState extends State<StartPlanSheet> {
  final _note = TextEditingController();

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  (String, String) _request(BuildContext context) {
    final l = context.l10n;
    final r = widget.report;
    final plan = r.recommendation.plan;
    final subject = plan != null ? l.trialStartSubject(plan.name, r.learner) : l.trialStartSubjectAny(r.subject, r.learner);
    final body = plan != null ? l.trialStartBody(r.subject, plan.name) : l.trialStartBodyAny(r.subject);
    return (subject.length > 150 ? subject.substring(0, 150) : subject, body);
  }

  Future<void> _send() async {
    final (subject, body) = _request(context);
    final cubit = context.read<StartPlanCubit>();
    if (await cubit.send(subject: subject, body: body, note: _note.text) && mounted) {
      Navigator.pop(context, cubit.state.sentId);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final r = widget.report;
    final (subject, body) = _request(context);
    final open = r.openAt(DateTime.now());
    return BlocBuilder<StartPlanCubit, StartPlanState>(
      builder: (context, state) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, Space.l, 20, Space.xl),
          child: Column(
            key: const Key('start-sheet'),
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Semantics(header: true, child: Text(l.trialStartTitle, style: context.type.title)),
              const Gap(Space.s),
              Text(l.trialStartLead, style: context.type.body.copyWith(color: c.muted, height: 1.7)),
              if (open) ...[const Gap(Space.m), Note(l.trialStartOfferNote, tone: Tone.attention, icon: Icons.local_offer_outlined)],
              const Gap(Space.l),
              if (state.loading && state.options == null)
                const Padding(
                  padding: EdgeInsets.all(Space.xl),
                  child: Center(child: CircularProgressIndicator.adaptive()),
                )
              else if (state.problem != null)
                ErrorState.of(state.problem!, onRetry: context.read<StartPlanCubit>().load)
              else if (state.noHelp)
                Note(l.trialStartNoHelp, key: const Key('start-no-help'), icon: Icons.call_outlined)
              else ...[
                Text(l.trialStartWhat, style: context.type.label.copyWith(color: c.muted)),
                const Gap(Space.xs),
                Container(
                  key: const Key('start-preview'),
                  padding: const EdgeInsets.all(Space.m),
                  decoration: BoxDecoration(color: c.chip, borderRadius: BorderRadius.circular(context.shape.card - 2)),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(subject, style: context.type.cardTitle),
                      const Gap(Space.xs),
                      Text(body, style: context.type.body.copyWith(height: 1.7)),
                    ],
                  ),
                ),
                const Gap(Space.m),
                TextField(
                  key: const Key('start-note'),
                  controller: _note,
                  minLines: 2,
                  maxLines: 5,
                  maxLength: 1000,
                  decoration: InputDecoration(labelText: l.trialStartNote),
                ),
                if (state.sendProblem != null) ...[
                  Note(context.problemText(state.sendProblem!), key: const Key('start-problem'), tone: Tone.danger),
                  const Gap(Space.m),
                ],
                SizedBox(
                  height: 54,
                  child: PrimaryAction(
                    l.trialStartSend,
                    key: const Key('start-send'),
                    icon: Icons.send_rounded,
                    busy: state.sending,
                    onPressed: state.sending ? null : _send,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
