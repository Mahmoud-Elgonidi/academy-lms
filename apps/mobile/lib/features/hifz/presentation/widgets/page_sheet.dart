import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../homework/data/homework_repository.dart';
import '../../../homework/domain/recitation.dart';
import '../../../homework/presentation/widgets/recited_words.dart';
import '../../../homework/presentation/widgets/voice_recorder.dart';
import '../../data/hifz_repository.dart';
import '../../domain/hifz.dart';
import 'hifz_map_view.dart';

/// The longest recitation of a page.
const reciteLimit = Duration(minutes: 5);

/// A page's name in the reader's language: the API's Arabic title ("صفحة ١٢ · البقرة"), else the page and its surahs.
String pageTitle(BuildContext context, {required int page, String title = '', List<Surah> surahs = const []}) {
  final ar = context.locale == 'ar';
  if (ar && title.isNotEmpty) return title;
  final names = [
    for (final s in surahs)
      if ((ar ? s.nameAr : s.nameEn).isNotEmpty) ar ? s.nameAr : s.nameEn,
  ];
  final number = context.l10n.hifzPageNumber(page);
  return names.isEmpty ? number : '$number · ${names.join(ar ? '، ' : ', ')}';
}

/// A review's grade (1 forgot … 4 easy) as a chip's tone.
Tone gradeTone(int? grade) => switch (grade) {
  1 => Tone.danger,
  2 => Tone.attention,
  3 => Tone.info,
  4 => Tone.brand,
  _ => Tone.neutral,
};

/// Opens a page of the review over the screen. Where the academy checks
/// recitations ([checks]), the learner records the page from memory and
/// waits for the check, which the hifz signal brings (no polling); else, or
/// when the check could not hear it, they recite from memory, show the page
/// and say how it went. With [recitationId] it opens on that recitation's
/// check. Returns the page as graded here, or null.
Future<QuranPage?> openHifzPage(
  BuildContext context, {
  required int page,
  required String title,
  required bool checks,
  Map<String, String> strengths = const {},
  String? learnerId,
  String? recitationId,
}) => showActionSheet<QuranPage>(
  context,
  builder: (_) =>
      HifzPageSheet(page: page, title: title, checks: checks, strengths: strengths, learnerId: learnerId, recitationId: recitationId),
);

class HifzPageSheet extends StatefulWidget {
  const HifzPageSheet({
    required this.page,
    required this.title,
    required this.checks,
    this.strengths = const {},
    this.learnerId,
    this.recitationId,
    super.key,
  });

  final int page;
  final String title;
  final bool checks;

  /// Each strength's name in the reader's language (the map's).
  final Map<String, String> strengths;
  final String? learnerId;
  final String? recitationId;

  @override
  State<HifzPageSheet> createState() => _HifzPageSheetState();
}

class _HifzPageSheetState extends State<HifzPageSheet> {
  late Future<QuranPage> _text = _load();
  late String? _recitation = widget.recitationId;
  // A recitation opened from the map shows its check, whichever way the academy reviews now.
  late bool _self = !widget.checks && widget.recitationId == null;
  HandInFile? _audio;
  bool _revealed = false;
  bool _busy = false;
  String? _error;

  HifzRepository get _repo => context.read<HifzRepository>();

  Future<QuranPage> _load() => context.read<HifzRepository>().page(widget.page, learnerId: widget.learnerId);

