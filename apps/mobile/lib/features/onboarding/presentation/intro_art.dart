import 'dart:ui' show PathMetric;

import 'package:flutter/material.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../shared/motion/motion.dart';

/// The picture over each slide about the app (our own drawing, from the
/// approved design): a live lesson, a road to a goal, and the next lesson,
/// homework and a paid invoice coming in one under the other. Each part
/// comes in after the one before it when the slide is first shown; the
/// little loops (the live dot, the voice bars, the bell) go on while it is
/// on screen. Under less motion everything simply stands.
///
/// The parts sit where the design put them on a 310 x 340 box (left and top
/// as drawn, the same in both languages); the slide scales the whole box to
/// the room it has.
class IntroArt extends StatelessWidget {
  const IntroArt(this.index, {super.key});

  final int index;

  static const size = Size(310, 340);

  @override
  Widget build(BuildContext context) => MediaQuery.withNoTextScaling(
    child: SizedBox.fromSize(
      size: size,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned(left: 25, top: 30, child: _Disc().pop(context, 0)),
          ...switch (index) {
            0 => _live(context),
            1 => _road(context),
            _ => _together(context),
          },
        ],
      ),
    ),
  );

  List<Widget> _live(BuildContext context) {
    final c = context.colors;
    return [
      Positioned(
        left: 35,
        top: 72,
        child: Container(
          width: 240,
          height: 170,
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            color: c.brand,
            borderRadius: BorderRadius.circular(24),
            boxShadow: [BoxShadow(color: c.brandDeep.withValues(alpha: 0.28), blurRadius: 36, offset: const Offset(0, 18))],
          ),
          child: Stack(
            children: [
              CustomPaint(
                size: const Size(240, 170),
                painter: _Teacher(board: c.brandDeep, lines: c.brandSoft),
              ),
              Positioned(left: 14, bottom: 14, child: _VoiceBars()),
            ],
          ),
        ).pop(context, 2),
      ),
      Positioned(
        left: 48,
        top: 86,
        child: Container(
          height: 28,
          padding: const EdgeInsets.symmetric(horizontal: 11),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 8,
                height: 8,
                decoration: const BoxDecoration(color: _red, shape: BoxShape.circle),
              ).blink(context),
              const SizedBox(width: 6),
              Text(
                context.l10n.introLive,
                style: const TextStyle(color: Color(0xFFB91C1C), fontSize: 13, fontWeight: FontWeight.w700, height: 1),
              ),
            ],
          ),
        ).pop(context, 5),
      ),
      Positioned(
        left: 196,
        top: 206,
        child: Container(
          width: 92,
          height: 76,
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            color: c.infoSoft,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: Colors.white, width: 3),
            boxShadow: const [BoxShadow(color: Color(0x2E1C1917), blurRadius: 20, offset: Offset(0, 10))],
          ),
          child: CustomPaint(painter: _Student()),
        ).pop(context, 4),
      ),
      Positioned(
        left: 70,
        top: 262,
        child: Container(
          height: 46,
          padding: const EdgeInsets.symmetric(horizontal: 8),
          decoration: BoxDecoration(color: const Color(0xFF1C1917), borderRadius: BorderRadius.circular(23)),
          child: const Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              _Control(Icons.mic_none_rounded),
              SizedBox(width: 8),
              _Control(Icons.videocam_outlined),
              SizedBox(width: 8),
              _Control(Icons.call_end_rounded, wide: true, colour: _red),
            ],
          ),
        ).rise(context, 6),
      ),
      Positioned(
        left: 4,
        top: 40,
        child: _Bubble(icon: Icons.star_rounded, colour: c.reward).pop(context, 6).float(context, 0),
      ),
      Positioned(
        left: 258,
        top: 30,
        child: _Bubble(icon: Icons.chat_bubble_outline_rounded, colour: c.brand).pop(context, 7).float(context, 4),
      ),
    ];
  }

  List<Widget> _road(BuildContext context) {
    final c = context.colors;
    Widget done() => Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: c.brand,
        shape: BoxShape.circle,
        boxShadow: [BoxShadow(color: c.brandDeep.withValues(alpha: 0.3), blurRadius: 14, offset: const Offset(0, 6))],
      ),
      child: const Icon(Icons.check_rounded, color: Colors.white, size: 22),
    );
    return [
      Positioned.fill(
        child: _Road(under: Colors.white, over: c.brand),
      ),
      Positioned(left: 50, top: 260, child: done().pop(context, 3)),
      Positioned(left: 130, top: 170, child: done().pop(context, 5)),
      Positioned(
        left: 164,
        top: 69,
        child: SizedBox.square(
          dimension: 52,
          child: Stack(
            children: [
              Positioned.fill(child: _Ring(colour: c.brand)),
              Positioned.fill(
                child: Container(
                  decoration: BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    border: Border.all(color: c.brand, width: 4),
                  ),
                  child: Icon(Icons.star_rounded, color: c.reward, size: 24),
                ).pop(context, 7),
              ),
            ],
          ),
        ),
      ),
      Positioned(
        left: 223,
        top: 22,
        child: Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            color: c.reward,
            shape: BoxShape.circle,
            boxShadow: const [BoxShadow(color: Color(0x4DB45309), blurRadius: 14, offset: Offset(0, 6))],
          ),
          child: const Icon(Icons.flag_rounded, color: Color(0xFF78350F), size: 22),
        ).pop(context, 9),
      ),
      Positioned(
        left: 14,
        top: 34,
        child: Container(
          width: 132,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: const [BoxShadow(color: Color(0x1A1C1917), blurRadius: 22, offset: Offset(0, 10))],
          ),
          child: Row(
            children: [
              Icon(Icons.star_rounded, color: c.reward, size: 20),
              const SizedBox(width: 8),
              Expanded(
                child: _Fill(colour: c.brand, track: c.line),
              ),
            ],
          ),
        ).rise(context, 6),
      ),
    ];
  }

  List<Widget> _together(BuildContext context) {
    final c = context.colors;
    final l = context.l10n;
    return [
      Positioned(
        left: 240,
        top: 14,
        child: SizedBox.square(
          dimension: 52,
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              Positioned.fill(
                child: Container(
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    boxShadow: [BoxShadow(color: Color(0x1F1C1917), blurRadius: 18, offset: Offset(0, 8))],
                  ),
                  child: const Icon(Icons.notifications_none_rounded, color: Color(0xFF1C1917), size: 26).ring(context),
                ),
              ),
              Positioned(
                top: 8,
                right: 9,
                child: Container(
                  width: 11,
                  height: 11,
                  decoration: BoxDecoration(
                    color: _red,
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 2),
                  ),
                ).pop(context, 9),
              ),
            ],
          ),
        ).pop(context, 8),
      ),
      Positioned(
        left: 24,
        top: 78,
        child: _Row(
          icon: Icons.calendar_month_outlined,
          tint: c.brandSoft,
          ink: c.brandDeep,
          title: l.nextLesson,
          subtitle: l.introLessonWhen,
        ).slideIn(context, 2),
      ),
      Positioned(
        left: 36,
        top: 156,
        child: _Row(
          icon: Icons.edit_outlined,
          tint: c.attentionSoft,
          ink: c.attentionInk,
          title: l.introHomework,
          subtitle: l.introHomeworkWhen,
        ).slideIn(context, 5),
      ),
      Positioned(
        left: 24,
        top: 234,
        child: _Row(
          icon: Icons.receipt_long_outlined,
          tint: c.brandSoft,
          ink: c.brandDeep,
          title: l.introPaid,
          subtitle: l.introThanks,
          end: Container(
            width: 28,
            height: 28,
            decoration: BoxDecoration(color: c.brand, shape: BoxShape.circle),
            child: const Icon(Icons.check_rounded, color: Colors.white, size: 18),
          ).pop(context, 9),
        ).slideIn(context, 7),
      ),
    ];
  }
}

