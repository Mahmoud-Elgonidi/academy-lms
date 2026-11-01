import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/widgets/latin_digits.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../auth/domain/contact.dart';
import '../../../auth/presentation/widgets/contact_field.dart';
import '../../../auth/presentation/widgets/retry_wait.dart';
import '../trial_cubit.dart';
import '../widgets.dart';

/// The number (WhatsApp) and, if they like, an email, under a summary of
/// what they asked for with a way to change each part. While the family
/// books, a code goes to the number; otherwise the request goes.
class ContactStep extends StatefulWidget {
  const ContactStep({super.key});

  @override
  State<ContactStep> createState() => _ContactStepState();
}

class _ContactStepState extends State<ContactStep> {
  late final TrialCubit _cubit;
  late final TextEditingController _phone;
  late final TextEditingController _email;
  late String _country = Contact.countryFor(context.read<SessionCubit>().state.deviceTimezone);
  Map<String, String> _missing = const {};
  int _shakes = 0;

  @override
  void initState() {
    super.initState();
    _cubit = context.read<TrialCubit>();
    _phone = TextEditingController(text: _cubit.state.draft.phone);
    _email = TextEditingController(text: _cubit.state.draft.email);
  }

  @override
  void dispose() {
    // Going back keeps the number typed so far.
    if (!_cubit.isClosed && _phone.text.trim().isNotEmpty) _cubit.keep(phone: _number());
    _phone.dispose();
    _email.dispose();
    super.dispose();
  }

  String _number() => Contact.phone(_phone.text, _country);

  Future<void> _send() async {
    final l = context.l10n;
    final text = _phone.text;
    final email = latinDigits(_email.text).trim();
    final missing = <String, String>{
      if (text.trim().isEmpty)
        'phone': l.trialPhoneMissing
      else if (Contact.kindOf(text) == ContactKind.email)
        'phone': l.trialPhoneNotEmail
      else if (!Contact.phoneComplete(text))
        'phone': l.contactShortPhone,
      if (email.isNotEmpty && !Contact.emailComplete(email)) 'email': l.contactBadEmail,
    };
    setState(() => _missing = missing);
    if (missing.isNotEmpty) {
      Juice.wrong();
      if (missing.containsKey('phone')) setState(() => _shakes++);
      return;
    }
    final ok = await _cubit.submitContact(phone: _number(), email: email);
    if (!ok && mounted && _cubit.state.problem?.field('phone') != null) {
      Juice.wrong();
      setState(() => _shakes++);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocBuilder<TrialCubit, TrialState>(
      builder: (context, state) {
        final p = state.problem;
        final waiting = p?.status == 429;
        final hours = l.inHours(state.page?.trials.responseHours ?? 4);
        final general = p != null && !waiting && p.field('phone') == null && p.field('email') == null ? context.problemText(p) : null;
        return StepBody(
          title: state.selfBooks ? l.trialContactTitle : l.trialContactTitleRequest,
          lead: Lead(state.selfBooks ? l.trialContactLeadCode : l.trialContactLeadRequest(hours)),
          bottom: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (waiting) ...[
                RetryWait(key: ValueKey(p), body: context.l10n.trialTooMany, seconds: p!.retryAfter ?? 60, onDone: _cubit.clearProblem),
                const Gap(Space.s),
              ],
              SizedBox(
                height: 56,
                child: BusyButton(
                  key: const Key('trial-send'),
                  label: state.selfBooks ? l.sendCode : l.trialSendRequest,
                  busy: state.busy,
                  onPressed: waiting ? null : _send,
                ),
              ),
              if (state.canPick && state.viaRequest)
                TextButton(
                  key: const Key('trial-pick-after-all'),
                  style: TextButton.styleFrom(minimumSize: const Size.fromHeight(44)),
                  onPressed: _cubit.pickTimeAfterAll,
                  child: Text(l.trialPickTimeAfterAll),
                ),
            ],
          ),
          children: [
            _Summary(state: state),
            const Gap(Space.xl),
            if (general != null) ...[
              Semantics(
                liveRegion: true,
                child: Note(general, tone: Tone.danger, icon: Icons.error_outline_rounded),
              ),
              const Gap(Space.l),
            ],
            AutofillGroup(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ContactField(
                    controller: _phone,
                    fieldKey: const Key('trial-phone'),
                    phoneOnly: true,
                    label: l.trialPhoneLabel,
                    hint: state.selfBooks ? l.trialPhoneHint : l.trialPhoneHintRequest,
                    country: _country,
                    onCountry: (code) => setState(() => _country = code),
                    onSubmit: _send,
                    error: _missing['phone'] ?? p?.field('phone'),
                    shakes: _shakes,
                  ),
                  const Gap(Space.s),
                  TextField(
                    key: const Key('trial-email'),
                    controller: _email,
                    keyboardType: TextInputType.emailAddress,
                    autocorrect: false,
                    textDirection: TextDirection.ltr,
                    autofillHints: const [AutofillHints.email],
                    textInputAction: TextInputAction.done,
                    onChanged: (v) => _cubit.keep(email: v),
                    onSubmitted: (_) => _send(),
                    decoration: InputDecoration(
                      labelText: '${l.trialEmailLabel} ${l.optional}',
                      helperText: l.trialEmailHint,
                      errorText: _missing['email'] ?? p?.field('email'),
                      errorMaxLines: 3,
                    ),
                  ),
                ],
              ),
            ),
          ],
        );
      },
    );
  }
}

/// What they asked for, each part with a way back to change it.
class _Summary extends StatelessWidget {
  const _Summary({required this.state});

  final TrialState state;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final cubit = context.read<TrialCubit>();
    final draft = state.draft;
    final subject = state.page?.subjects.where((s) => s.id == draft.subjectId).firstOrNull?.name;
    Widget line(IconData icon, String text, TrialStep step, String key) => Row(
      children: [
        Icon(icon, size: 20, color: c.brandDeep),
        const Gap(Space.s),
        Expanded(
          child: Text(text, style: TextStyle(fontSize: 15, color: c.ink, height: 1.4)),
        ),
        TextButton(
          key: Key('trial-edit-$key'),
          style: TextButton.styleFrom(minimumSize: const Size(48, 44), foregroundColor: c.brandDeep),
          onPressed: () => cubit.edit(step),
          child: Semantics(label: '${l.trialEdit}: $text', excludeSemantics: true, child: Text(l.trialEdit)),
        ),
      ],
    );
    return LmsCard(
      padding: const EdgeInsetsDirectional.fromSTEB(14, 8, 4, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 6, bottom: 2),
            child: Text(l.trialSummary, style: context.type.section),
          ),
          line(Icons.person_outline_rounded, l.trialSummaryFor(draft.learnerName), TrialStep.who, 'who'),
          line(Icons.menu_book_outlined, draft.helpChoose ? l.trialSummaryHelp : (subject ?? ''), TrialStep.subject, 'subject'),
          if (state.selfBooks && state.chosen != null)
            line(Icons.event_available_rounded, state.chosen!.startsAt.label, TrialStep.time, 'time')
          else if (!state.selfBooks)
            line(Icons.event_note_rounded, l.trialSummaryNoTime, TrialStep.wishes, 'wishes'),
        ],
      ),
    );
  }
}