  Future<void> _send() async {
    final audio = _audio;
    if (audio == null || _busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final sent = await _repo.recite(widget.page, audio, learnerId: widget.learnerId);
      if (!mounted) return;
      Juice.tap();
      setState(() {
        _busy = false;
        _recitation = sent.id;
      });
    } on ApiProblem catch (p) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = context.problemText(p);
        // The check was turned off meanwhile: the learner says how it went instead.
        if (p.status == 403) _self = true;
      });
    }
  }

  Future<void> _grade(int grade) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final graded = await _repo.grade(widget.page, grade, learnerId: widget.learnerId);
      if (mounted) Navigator.of(context).pop(graded);
    } on ApiProblem catch (p) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = context.problemText(p);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final recitation = _recitation;
    final Widget body;
    if (recitation != null && !_self) {
      body = BlocProvider(
        key: ValueKey(recitation),
        create: (c) => LoadCubit<HifzRecitation>(
          () => c.read<HifzRepository>().recitation(recitation, learnerId: widget.learnerId),
          refreshOn: c.read<Live>().on({Live.hifz}),
        )..load(),
        child: _Check(onSelf: () => setState(() => _self = true), onDone: () => Navigator.of(context).pop()),
      );
    } else if (!_self) {
      body = _recite(l);
    } else {
      body = _selfGrade(l);
    }
    return SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(Space.xl - 4, 0, Space.xl - 4, Space.xl + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Semantics(header: true, child: Text(widget.title, style: context.type.title)),
          FutureBuilder<QuranPage>(
            future: _text,
            builder: (context, snap) {
              final page = snap.data;
              if (page == null) return const SizedBox.shrink();
              return Padding(
                padding: const EdgeInsets.only(top: Space.xs),
                child: Wrap(
                  spacing: Space.s,
                  runSpacing: Space.xs,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Text(l.hifzPageOf(page.page, page.juz), style: context.type.caption),
                    StatusChip(widget.strengths[page.strength.key] ?? page.strength.key, tone: strengthTone(page.strength)),
                  ],
                ),
              );
            },
          ),
          const Gap(Space.l),
          body,
          if (_error != null) ...[const Gap(Space.s), Text(_error!, style: TextStyle(color: c.dangerInk))],
        ],
      ),
    );
  }

  Widget _recite(AppLocalizations l) {
    final kids = context.isKids;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l.hifzReciteHint, style: context.type.body),
        const Gap(Space.l),
        VoiceRecorder(
          big: kids,
          limit: reciteLimit,
          label: kids ? l.hifzReciteKids : l.hifzRecite,
          onRecorded: (file) => setState(() => _audio = file),
        ),
        if (_audio != null) ...[
          const Gap(Space.m),
          PrimaryAction(l.hifzSendRecitation, key: const Key('hifz-send'), icon: Icons.send_rounded, busy: _busy, onPressed: _send),
        ],
      ],
    );
  }

  Widget _selfGrade(AppLocalizations l) {
    if (!_revealed) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Note(l.hifzRevealHint, icon: Icons.visibility_off_outlined),
          const Gap(Space.m),
          PrimaryAction(
            l.hifzReveal,
            key: const Key('hifz-reveal'),
            icon: Icons.visibility_rounded,
            onPressed: () => setState(() => _revealed = true),
          ),
        ],
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        FutureBuilder<QuranPage>(
          future: _text,
          builder: (context, snap) {
            if (snap.data case final page?) return PageText(page: page).appear(context, 0);
            if (snap.error case final error?) {
              return Note(
                context.problemText(error),
                tone: Tone.danger,
                icon: Icons.error_outline_rounded,
                action: TextButton(onPressed: () => setState(() => _text = _load()), child: Text(l.retry)),
              );
            }
            return const Padding(
              padding: EdgeInsets.all(Space.xl),
              child: Center(child: CircularProgressIndicator.adaptive()),
            );
          },
        ),
        const Gap(Space.l),
        Semantics(header: true, child: Text(l.hifzHowWent, style: context.type.cardTitle)),
        const Gap(Space.s),
        GradeButtons(busy: _busy, onGrade: _grade),
      ],
    );
  }
}

/// The check of a recited page, read again when the hifz signal comes: "listening" while it is heard, then the
/// words in colour with the grade it counted as; when it could not be heard, why, and grading it by hand instead.
class _Check extends StatelessWidget {
  const _Check({required this.onSelf, required this.onDone});

  final VoidCallback onSelf;
  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocBuilder<LoadCubit<HifzRecitation>, LoadState<HifzRecitation>>(
      builder: (context, state) {
        final r = state.data;
        if (r == null && state.problem != null) {
          return Note(
            context.problemText(state.problem!),
            tone: Tone.danger,
            icon: Icons.error_outline_rounded,
            action: TextButton(onPressed: () => context.read<LoadCubit<HifzRecitation>>().load(), child: Text(l.retry)),
          );
        }
        if (r == null || r.pending) return const _Listening();
        if (r.check.failed) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Note(r.check.failure ?? r.check.statusLabel, tone: Tone.danger, icon: Icons.hearing_disabled_rounded),
              const Gap(Space.m),
              SecondaryAction(l.hifzSelfGrade, icon: Icons.touch_app_outlined, onPressed: onSelf),
            ],
          );
        }
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            HifzCheckView(recitation: r).appear(context, 0),
            const Gap(Space.l),
            PrimaryAction(l.awesome, onPressed: onDone),
          ],
        );
      },
    );
  }
}