const _red = Color(0xFFDC2626);

/// The design's steps: 0.06 s to 0.9 s after the slide comes.
Duration _step(int i) => Duration(milliseconds: const [0, 60, 120, 200, 280, 360, 460, 580, 720, 900][i.clamp(0, 9)]);

extension _Moves on Widget {
  /// Grows from small with a little overshoot.
  Widget pop(BuildContext context, int step) => context.calm
      ? this
      : animate(delay: _step(step))
            .fadeIn(duration: const Duration(milliseconds: 300))
            .scaleXY(begin: 0.3, end: 1, duration: const Duration(milliseconds: 550), curve: Curves.easeOutBack);

  /// Rises a little into place.
  Widget rise(BuildContext context, int step) => context.calm
      ? this
      : animate(delay: _step(step))
            .fadeIn(duration: const Duration(milliseconds: 450))
            .moveY(begin: 16, end: 0, duration: const Duration(milliseconds: 450), curve: Curves.easeOutCubic);

  /// Comes in from the side the reading starts on.
  Widget slideIn(BuildContext context, int step) {
    if (context.calm) return this;
    final from = Directionality.of(context) == TextDirection.rtl ? 44.0 : -44.0;
    return animate(delay: _step(step))
        .fadeIn(duration: const Duration(milliseconds: 380))
        .moveX(begin: from, end: 0, duration: const Duration(milliseconds: 380), curve: Curves.easeOutCubic);
  }

