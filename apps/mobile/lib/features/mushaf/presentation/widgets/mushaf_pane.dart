import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/latin_digits.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../hifz/presentation/widgets/page_sheet.dart' show pageTitle;
import '../../data/mushaf_repository.dart';
import '../../domain/mushaf.dart';
import 'mushaf_view.dart';

/// Which lesson's mushaf a screen shows, and the page asked for: null
/// follows the teacher (the page they have open, else where the lesson
/// starts); a page number is the student looking elsewhere.
class MushafPlace {
  MushafPlace(this.sessionId);

  final String sessionId;
  int? asked;
}

/// The lesson's mushaf for a screen: the page [place] asks for, read again
/// when the mushaf signal comes (the teacher turned or marked). Reaching the
/// teacher's page again follows them from then on.
LoadCubit<LessonMushaf> mushafCubit(BuildContext context, MushafPlace place) {
  final repo = context.read<MushafRepository>();
  return LoadCubit<LessonMushaf>(() async {
    final shown = await repo.page(place.sessionId, page: place.asked);
    if (shown.teaches || shown.onTeachersPage) place.asked = null;
    return shown;
  }, refreshOn: context.read<Live>().on({Live.mushaf}));
}

/// A lesson's mushaf with its controls, from the screen's
/// `LoadCubit<LessonMushaf>`: the page's name and part, what the viewer may
/// do (the teacher: tap a word to mark it while marking is open; the
/// student and their guardians: follow the teacher, or look elsewhere and
/// come back), the page itself, and turning pages (the teacher's turn moves
/// the student's screen) by word or by number. [onChanged] hears each of
/// the teacher's changes once the API took it (the room tells the others at
/// once).
class MushafPane extends StatelessWidget {
  const MushafPane({required this.place, this.onChanged, this.size = 24, super.key});

  final MushafPlace place;
  final VoidCallback? onChanged;
  final double size;

  @override
  Widget build(BuildContext context) => BlocBuilder<LoadCubit<LessonMushaf>, LoadState<LessonMushaf>>(
    buildWhen: (before, now) => (before.data == null) != (now.data == null) || before.problem != now.problem,
    builder: (context, state) {
      final problem = state.problem;
      if (state.data == null && problem != null && (problem.isNotFound || problem.status == 403)) {
        return ListView(
          padding: pagePadding,
          children: [EmptyState(icon: Icons.menu_book_outlined, title: context.l10n.mushafNone)],
        );
      }
      return LoadView<LessonMushaf>(
        refreshable: false,
        skeleton: const SkeletonList(count: 2),
        builder: (context, page) => _Pane(page: page, place: place, onChanged: onChanged, size: size),
      );
    },
  );
}

class _Pane extends StatefulWidget {
  const _Pane({required this.page, required this.place, required this.size, this.onChanged});

  final LessonMushaf page;
  final MushafPlace place;
  final VoidCallback? onChanged;
  final double size;

  @override
  State<_Pane> createState() => _PaneState();
}

class _PaneState extends State<_Pane> {
  late final _number = TextEditingController(text: '${widget.page.page}');
  final _focus = FocusNode();
  bool _busy = false;

  LessonMushaf get _m => widget.page;

  @override
  void didUpdateWidget(_Pane old) {
    super.didUpdateWidget(old);
    if (old.page.page != widget.page.page && !_focus.hasFocus) _number.text = '${widget.page.page}';
  }

  @override
  void dispose() {
    _number.dispose();
    _focus.dispose();
    super.dispose();
  }