class _Listening extends StatelessWidget {
  const _Listening();

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return Semantics(
      liveRegion: true,
      child: Container(
        key: const Key('hifz-listening'),
        padding: const EdgeInsets.all(Space.l),
        decoration: BoxDecoration(color: c.infoSoft, borderRadius: context.shape.cardRadius),
        child: Row(
          children: [
            SizedBox.square(dimension: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: c.infoInk)),
            const Gap(Space.m),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(l.hifzListening, style: context.type.cardTitle.copyWith(color: c.infoInk)),
                  Text(l.hifzListeningHint, style: context.type.caption.copyWith(color: c.infoInk)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// A recited page's check: how it went in a line, the grade it counted as, the words in colour, and that it is
/// checked automatically (the teacher has the last word).
class HifzCheckView extends StatelessWidget {
  const HifzCheckView({required this.recitation, super.key});

  final HifzRecitation recitation;

  @override
  Widget build(BuildContext context) {
    final check = recitation.check;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(child: Text(check.summaryLabel ?? check.statusLabel, style: context.type.label)),
            if (recitation.gradeLabel != null) StatusChip(recitation.gradeLabel!, tone: gradeTone(recitation.grade)),
          ],
        ),
        const Gap(Space.xs),
        RecitedWords(tokens: check.tokens),
        const Gap(Space.xs),
        Text(check.note, style: context.type.caption),
      ],
    );
  }
}

/// The four ways a page can have gone, in two rows: forgot, hard, good, easy.
class GradeButtons extends StatelessWidget {
  const GradeButtons({required this.onGrade, this.busy = false, super.key});

  final ValueChanged<int> onGrade;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    Widget button(int grade, String label) {
      final (bg, ink) = gradeTone(grade).colours(context);
      return Expanded(
        child: PressScale(
          child: FilledButton(
            key: Key('grade-$grade'),
            style: FilledButton.styleFrom(backgroundColor: bg, foregroundColor: ink, minimumSize: const Size(64, 52)),
            onPressed: busy ? null : PressScale.withPress(context, () => onGrade(grade)),
            child: Text(label, textAlign: TextAlign.center),
          ),
        ),
      );
    }

    return Column(
      children: [
        Row(children: [button(1, l.hifzGrade1), const Gap(Space.s), button(2, l.hifzGrade2)]),
        const Gap(Space.s),
        Row(children: [button(3, l.hifzGrade3), const Gap(Space.s), button(4, l.hifzGrade4)]),
      ],
    );
  }
}

/// A page's verses as the platform's copy writes them, right to left: a surah's name where it starts on the page,
/// each verse's number at its end, and the attribution the text is shown with.
class PageText extends StatelessWidget {
  const PageText({required this.page, super.key});

  final QuranPage page;

  static String _arabic(int n) => '$n'.split('').map((d) => '٠١٢٣٤٥٦٧٨٩'[int.parse(d)]).join();

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final words = context.type.body.copyWith(fontSize: 20, height: 2.1);
    final groups = <List<PageVerse>>[];
    for (final v in page.verses) {
      if (groups.isEmpty || groups.last.last.surah != v.surah) {
        groups.add([v]);
      } else {
        groups.last.add(v);
      }
    }
    return Container(
      key: const Key('hifz-page-text'),
      padding: const EdgeInsets.all(Space.l),
      decoration: BoxDecoration(
        color: c.page,
        borderRadius: context.shape.cardRadius,
        border: Border.all(color: c.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final group in groups) ...[
            if (group.first.verse == 1 && page.surah(group.first.surah) != null)
              Padding(
                padding: const EdgeInsets.only(bottom: Space.s),
                child: Center(child: StatusChip(page.surah(group.first.surah)!.nameAr, tone: Tone.brand)),
              ),
            Directionality(
              textDirection: TextDirection.rtl,
              child: Text.rich(
                TextSpan(
                  children: [
                    for (final v in group) ...[
                      TextSpan(text: v.text),
                      TextSpan(
                        text: ' ﴿${_arabic(v.verse)}﴾ ',
                        style: words.copyWith(color: c.brandText, fontSize: 16),
                      ),
                    ],
                  ],
                ),
                style: words,
                textAlign: TextAlign.justify,
              ),
            ),
          ],
          if (page.attribution.isNotEmpty) ...[
            const Gap(Space.s),
            Text(page.attribution, style: context.type.caption.copyWith(fontSize: 11.5)),
          ],
        ],
      ),
    );
  }
}