  /// Floats up and down, on and on.
  Widget float(BuildContext context, int step) => context.calm
      ? this
      : animate(
          delay: _step(step),
          onPlay: (c) => c.repeat(reverse: true),
        ).moveY(begin: 0, end: -9, duration: const Duration(milliseconds: 1600), curve: Curves.easeInOut);

  /// Fades in and out, on and on (the live dot).
  Widget blink(BuildContext context) => context.calm
      ? this
      : animate(onPlay: (c) => c.repeat(reverse: true))
            .fade(begin: 1, end: 0.25, duration: const Duration(milliseconds: 550), curve: Curves.easeInOut);

  /// Rings now and then, like a bell with news.
  Widget ring(BuildContext context) => context.calm
      ? this
      : animate(delay: const Duration(milliseconds: 900), onPlay: (c) => c.repeat()).custom(
          duration: const Duration(milliseconds: 2600),
          builder: (context, v, child) {
            const swings = [0.0, 0.28, -0.24, 0.17, -0.1, 0.05, 0.0];
            final x = (v / 0.4).clamp(0.0, 1.0) * (swings.length - 1);
            final i = x.floor().clamp(0, swings.length - 2);
            final angle = swings[i] + (swings[i + 1] - swings[i]) * (x - i);
            return Transform.rotate(angle: angle, alignment: const Alignment(0, -0.76), child: child);
          },
        );
}

/// The soft round behind every picture.
class _Disc extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    width: 260,
    height: 260,
    decoration: BoxDecoration(color: context.colors.brandSoft, shape: BoxShape.circle),
  );
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.icon, required this.colour});

  final IconData icon;
  final Color colour;

  @override
  Widget build(BuildContext context) => Container(
    width: 46,
    height: 46,
    decoration: const BoxDecoration(
      color: Colors.white,
      shape: BoxShape.circle,
      boxShadow: [BoxShadow(color: Color(0x1F1C1917), blurRadius: 18, offset: Offset(0, 8))],
    ),
    child: Icon(icon, color: colour, size: 22),
  );
}

class _Control extends StatelessWidget {
  const _Control(this.icon, {this.wide = false, this.colour = const Color(0xFF44403C)});

  final IconData icon;
  final bool wide;
  final Color colour;

  @override
  Widget build(BuildContext context) => Container(
    width: wide ? 44 : 32,
    height: 32,
    decoration: BoxDecoration(color: colour, borderRadius: BorderRadius.circular(16)),
    child: Icon(icon, color: Colors.white, size: wide ? 18 : 16),
  );
}

/// Four bars going up and down: someone is talking.
class _VoiceBars extends StatelessWidget {
  @override
  Widget build(BuildContext context) => SizedBox(
    height: 18,
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        for (final (i, wait) in const [0, 200, 400, 100].indexed) ...[
          if (i > 0) const SizedBox(width: 3),
          Container(
            width: 4,
            height: 18,
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(2)),
          ).voice(context, wait),
        ],
      ],
    ),
  );
}

extension on Widget {
  Widget voice(BuildContext context, int wait) => context.calm
      ? Transform.scale(scaleY: 0.6, alignment: Alignment.bottomCenter, child: this)
      : animate(
          delay: Duration(milliseconds: wait),
          onPlay: (c) => c.repeat(reverse: true),
        ).scaleY(
          begin: 0.35,
          end: 1,
          alignment: Alignment.bottomCenter,
          duration: const Duration(milliseconds: 450),
          curve: Curves.easeInOut,
        );
}