  /// A change of the teacher's: shown at once from the API's answer, and told to the room.
  Future<void> _write(Future<LessonMushaf> Function(MushafRepository repo) change) async {
    if (_busy) return;
    final cubit = context.read<LoadCubit<LessonMushaf>>();
    final repo = context.read<MushafRepository>();
    setState(() => _busy = true);
    try {
      cubit.show(await change(repo));
      widget.onChanged?.call();
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, p.isOffline ? context.l10n.offline : (p.message ?? context.l10n.mushafFailed));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _go(int page) async {
    final m = _m;
    final to = page.clamp(1, m.pagesTotal < 1 ? 1 : m.pagesTotal);
    _focus.unfocus();
    if (to == m.page) {
      _number.text = '${m.page}';
      return;
    }
    if (m.teaches) return _write((repo) => repo.turn(m.sessionId, to));
    // The student (or a guardian) looks elsewhere: the teacher's page still comes with each answer.
    widget.place.asked = to;
    await context.read<LoadCubit<LessonMushaf>>().load();
  }

  Future<void> _follow() async {
    widget.place.asked = null;
    await context.read<LoadCubit<LessonMushaf>>().load();
  }

  Future<void> _word(WordAt at) async {
    final m = _m;
    final current = m.marks[at];
    final verse = m.verses.where((v) => v.surah == at.surah && v.verse == at.verse).firstOrNull;
    final text = verse == null || at.word > verse.words.length ? '' : verse.words[at.word - 1];
    final choice = await showActionSheet<_Choice>(
      context,
      builder: (_) => _MarkSheet(word: text, current: current, label: m.label),
    );
    if (choice == null || !mounted) return;
    switch (choice) {
      case _Mark(:final kind) when kind != current:
        await _write((repo) => repo.mark(m.sessionId, at, kind, page: m.page));
      case _Clear() when current != null:
        await _write((repo) => repo.unmark(m.sessionId, at, page: m.page));
      case _:
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final m = _m;
    final marking = m.teaches && m.canMark;
    final status = <Widget>[
      if (m.teaches)
        Text(marking ? l.mushafHintTeacher : l.mushafMarkingClosed, key: const Key('mushaf-hint'), style: context.type.caption)
      else if (m.onTeachersPage)
        Row(
          key: const Key('mushaf-following'),
          children: [
            Icon(Icons.sync_rounded, size: 18, color: context.colors.brandText),
            const Gap(Space.xs),
            Expanded(child: Text(l.mushafFollowing, style: context.type.caption)),
          ],
        )
      else
        Note(
          l.mushafTeacherOn(m.teachersView),
          icon: Icons.person_pin_circle_outlined,
          action: FilledButton.tonal(key: const Key('mushaf-follow'), onPressed: _follow, child: Text(l.mushafBackToTeacher)),
        ),
    ];
    final children = <Widget>[
      Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: Semantics(
              header: true,
              child: Text(
                pageTitle(context, page: m.page, title: m.title, surahs: m.surahs),
                style: context.type.cardTitle,
              ),
            ),
          ),
          const Gap(Space.s),
          Text(l.hifzJuz(m.juz), style: context.type.caption),
        ],
      ),
      ...status,
      MushafView(page: m, onWord: marking ? _word : null, size: widget.size),
      if (marking || m.marks.isNotEmpty || m.earlier.isNotEmpty) MistakesKey(kinds: m.label, earlier: m.earlier.isNotEmpty),
    ];
    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: pagePadding.copyWith(top: Space.m),
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              for (final (i, w) in children.indexed)
                Padding(
                  padding: const EdgeInsets.only(bottom: Space.m),
                  child: w.appear(context, i),
                ),
            ],
          ),
        ),
        _Turns(page: m, number: _number, focus: _focus, busy: _busy, onGo: _go),
      ],
    );
  }
}

/// Turning pages: the one before and the one after in words (a mushaf reads right to left, so the one before is on
/// the reading side), and a page by its number.
class _Turns extends StatelessWidget {
  const _Turns({required this.page, required this.number, required this.focus, required this.busy, required this.onGo});

