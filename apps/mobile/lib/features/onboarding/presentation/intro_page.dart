import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../app/theme/app_theme.dart';
import '../../../app/theme/tokens.dart';
import '../../../core/auth/session_cubit.dart';
import '../../../core/l10n/l10n.dart';
import '../../../shared/motion/motion.dart';
import 'intro_art.dart';
import 'language_switch.dart';

/// Three slides about the app, the first time it opens on a phone: live
/// lessons, following progress, and everything in one place. Swipe or
/// "Next" through them, or skip; the language can be changed here before
/// anything else. Once seen (or skipped) they never come again, and the
/// router moves on to signing in.
class IntroPage extends StatefulWidget {
  const IntroPage({super.key});

  static const count = 3;

  @override
  State<IntroPage> createState() => _IntroPageState();
}

class _IntroPageState extends State<IntroPage> {
  final _pages = PageController();
  int _at = 0;

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  // The session remembers it; the router then leaves for the welcome screen.
  void _done() => unawaited(context.read<SessionCubit>().introSeen());

  void _next() {
    if (_at == IntroPage.count - 1) return _done();
    if (context.calm) {
      _pages.jumpToPage(_at + 1);
    } else {
      unawaited(_pages.animateToPage(_at + 1, duration: const Duration(milliseconds: 380), curve: Curves.easeOutCubic));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final last = _at == IntroPage.count - 1;
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsetsDirectional.fromSTEB(16, 12, 12, 0),
              child: Row(
                children: [
                  const LanguageSwitch(),
                  const Spacer(),
                  TextButton(
                    key: const Key('intro-skip'),
                    onPressed: _done,
                    style: TextButton.styleFrom(foregroundColor: context.colors.muted, minimumSize: const Size(48, 48)),
                    child: Text(l.introSkip),
                  ),
                ],
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _pages,
                itemCount: IntroPage.count,
                onPageChanged: (i) => setState(() => _at = i),
                itemBuilder: (context, i) => _Slide(index: i, pages: _pages),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(Space.xl, Space.s, Space.xl, 28),
              child: Column(
                children: [
                  _Dots(at: _at),
                  const Gap(Space.l),
                  PressScale(
                    child: SizedBox(
                      width: double.infinity,
                      height: 56,
                      child: FilledButton(
                        key: const Key('intro-next'),
                        onPressed: PressScale.withPress(context, _next),
                        child: AnimatedSwitcher(
                          duration: context.calm ? Duration.zero : const Duration(milliseconds: 220),
                          child: Row(
                            key: ValueKey(last),
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(last ? l.introStart : l.introNext, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                              const Gap(Space.s),
                              const Icon(Icons.arrow_forward_rounded, size: 20),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// One slide: its picture, scaled to the room there is and moving a little
/// behind the swipe, then its title and words.
class _Slide extends StatelessWidget {
  const _Slide({required this.index, required this.pages});

  final int index;
  final PageController pages;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final (title, body) = switch (index) {
      0 => (l.intro1Title, l.intro1Body),
      1 => (l.intro2Title, l.intro2Body),
      _ => (l.intro3Title, l.intro3Body),
    };
    final art = AnimatedBuilder(
      animation: pages,
      child: RepaintBoundary(child: IntroArt(index)),
      builder: (context, child) {
        if (context.calm || !pages.hasClients || !pages.position.haveDimensions) return child!;
        final away = (pages.page ?? index.toDouble()) - index;
        final rtl = Directionality.of(context) == TextDirection.rtl;
        return Opacity(
          opacity: (1 - away.abs() * 0.7).clamp(0.0, 1.0),
          child: Transform.translate(offset: Offset(away * 90 * (rtl ? -1 : 1), 0), child: child),
        );
      },
    );
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Flexible(
          child: Padding(
            padding: const EdgeInsets.only(top: 6),
            child: ExcludeSemantics(
              child: ConstrainedBox(
                constraints: BoxConstraints.loose(IntroArt.size),
                child: FittedBox(child: art),
              ),
            ),
          ),
        ),
        const Gap(Space.s),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: Space.xxl),
          child: Column(
            children: [
              Semantics(
                header: true,
                child: Text(
                  title,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontFamily: context.shape.headingFont,
                    fontSize: 26,
                    height: 1.35,
                    fontWeight: FontWeight.w700,
                    color: context.colors.ink,
                  ),
                ),
              ).appearAfter(context, 200),
              const Gap(10),
              Text(
                body,
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 16, height: 1.65, color: context.colors.muted),
              ).appearAfter(context, 280),
            ],
          ),
        ),
      ],
    );
  }
}

extension on Widget {
  Widget appearAfter(BuildContext context, int ms) => context.calm
      ? this
      : animate(delay: Duration(milliseconds: ms))
            .fadeIn(duration: const Duration(milliseconds: 450))
            .moveY(begin: 16, end: 0, duration: const Duration(milliseconds: 450), curve: Curves.easeOutCubic);
}

/// Where the reader is among the slides: the current one long and in the
/// brand's colour. Read aloud as "slide 2 of 3".
class _Dots extends StatelessWidget {
  const _Dots({required this.at});

  final int at;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Semantics(
      liveRegion: true,
      label: context.l10n.introSlide(at + 1, IntroPage.count),
      excludeSemantics: true,
      child: SizedBox(
        height: 24,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (var i = 0; i < IntroPage.count; i++)
              AnimatedContainer(
                duration: context.calm ? Duration.zero : const Duration(milliseconds: 300),
                curve: Curves.easeOutBack,
                margin: const EdgeInsets.symmetric(horizontal: 4),
                width: i == at ? 24 : 8,
                height: 8,
                decoration: BoxDecoration(color: i == at ? c.brand : c.line, borderRadius: BorderRadius.circular(4)),
              ),
          ],
        ),
      ),
    );
  }
}