/// A ring going out from the goal, again and again.
class _Ring extends StatelessWidget {
  const _Ring({required this.colour});

  final Color colour;

  @override
  Widget build(BuildContext context) {
    if (context.calm) return const SizedBox.shrink();
    return Container(
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: colour, width: 3),
          ),
        )
        .animate(delay: _step(9), onPlay: (c) => c.repeat())
        .scaleXY(begin: 0.85, end: 1.9, duration: const Duration(milliseconds: 1800), curve: Curves.easeOut)
        .fade(begin: 0.7, end: 0, duration: const Duration(milliseconds: 1800), curve: Curves.easeOut);
  }
}

/// A progress bar filling to most of the way.
class _Fill extends StatelessWidget {
  const _Fill({required this.colour, required this.track});

  final Color colour;
  final Color track;

  @override
  Widget build(BuildContext context) {
    final bar = Container(
      height: 8,
      decoration: BoxDecoration(color: colour, borderRadius: BorderRadius.circular(4)),
    );
    return Container(
      height: 8,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(color: track, borderRadius: BorderRadius.circular(4)),
      alignment: AlignmentDirectional.centerStart,
      child: FractionallySizedBox(
        widthFactor: 0.7,
        child: context.calm
            ? bar
            : bar
                  .animate(delay: const Duration(milliseconds: 700))
                  .scaleX(
                    begin: 0,
                    end: 1,
                    alignment: AlignmentDirectional.centerStart.resolve(Directionality.of(context)),
                    duration: const Duration(milliseconds: 1100),
                    curve: Curves.easeOutCubic,
                  ),
      ),
    );
  }
}

/// One thing coming in: an icon on its tint, a title and a line under it.
class _Row extends StatelessWidget {
  const _Row({required this.icon, required this.tint, required this.ink, required this.title, required this.subtitle, this.end});

  final IconData icon;
  final Color tint;
  final Color ink;
  final String title;
  final String subtitle;
  final Widget? end;

  @override
  Widget build(BuildContext context) => Container(
    width: 250,
    height: 66,
    padding: const EdgeInsets.symmetric(horizontal: 14),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      boxShadow: const [BoxShadow(color: Color(0x1A1C1917), blurRadius: 24, offset: Offset(0, 10))],
    ),
    child: Row(
      children: [
        Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(color: tint, borderRadius: BorderRadius.circular(12)),
          child: Icon(icon, color: ink, size: 20),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: Color(0xFF1C1917)),
              ),
              Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 13, color: Color(0xFF57534E)),
              ),
            ],
          ),
        ),
        ?end,
      ],
    ),
  );
}

/// The road to the goal: a wide white way, and the course drawn along it.
class _Road extends StatelessWidget {
  const _Road({required this.under, required this.over});

  final Color under;
  final Color over;

  @override
  Widget build(BuildContext context) {
    if (context.calm) {
      return CustomPaint(
        painter: _RoadPainter(under: under, over: over, drawn: 1),
      );
    }
    return const SizedBox.expand()
        .animate(delay: _step(2))
        .custom(
          duration: const Duration(milliseconds: 1200),
          curve: const Cubic(0.6, 0, 0.3, 1),
          builder: (context, v, _) => CustomPaint(
            painter: _RoadPainter(under: under, over: over, drawn: v),
          ),
        );
  }
}

class _RoadPainter extends CustomPainter {
  _RoadPainter({required this.under, required this.over, required this.drawn});

  final Color under;
  final Color over;
  final double drawn;

  static final _path = Path()
    ..moveTo(70, 280)
    ..cubicTo(190, 280, 220, 220, 150, 190)
    ..cubicTo(80, 160, 100, 105, 190, 95)
    ..cubicTo(245, 89, 255, 62, 245, 44);
  static final List<PathMetric> _metrics = _path.computeMetrics().toList();

