import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../dashboard/presentation/widgets/learner_switcher.dart';
import '../data/safeguarding_repository.dart';
import '../domain/safeguarding.dart';

/// Who is telling: a guardian (about the learner the family tabs show), a
/// student about themself (a child in the kids' look too), or a teacher.
enum ConcernScope { family, self, teacher }

typedef _Concerns = ({ConcernOptions options, List<MyConcern> mine});

const _icons = {
  'adult_behaviour': Icons.sentiment_dissatisfied_outlined,
  'outside_contact': Icons.chat_bubble_outline_rounded,
  'content': Icons.visibility_off_outlined,
  'bullying': Icons.back_hand_outlined,
  'wellbeing': Icons.favorite_outline_rounded,
  'other': Icons.more_horiz_rounded,
};

/// "Tell us" (families, every look) and "Raise a concern" (teachers): what to
/// do first if someone is in danger, the concern in the reporter's own words,
/// and the ones they raised with where each stands. Only the academy's
/// safeguarding leads read them. It follows the person live.
class TellUsPage extends StatelessWidget {
  const TellUsPage({required this.scope, super.key});

  final ConcernScope scope;

  @override
  Widget build(BuildContext context) {
    final learnerId = scope == ConcernScope.family ? context.watch<LearnerCubit>().state.selected?.id : null;
    final repo = context.read<SafeguardingRepository>();
    final l = context.l10n;
    return BlocProvider(
      key: ValueKey(learnerId),
      create: (c) => LoadCubit<_Concerns>(
        () async => (options: await repo.options(learnerId: learnerId), mine: await repo.mine(learnerId: learnerId)),
        refreshOn: c.read<Live>().on({Live.concerns}),
      )..load(),
      child: Scaffold(
        appBar: AppBar(
          title: BlocBuilder<LoadCubit<_Concerns>, LoadState<_Concerns>>(
            builder: (context, s) => Text(switch (s.data?.options.look) {
              'kids' => l.tellUsTitleKids,
              'student' => l.tellUsTitleStudent,
              'teacher' => l.concernTeacherTitle,
              _ => l.tellUsTitle,
            }),
          ),
          // A guardian tells about one child: which, and another a tap away.
          actions: [if (scope == ConcernScope.family) const LearnerChip()],
        ),
        body: LoadView<_Concerns>(
          builder: (context, data) => ListView(
            padding: pagePadding,
            physics: const AlwaysScrollableScrollPhysics(),
            children: staggered(context, [
              Text(switch (data.options.look) {
                'kids' => l.tellUsLeadKids,
                'student' => l.tellUsLeadStudent,
                'teacher' => l.concernTeacherLead,
                _ => l.tellUsLead,
              }, style: context.text.bodyMedium?.copyWith(color: context.colors.muted)),
              const SizedBox(height: 12),
              if (data.options.danger.isNotEmpty) Note(data.options.danger, tone: Tone.danger, icon: Icons.warning_amber_rounded),
              const SizedBox(height: 14),
              _ConcernForm(options: data.options, learnerId: learnerId),
              const SizedBox(height: 20),
              Text(l.concernsMine, style: context.text.titleMedium),
              const SizedBox(height: 8),
              if (data.mine.isEmpty) EmptyNote(l.concernsNone, icon: Icons.shield_outlined),
              for (final c in data.mine) ...[_ConcernTile(concern: c), const SizedBox(height: 10)],
            ]),
          ),
        ),
      ),
    );
  }
}

class _ConcernTile extends StatelessWidget {
  const _ConcernTile({required this.concern});

  final MyConcern concern;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return LmsCard(
      key: Key('concern-${concern.id}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(concern.categoryLabel, style: context.text.titleSmall)),
              Pill(
                concern.statusLabel,
                tone: switch (concern.status) {
                  'open' => Tone.brand,
                  'new' => Tone.attention,
                  _ => Tone.neutral,
                },
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text([l.concernNumber(concern.number), ?concern.createdAt?.label].join(' · '), style: context.text.bodySmall),
          if (concern.text.isNotEmpty) ...[const SizedBox(height: 6), Text(concern.text, maxLines: 3, overflow: TextOverflow.ellipsis)],
          if (concern.reply != null) ...[
            const SizedBox(height: 8),
            Note(concern.reply!, icon: Icons.mark_chat_read_outlined, tone: Tone.info),
          ],
        ],
      ),
    );
  }
}

class _ConcernForm extends StatefulWidget {
  const _ConcernForm({required this.options, required this.learnerId});

  final ConcernOptions options;
  final String? learnerId;

  @override
  State<_ConcernForm> createState() => _ConcernFormState();
}

