import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/realtime/live.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../consent/data/consent_repository.dart';
import '../../../consent/domain/consent.dart';
import '../../../start/presentation/start_page.dart';
import '../../../trial/presentation/widgets.dart';
import '../../data/family_repository.dart';
import '../../domain/learner.dart';
import '../bloc/add_child_cubit.dart';

/// «إضافة طفل» for a guardian: two calm steps under a bar, as the start flow
/// has. About the child (name, birth by day or year alone, gender if they
/// like), then the academy's consent wording with the child's name in it and
/// one tick; added, the start flow opens for the child. From the start flow's
/// learner step ([fromStart]) it goes back there with the child chosen.
class AddChildPage extends StatelessWidget {
  const AddChildPage({this.fromStart = false, super.key});

  final bool fromStart;

  static const location = '/family/children/new';

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => AddChildCubit(c.read<FamilyRepository>(), c.read<ConsentRepository>()),
    child: BlocListener<AddChildCubit, AddChildState>(
      listenWhen: (a, b) => a.added == null && b.added != null,
      listener: (context, state) => _added(context, state.added!),
      child: const _Frame(),
    ),
  );

  void _added(BuildContext context, AddedChild child) {
    // The family's tabs read their learners again: the child joins the switcher.
    context.read<Live>().changed(Live.learners);
    if (fromStart && context.canPop()) {
      context.pop(child);
    } else {
      context.pushReplacement(StartPage.location(learner: child.id), extra: child.message);
    }
  }
}

class _Frame extends StatelessWidget {
  const _Frame();