  final LessonMushaf page;
  final TextEditingController number;
  final FocusNode focus;
  final bool busy;
  final ValueChanged<int> onGo;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final p = page;
    return Material(
      color: c.card,
      child: SafeArea(
        top: false,
        child: Container(
          decoration: BoxDecoration(
            border: Border(top: BorderSide(color: c.line)),
          ),
          padding: const EdgeInsets.fromLTRB(Space.m, Space.s, Space.m, Space.s),
          child: Row(
            children: [
              Expanded(
                child: TextButton(
                  key: const Key('mushaf-previous'),
                  style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
                  onPressed: busy || !p.hasPrevious ? null : () => onGo(p.page - 1),
                  child: Text(l.mushafPrevious, textAlign: TextAlign.center),
                ),
              ),
              Text(l.mushafPageField, style: context.type.caption),
              const Gap(Space.xs),
              SizedBox(
                width: 64,
                child: TextField(
                  key: const Key('mushaf-number'),
                  controller: number,
                  focusNode: focus,
                  enabled: !busy,
                  textAlign: TextAlign.center,
                  keyboardType: TextInputType.number,
                  textInputAction: TextInputAction.go,
                  inputFormatters: [const LatinDigits(), FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(3)],
                  decoration: InputDecoration(
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(horizontal: Space.xs, vertical: Space.s),
                    hintText: '${p.page}',
                  ),
                  onSubmitted: (v) => onGo(int.tryParse(v) ?? p.page),
                ),
              ),
              const Gap(Space.xs),
              Text(l.mushafPageOfTotal(p.pagesTotal), style: context.type.caption),
              Expanded(
                child: TextButton(
                  key: const Key('mushaf-next'),
                  style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
                  onPressed: busy || !p.hasNext ? null : () => onGo(p.page + 1),
                  child: Text(l.mushafNext, textAlign: TextAlign.center),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// What the teacher chose for a word: a kind, or its mark off.
sealed class _Choice {
  const _Choice();
}

class _Mark extends _Choice {
  const _Mark(this.kind);

  final MistakeKind kind;
}

class _Clear extends _Choice {
  const _Clear();
}

/// The word in the Quran's face, the three kinds in their colours (the one it is marked as, checked), and its mark
/// off when it has one.
class _MarkSheet extends StatelessWidget {
  const _MarkSheet({required this.word, required this.current, required this.label});

  final String word;
  final MistakeKind? current;
  final String Function(MistakeKind kind) label;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(Space.xl - 4, 0, Space.xl - 4, Space.xl),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.mushafMarkWord, style: context.type.caption, textAlign: TextAlign.center),
          Text(word, textAlign: TextAlign.center, textDirection: TextDirection.rtl, style: quranStyle(context, size: 34)),
          const Gap(Space.m),
          for (final kind in MistakeKind.values) ...[
            Builder(
              builder: (context) {
                final (bg, ink) = mistakeTone(kind).colours(context);
                final chosen = kind == current;
                return FilledButton.icon(
                  key: Key('kind-${kind.name}'),
                  style: FilledButton.styleFrom(
                    backgroundColor: bg,
                    foregroundColor: ink,
                    minimumSize: const Size.fromHeight(52),
                    side: chosen ? BorderSide(color: ink, width: 2) : null,
                  ),
                  onPressed: () => Navigator.of(context).pop<_Choice>(_Mark(kind)),
                  icon: Icon(chosen ? Icons.check_circle_rounded : Icons.circle, size: chosen ? 20 : 12, color: ink),
                  label: Text(label(kind), style: const TextStyle(fontWeight: FontWeight.w700)),
                );
              },
            ),
            const Gap(Space.s),
          ],
          if (current != null)
            TextButton(
              key: const Key('kind-clear'),
              style: TextButton.styleFrom(foregroundColor: c.dangerInk, minimumSize: const Size.fromHeight(48)),
              onPressed: () => Navigator.of(context).pop<_Choice>(const _Clear()),
              child: Text(l.mushafClearMark, style: const TextStyle(fontWeight: FontWeight.w700)),
            ),
        ],
      ),
    );
  }
}
