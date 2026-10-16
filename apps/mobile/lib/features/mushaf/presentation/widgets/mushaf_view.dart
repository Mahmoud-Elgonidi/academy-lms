import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../domain/mushaf.dart';

/// The Quran's own face (Amiri Quran, bundled), for its words alone.
const quranFont = 'AmiriQuran';

/// The Quran's words at [size], in [colour].
TextStyle quranStyle(BuildContext context, {double size = 24, Color? colour}) =>
    TextStyle(fontFamily: quranFont, fontSize: size, height: 2.2, color: colour ?? context.colors.ink);

/// A kind of mistake as a tone: wrong in danger, a hesitation in attention, tajweed in info.
Tone mistakeTone(MistakeKind kind) => switch (kind) {
  MistakeKind.wrong => Tone.danger,
  MistakeKind.hesitation => Tone.attention,
  MistakeKind.tajweed => Tone.info,
};

/// The colour a tone draws its mark's line in.
Color toneLine(BuildContext context, Tone tone) {
  final c = context.colors;
  return switch (tone) {
    Tone.danger => c.danger,
    Tone.attention => c.attention,
    Tone.info => c.info,
    Tone.reward => c.reward,
    Tone.brand => c.brand,
    Tone.neutral => c.muted,
  };
}

/// A page of a lesson's shared mushaf, right to left in the Quran's face,
/// each word its own span: a surah's name where it begins, its basmala from
/// the platform's copy (not al-Fatiha's, not at-Tawba's), each verse's
/// number at its end (﴿١٢﴾), the words marked in this lesson in their kind's
/// colour and the ones marked before underlined faintly, and the attribution
/// under the text. With [onWord] (the teacher, while marking is open) each
/// word answers a tap.
class MushafView extends StatefulWidget {
  const MushafView({required this.page, this.onWord, this.size = 24, super.key});

  final LessonMushaf page;
  final ValueChanged<WordAt>? onWord;

  /// The words' size (verse numbers come a little smaller).
  final double size;

  @override
  State<MushafView> createState() => _MushafViewState();
}

class _MushafViewState extends State<MushafView> {
  /// One recognizer a word, kept while the page and the marking stay.
  final _taps = <WordAt, TapGestureRecognizer>{};

  TapGestureRecognizer _tap(WordAt at) => _taps.putIfAbsent(at, () => TapGestureRecognizer()..onTap = () => widget.onWord?.call(at));

  void _forget() {
    for (final t in _taps.values) {
      t.dispose();
    }
    _taps.clear();
  }

  @override
  void didUpdateWidget(MushafView old) {
    super.didUpdateWidget(old);
    if (old.page.page != widget.page.page || (old.onWord == null) != (widget.onWord == null)) _forget();
  }

  @override
  void dispose() {
    _forget();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final p = widget.page;
    final words = quranStyle(context, size: widget.size);
    final ends = words.copyWith(fontSize: widget.size * 0.8, color: c.brandText);
    final marking = widget.onWord != null;

    InlineSpan word(MushafVerse v, int place, String text) {
      final at = v.at(place);
      final mark = p.marks[at];
      final before = mark == null ? p.earlier[at] : null;
      TextStyle style = words;
      String? said;
      if (mark != null) {
        final (bg, ink) = mistakeTone(mark).colours(context);
        style = words.copyWith(backgroundColor: bg, color: ink);
        said = l.mushafWordMarked(text, p.label(mark));
      } else if (before != null) {
        style = words.copyWith(
          decoration: TextDecoration.underline,
          decorationStyle: TextDecorationStyle.dotted,
          decorationThickness: 2,
          decorationColor: toneLine(context, mistakeTone(before.kind)).withValues(alpha: 0.7),
        );
        said = l.mushafWordEarlier(text, p.label(before.kind), l.mistakesTimes(before.times));
      }
      return TextSpan(
        children: [
          TextSpan(text: text, style: style, semanticsLabel: said, recognizer: marking ? _tap(at) : null),
          const TextSpan(text: ' '),
        ],
      );
    }

    final parts = <Widget>[];
    for (final group in p.bySurah) {
      final first = group.first;
      final surah = p.surah(first.surah);
      if (first.verse == 1 && surah != null) {
        final name = l.mushafSurah(context.locale == 'ar' || surah.nameEn.isEmpty ? surah.nameAr : surah.nameEn);
        parts.add(
          Padding(
            padding: const EdgeInsets.only(top: Space.xs, bottom: Space.xs),
            child: Container(
              key: Key('mushaf-surah-${first.surah}'),
              padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.xs),
              decoration: BoxDecoration(
                color: c.brandSoft,
                borderRadius: BorderRadius.circular(context.shape.field),
                border: Border.all(color: c.brand.withValues(alpha: 0.35)),
              ),
              alignment: Alignment.center,
              child: Semantics(
                header: true,
                label: name,
                excludeSemantics: true,
                child: Text(name, style: context.type.cardTitle.copyWith(color: c.brandDeep)),
              ),
            ),
          ),
        );
      }
      if (p.basmalaBefore(first)) {
        parts.add(Text(p.basmala!.join(' '), key: const Key('mushaf-basmala'), textAlign: TextAlign.center, style: words));
      }
      parts.add(
        Text.rich(
          TextSpan(
            children: [
              for (final v in group) ...[
                for (final (i, w) in v.words.indexed) word(v, i + 1, w),
                TextSpan(text: '﴿${arabicDigits(v.verse)}﴾ ', style: ends, semanticsLabel: l.mushafVerseEnd(v.verse)),
              ],
            ],
          ),
          key: Key('mushaf-text-${first.surah}'),
          style: words,
          textAlign: TextAlign.justify,
        ),
      );
    }

    return Directionality(
      textDirection: TextDirection.rtl,
      child: Container(
        key: const Key('mushaf-page'),
        padding: const EdgeInsets.fromLTRB(Space.l, Space.m, Space.l, Space.m),
        decoration: BoxDecoration(
          color: c.card,
          borderRadius: context.shape.cardRadius,
          border: Border.all(color: c.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ...parts,
            if (p.attribution.isNotEmpty) ...[
              const Gap(Space.s),
              Text(p.attribution, style: context.type.caption.copyWith(fontSize: 11.5)),
            ],
          ],
        ),
      ),
    );
  }
}

/// What the colours mean: each kind, and a word marked in an earlier lesson.
class MistakesKey extends StatelessWidget {
  const MistakesKey({required this.kinds, this.earlier = true, super.key});

  /// Each kind's name in the reader's language.
  final String Function(MistakeKind kind) kinds;
  final bool earlier;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Wrap(
      spacing: Space.s,
      runSpacing: Space.xs,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        for (final k in MistakeKind.values) Pill(kinds(k), tone: mistakeTone(k)),
        if (earlier)
          Text(
            context.l10n.mushafEarlier,
            style: context.type.label.copyWith(
              decoration: TextDecoration.underline,
              decorationStyle: TextDecorationStyle.dotted,
              decorationThickness: 2,
              decorationColor: c.danger.withValues(alpha: 0.7),
            ),
          ),
      ],
    );
  }
}