class _ConcernFormState extends State<_ConcernForm> {
  String? _category;
  String? _lesson;
  String? _student;
  bool _urgent = false;
  final _text = TextEditingController();
  bool _busy = false;
  String? _error;
  String? _thanks;

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final l = context.l10n;
    if (_category == null) {
      setState(() => _error = l.concernPick);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final thanks = await context.read<SafeguardingRepository>().raise(
        category: _category!,
        text: _text.text,
        urgent: widget.options.urgentAllowed && _urgent,
        learnerId: widget.learnerId,
        lessonId: _lesson,
        studentId: _student,
      );
      if (!mounted) return;
      _text.clear();
      setState(() {
        _thanks = thanks;
        _category = null;
        _lesson = null;
        _student = null;
        _urgent = false;
      });
    } on ApiProblem catch (p) {
      if (mounted) setState(() => _error = context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final o = widget.options;
    if (_thanks != null) {
      return LmsCard(
        key: const Key('concern-thanks'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.check_circle_outline_rounded, size: 36, color: context.colors.brand),
            const SizedBox(height: 8),
            Text(_thanks!, style: context.text.titleMedium),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: () => setState(() => _thanks = null),
              icon: const Icon(Icons.add_rounded),
              label: Text(o.teacher ? l.concernAgainTeacher : l.concernAgain),
            ),
          ],
        ),
      );
    }
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (o.learnerName != null && !o.teacher && o.look == 'classic') ...[
            Text(l.concernAbout(o.learnerName!), style: context.text.bodySmall),
            const SizedBox(height: 8),
          ],
          Text(o.kids ? l.concernWhatKids : l.concernWhat, style: context.text.labelLarge),
          const SizedBox(height: 8),
          // Two big buttons a row for a child; one full row each, with room for the words, for everyone else.
          LayoutBuilder(
            builder: (context, box) => Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final c in o.categories)
                  SizedBox(
                    width: o.kids ? (box.maxWidth - 8) / 2 : box.maxWidth,
                    child: _Choice(
                      key: Key('category-${c.key}'),
                      label: c.label,
                      icon: _icons[c.key] ?? Icons.flag_outlined,
                      big: o.kids,
                      selected: _category == c.key,
                      onTap: () => setState(() => _category = c.key),
                    ),
                  ),
              ],
            ),
          ),
          if (o.teacher && o.students.isNotEmpty) ...[
            const SizedBox(height: 12),
            DropdownButtonFormField<String?>(
              initialValue: _student,
              isExpanded: true,
              decoration: InputDecoration(labelText: l.concernStudent),
              items: [
                DropdownMenuItem<String?>(child: Text(l.concernNoStudent)),
                for (final s in o.students)
                  DropdownMenuItem<String?>(
                    value: s.id,
                    child: Text(s.label, overflow: TextOverflow.ellipsis),
                  ),
              ],
              onChanged: (v) => setState(() => _student = v),
            ),
          ],
          if (o.lessons.isNotEmpty) ...[
            const SizedBox(height: 12),
            DropdownButtonFormField<String?>(
              initialValue: _lesson,
              isExpanded: true,
              decoration: InputDecoration(labelText: l.concernLesson),
              items: [
                DropdownMenuItem<String?>(child: Text(l.concernNoLesson)),
                for (final lesson in o.lessons)
                  DropdownMenuItem<String?>(
                    value: lesson.id,
                    child: Text(lesson.label, overflow: TextOverflow.ellipsis),
                  ),
              ],
              onChanged: (v) => setState(() => _lesson = v),
            ),
          ],
          const SizedBox(height: 12),
          TextField(
            key: const Key('concern-text'),
            controller: _text,
            minLines: o.kids ? 2 : 4,
            maxLines: 8,
            maxLength: 5000,
            decoration: InputDecoration(
              labelText: o.kids ? l.concernTextKids : l.concernText,
              helperText: o.kids ? null : l.concernTextHint,
            ),
          ),
          if (o.urgentAllowed)
            CheckboxListTile(
              key: const Key('concern-urgent'),
              value: _urgent,
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              title: Text(l.concernUrgent, style: context.text.titleSmall?.copyWith(color: context.colors.danger)),
              onChanged: (v) => setState(() => _urgent = v ?? false),
            ),
          if (_error != null) ...[const SizedBox(height: 8), Note(_error!, tone: Tone.danger)],
          const SizedBox(height: 12),
          FilledButton.icon(
            key: const Key('concern-send'),
            onPressed: _busy ? null : _send,
            icon: const Icon(Icons.send_rounded),
            label: Text(o.teacher ? l.concernSendTeacher : l.concernSend),
          ),
        ],
      ),
    );
  }
}

class _Choice extends StatelessWidget {
  const _Choice({required this.label, required this.icon, required this.big, required this.selected, required this.onTap, super.key});

  final String label;
  final IconData icon;
  final bool big;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Semantics(
      selected: selected,
      button: true,
      child: Material(
        color: selected ? c.brandSoft : c.card,
        shape: RoundedRectangleBorder(
          borderRadius: context.shape.cardRadius,
          side: BorderSide(color: selected ? c.brand : c.line, width: selected ? 2 : 1),
        ),
        child: InkWell(
          borderRadius: context.shape.cardRadius,
          onTap: onTap,
          child: Padding(
            padding: EdgeInsets.all(big ? 14 : 12),
            child: big
                ? Column(
                    children: [
                      Icon(icon, size: 36, color: c.brand),
                      const SizedBox(height: 6),
                      Text(
                        label,
                        textAlign: TextAlign.center,
                        style: context.text.titleSmall?.copyWith(fontWeight: selected ? FontWeight.w800 : null),
                      ),
                    ],
                  )
                : Row(
                    children: [
                      Icon(icon, size: 22, color: c.brand),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(label, style: context.text.bodyMedium?.copyWith(fontWeight: selected ? FontWeight.w700 : null)),
                      ),
                      if (selected) Icon(Icons.check_circle_rounded, size: 20, color: c.brand),
                    ],
                  ),
          ),
        ),
      ),
    );
  }
}