  @override
  void paint(Canvas canvas, Size size) {
    Paint line(Color colour, double width) => Paint()
      ..color = colour
      ..style = PaintingStyle.stroke
      ..strokeWidth = width
      ..strokeCap = StrokeCap.round;
    canvas.drawPath(_path, line(under, 22));
    if (drawn <= 0) return;
    final course = Path();
    for (final m in _metrics) {
      course.addPath(m.extractPath(0, m.length * drawn), Offset.zero);
    }
    canvas.drawPath(course, line(over, 6));
  }

  @override
  bool shouldRepaint(_RoadPainter old) => old.drawn != drawn || old.under != under || old.over != over;
}

/// The teacher on the call, by a little board.
class _Teacher extends CustomPainter {
  _Teacher({required this.board, required this.lines});

  final Color board;
  final Color lines;

  @override
  void paint(Canvas canvas, Size size) {
    canvas.drawRRect(RRect.fromLTRBR(152, 24, 218, 70, const Radius.circular(8)), Paint()..color = board);
    final chalk = Paint()
      ..color = lines
      ..strokeWidth = 4
      ..strokeCap = StrokeCap.round;
    canvas
      ..drawLine(const Offset(162, 40), const Offset(204, 40), chalk)
      ..drawLine(const Offset(162, 53), const Offset(190, 53), chalk);
    canvas.drawPath(
      Path()
        ..moveTo(52, 170)
        ..cubicTo(56, 130, 80, 110, 110, 110)
        ..cubicTo(140, 110, 164, 130, 168, 170)
        ..close(),
      Paint()..color = const Color(0xFFFBBF24),
    );
    _face(canvas, centre: const Offset(110, 76), radius: 27, hairColour: const Color(0xFF44403C), eyes: 10, eyeSize: 2.6, mouth: 2.6);
  }

  @override
  bool shouldRepaint(_Teacher old) => old.board != board || old.lines != lines;
}

/// The student in the corner of the call.
class _Student extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    canvas.drawPath(
      Path()
        ..moveTo(16, 70)
        ..cubicTo(18, 53, 29, 44, 43, 44)
        ..cubicTo(57, 44, 68, 53, 70, 70)
        ..close(),
      Paint()..color = const Color(0xFF60A5FA),
    );
    _face(canvas, centre: const Offset(43, 30), radius: 14, hairColour: const Color(0xFF1C1917), eyes: 0, eyeSize: 0, mouth: 2);
  }

  @override
  bool shouldRepaint(_Student old) => false;
}

/// A round face with hair over the top, eyes [eyes] apart (none at 0) and a smile.
void _face(
  Canvas canvas, {
  required Offset centre,
  required double radius,
  required Color hairColour,
  required double eyes,
  required double eyeSize,
  required double mouth,
}) {
  final r = radius;
  final x = centre.dx;
  final y = centre.dy;
  canvas.drawCircle(centre, r, Paint()..color = const Color(0xFFFDE7D3));
  // Hair: over the head's top, down to just above the eyes.
  final k = r / 27;
  canvas.drawPath(
    Path()
      ..moveTo(x - r, y - 4 * k)
      ..cubicTo(x - r, y - 21 * k, x - 15 * k, y - 32 * k, x, y - 32 * k)
      ..cubicTo(x + 15 * k, y - 32 * k, x + r, y - 21 * k, x + r, y - 4 * k)
      ..cubicTo(x + 21 * k, y - 12 * k, x + 12 * k, y - 16 * k, x, y - 16 * k)
      ..cubicTo(x - 12 * k, y - 16 * k, x - 21 * k, y - 12 * k, x - r, y - 4 * k)
      ..close(),
    Paint()..color = hairColour,
  );
  final ink = Paint()..color = const Color(0xFF1C1917);
  if (eyes > 0) {
    canvas
      ..drawCircle(Offset(x - eyes, y + 3 * k), eyeSize, ink)
      ..drawCircle(Offset(x + eyes, y + 3 * k), eyeSize, ink);
  }
  canvas.drawPath(
    Path()
      ..moveTo(x - 8 * k, y + 13 * k)
      ..quadraticBezierTo(x, y + 19 * k, x + 8 * k, y + 13 * k),
    Paint()
      ..color = const Color(0xFF1C1917)
      ..style = PaintingStyle.stroke
      ..strokeWidth = mouth
      ..strokeCap = StrokeCap.round,
  );
}