  void _close(BuildContext context) => context.canPop() ? context.pop() : context.go('/family/me');

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final state = context.watch<AddChildCubit>().state;
    final cubit = context.read<AddChildCubit>();
    final at = state.step.index;
    return PopScope(
      canPop: state.step == AddChildStep.about && !state.busy,
      onPopInvokedWithResult: (popped, _) {
        if (!popped) cubit.back();
      },
      child: Scaffold(
        body: SafeArea(
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 560),
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(8, 6, 8, 4),
                    child: Column(
                      children: [
                        Row(
                          children: [
                            IconButton(
                              key: const Key('child-back'),
                              tooltip: l.back,
                              onPressed: state.busy ? null : () => cubit.back() ? null : _close(context),
                              icon: const BackButtonIcon(),
                            ),
                            Expanded(
                              child: Text(
                                l.addChildTitle,
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  fontFamily: context.shape.headingFont,
                                  fontSize: 16,
                                  fontWeight: FontWeight.w700,
                                  color: c.ink,
                                ),
                              ),
                            ),
                            IconButton(
                              key: const Key('child-close'),
                              tooltip: l.close,
                              onPressed: state.busy ? null : () => _close(context),
                              icon: const Icon(Icons.close_rounded),
                            ),
                          ],
                        ),
                        Semantics(
                          label: l.startStepOf(at + 1, AddChildStep.values.length),
                          excludeSemantics: true,
                          child: Padding(
                            padding: const EdgeInsets.fromLTRB(12, 6, 12, 4),
                            child: Row(
                              children: [
                                for (var i = 0; i < AddChildStep.values.length; i++) ...[
                                  if (i > 0) const Gap(6),
                                  Expanded(
                                    child: AnimatedContainer(
                                      duration: context.calm ? Duration.zero : const Duration(milliseconds: 380),
                                      curve: Curves.easeOutCubic,
                                      height: 5,
                                      decoration: BoxDecoration(color: i <= at ? c.brand : c.chip, borderRadius: BorderRadius.circular(5)),
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  Expanded(
                    child: AnimatedSwitcher(
                      duration: context.calm ? Duration.zero : const Duration(milliseconds: 320),
                      switchInCurve: Curves.easeOutCubic,
                      switchOutCurve: Curves.easeInCubic,
                      transitionBuilder: (child, a) => FadeTransition(
                        opacity: a,
                        child: SlideTransition(
                          position: Tween(begin: const Offset(0, 0.03), end: Offset.zero).animate(a),
                          child: child,
                        ),
                      ),
                      child: KeyedSubtree(
                        key: ValueKey(state.step),
                        child: state.step == AddChildStep.about ? const _About() : const _Consent(),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// About the child: the name, the birth (a day, or the year alone, with why the year counts as its last day), gender if they like.
class _About extends StatefulWidget {
  const _About();

  @override
  State<_About> createState() => _AboutState();
}

class _AboutState extends State<_About> {
  late final _name = TextEditingController(text: context.read<AddChildCubit>().state.name);

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  String? _error(AppLocalizations l, String? code) => switch (code) {
    null => null,
    AddChildCubit.nameShort => l.addChildNameShort,
    AddChildCubit.birthMissing => l.addChildBirthNeeded,
    _ => code,
  };

  Future<void> _pickDate() async {
    final cubit = context.read<AddChildCubit>();
    final today = DateUtils.dateOnly(cubit.today);
    final picked = await showDatePicker(
      context: context,
      initialDate: cubit.state.birthDate ?? DateTime(today.year - 8, today.month, today.day),
      firstDate: DateTime(1990),
      lastDate: today,
      helpText: context.l10n.addChildPickDate,
      initialEntryMode: DatePickerEntryMode.calendarOnly,
      initialDatePickerMode: DatePickerMode.year,
    );
    if (picked != null) cubit.setDate(picked);
  }

  Future<void> _pickYear() async {
    final cubit = context.read<AddChildCubit>();
    final now = cubit.today.year;
    final picked = await showActionSheet<int>(
      context,
      title: context.l10n.addChildPickYear,
      builder: (c) => SizedBox(
        height: 320,
        child: GridView.count(
          crossAxisCount: 4,
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          mainAxisSpacing: 8,
          crossAxisSpacing: 8,
          childAspectRatio: 1.9,
          children: [
            for (var y = now; y >= 1990; y--)
              ChoiceChip(
                key: Key('year-$y'),
                label: SizedBox(
                  width: double.infinity,
                  child: Text('$y', textAlign: TextAlign.center),
                ),
                selected: cubit.state.birthYear == y,
                onSelected: (_) => Navigator.pop(c, y),
              ),
          ],
        ),
      ),
    );
    if (picked != null) cubit.setYear(picked);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final state = context.watch<AddChildCubit>().state;
    final cubit = context.read<AddChildCubit>();
    final birthError = _error(l, state.errors[AddChildField.birth]);
    final date = state.birthDate;
    final year = state.birthYear;
    final byYear = state.birthBy == BirthBy.year;
    return StepBody(
      badge: Icons.child_care_rounded,
      title: l.addChildAboutTitle,
      lead: Lead(l.addChildAboutLead),
      bottom: SizedBox(
        height: 54,
        child: PrimaryAction(
          l.addChildNext,
          key: const Key('child-next'),
          icon: Icons.arrow_forward_rounded,
          onPressed: () {
            FocusScope.of(context).unfocus();
            cubit.next();
          },
        ),
      ),
      children: [
        TextField(
          key: const Key('child-name'),
          controller: _name,
          onChanged: cubit.setName,
          textInputAction: TextInputAction.done,
          textCapitalization: TextCapitalization.words,
          maxLength: 80,
          decoration: InputDecoration(
            labelText: l.addChildName,
            hintText: l.addChildNameHint,
            counterText: '',
            prefixIcon: const Icon(Icons.badge_outlined),
            errorText: _error(l, state.errors[AddChildField.name]),
            errorMaxLines: 4,
          ),
        ),
        const Gap(Space.l),
        Text(l.addChildBirth, style: context.type.label),
        const Gap(Space.s),
        SegmentedButton<BirthBy>(
          key: const Key('child-birth-by'),
          segments: [
            ButtonSegment(value: BirthBy.date, label: Text(l.addChildBirthExact), icon: const Icon(Icons.event_rounded)),
            ButtonSegment(value: BirthBy.year, label: Text(l.addChildBirthYearOnly), icon: const Icon(Icons.calendar_view_month_rounded)),
          ],
          selected: {state.birthBy},
          showSelectedIcon: false,
          onSelectionChanged: (s) => cubit.birthBy(s.first),
        ),
        const Gap(Space.s),
        InkWell(
          key: const Key('child-birth'),
          borderRadius: BorderRadius.circular(context.shape.field),
          onTap: byYear ? _pickYear : _pickDate,
          child: InputDecorator(
            decoration: InputDecoration(
              prefixIcon: const Icon(Icons.cake_outlined),
              suffixIcon: const Icon(Icons.expand_more_rounded),
              errorText: birthError,
              errorMaxLines: 3,
            ),
            child: Text(
              byYear
                  ? (year == null ? l.addChildPickYear : '$year')
                  : (date == null ? l.addChildPickDate : MaterialLocalizations.of(context).formatMediumDate(date)),
              style: context.type.body.copyWith(color: (byYear ? year : date) == null ? c.muted : c.ink),
            ),
          ),
        ),
        if (byYear) ...[const Gap(Space.s), Text(l.addChildYearNote, key: const Key('child-year-note'), style: context.type.caption)],
        const Gap(Space.l),
        Row(
          children: [
            Text(l.addChildGender, style: context.type.label),
            const Gap(Space.xs),
            Text(l.optional, style: context.type.caption),
          ],
        ),
        const Gap(Space.s),
        Wrap(
          spacing: Space.s,
          children: [
            for (final (value, label, icon) in [
              ('female', l.addChildGirl, Icons.face_3_rounded),
              ('male', l.addChildBoy, Icons.face_6_rounded),
            ])
              ChoiceChip(
                key: Key('child-gender-$value'),
                avatar: Icon(icon, size: 18),
                label: Text(label),
                selected: state.gender == value,
                onSelected: (_) => cubit.setGender(value),
              ),
          ],
        ),
      ],
    );
  }
}

/// The academy's consent wording with the child's name, one tick, then adding.
class _Consent extends StatelessWidget {
  const _Consent();

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final state = context.watch<AddChildCubit>().state;
    final cubit = context.read<AddChildCubit>();
    final name = state.trimmedName.isEmpty ? l.addChildSomeone : state.trimmedName;
    final ConsentText? wording = state.wording?.named(name);
    if (wording == null) {
      if (state.loadingWording) return const Center(child: CircularProgressIndicator.adaptive(key: Key('child-wording-loading')));
      return ErrorState.of(state.problem ?? ApiProblem.unreadable(), onRetry: cubit.loadWording, key: const Key('child-wording-failed'));
    }
    final short = name.split(' ').first;
    return StepBody(
      badge: Icons.verified_user_outlined,
      title: l.addChildConsentTitle,
      lead: Lead(l.addChildConsentLead),
      bottom: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (state.problem != null) ...[
            Semantics(
              liveRegion: true,
              child: Note(
                context.problemText(state.problem!),
                key: const Key('child-problem'),
                tone: Tone.danger,
                icon: Icons.error_outline_rounded,
              ),
            ),
            const Gap(Space.s),
          ],
          SizedBox(
            height: 54,
            child: PrimaryAction(
              l.addChildSubmit(short),
              key: const Key('child-submit'),
              icon: Icons.check_rounded,
              busy: state.busy,
              onPressed: state.busy ? null : cubit.submit,
            ),
          ),
        ],
      ),
      children: [
        Container(
          key: const Key('child-consent-text'),
          padding: const EdgeInsets.all(Space.l),
          decoration: BoxDecoration(
            color: c.page,
            borderRadius: BorderRadius.circular(context.shape.card),
            border: Border.all(color: c.line),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(wording.title, style: context.type.cardTitle),
              const Gap(Space.s),
              Text(wording.intro, style: context.type.body.copyWith(height: 1.7)),
              for (final s in wording.sections) ...[
                const Gap(Space.m),
                Text(s.title, style: context.type.body.copyWith(fontWeight: FontWeight.w700)),
                const Gap(Space.xs),
                Text(s.body, style: context.type.body.copyWith(height: 1.7, color: c.muted)),
              ],
            ],
          ),
        ),
        const Gap(Space.m),
        AnimatedContainer(
          duration: context.calm ? Duration.zero : const Duration(milliseconds: 220),
          decoration: BoxDecoration(
            color: state.agreed ? c.brandSoft : c.card,
            borderRadius: BorderRadius.circular(context.shape.card),
            border: Border.all(
              color: state.agreeMissing ? c.danger : (state.agreed ? c.brand : c.line),
              width: state.agreeMissing ? 1.5 : 1,
            ),
          ),
          child: Material(
            type: MaterialType.transparency,
            child: CheckboxListTile(
              key: const Key('child-agree'),
              value: state.agreed,
              onChanged: state.busy ? null : (v) => cubit.agree(v ?? false),
              controlAffinity: ListTileControlAffinity.leading,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(context.shape.card)),
              title: Text(wording.agree, style: context.type.body.copyWith(fontWeight: FontWeight.w700, height: 1.6)),
            ),
          ),
        ),
        if (state.agreeMissing) ...[
          const Gap(Space.xs),
          Semantics(
            liveRegion: true,
            child: Text(
              l.addChildAgreeNeeded,
              key: const Key('child-agree-needed'),
              style: context.type.caption.copyWith(color: c.dangerInk),
            ),
          ),
        ],
        const Gap(Space.s),
        Align(
          alignment: AlignmentDirectional.centerStart,
          child: TextButton.icon(
            key: const Key('child-edit'),
            onPressed: state.busy ? null : cubit.back,
            icon: const Icon(Icons.edit_outlined, size: 18),
            label: Text(l.addChildEdit),
          ),
        ),
      ],
    );
  }
}
