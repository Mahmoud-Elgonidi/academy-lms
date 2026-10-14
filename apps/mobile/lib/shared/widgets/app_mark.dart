import 'dart:math' as math;

import 'package:flutter/material.dart';

/// The product's own green: the launcher icon, the phone's launch screen
/// and the first frames before any academy is open (never an academy's
/// brand, which comes from its branding).
const productGreen = Color(0xFF0D735E);

/// The app's mark (tool/icon/mark.svg): an A reaching a gold star, drawn
/// from the same paths, so the phone's launch screen and the app's first
/// screen show the same picture. [star] scales the star and [turn] turns it
/// in quarter turns (one lands where it started: the star has four points).
class AppMark extends StatelessWidget {
  const AppMark({this.size = 144, this.star = 1, this.turn = 0, super.key});

  final double size;
  final double star;
  final double turn;

  @override
  Widget build(BuildContext context) => SizedBox.square(
    dimension: size,
    child: CustomPaint(
      painter: _MarkPainter(star: star, turn: turn),
    ),
  );
}

class _MarkPainter extends CustomPainter {
  _MarkPainter({required this.star, required this.turn});

  final double star;
  final double turn;

  // The mark's paths, in its 1024 square.
  static final _legs = Path()
    ..moveTo(321, 812)
    ..lineTo(512, 388)
    ..lineTo(703, 812);
  static final _bar = Path()
    ..moveTo(409, 662)
    ..quadraticBezierTo(512, 706, 615, 662);
  static final _star = Path()
    ..moveTo(512, 150)
    ..quadraticBezierTo(529, 239, 618, 256)
    ..quadraticBezierTo(529, 273, 512, 362)
    ..quadraticBezierTo(495, 273, 406, 256)
    ..quadraticBezierTo(495, 239, 512, 150)
    ..close();

  static const _halo = RadialGradient(colors: [Color(0x8CFFE7A3), Color(0x00FFE7A3)]);
  static const _chalk = LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Colors.white, Color(0xFFE3F4EE)]);
  static const _gold = LinearGradient(
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
    colors: [Color(0xFFFFD873), Color(0xFFF2A825)],
  );

  @override
  void paint(Canvas canvas, Size size) {
    canvas.save();
    canvas.scale(size.width / 1024);

    const glow = Offset(512, 260);
    canvas.drawCircle(glow, 207, Paint()..shader = _halo.createShader(Rect.fromCircle(center: glow, radius: 207)));

    Paint stroke(double width) => Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = width
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;

    // The A's soft shadow under it, then the A in chalk.
    const shadow = Color.fromRGBO(0, 0, 0, 0.32);
    canvas.save();
    canvas.translate(0, 20);
    for (final (path, width) in [(_legs, 112.0), (_bar, 83.0)]) {
      canvas.drawPath(
        path,
        stroke(width)
          ..color = shadow
          ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 16),
      );
    }
    canvas.restore();
    final chalk = _chalk.createShader(const Rect.fromLTRB(0, 380, 1024, 870));
    canvas.drawPath(_legs, stroke(112)..shader = chalk);
    canvas.drawPath(_bar, stroke(83)..shader = chalk);

    // The star, about its own middle.
    canvas.translate(512, 256);
    canvas.rotate(turn * math.pi / 2);
    canvas.scale(star);
    canvas.translate(-512, -256);
    canvas.drawPath(_star, Paint()..shader = _gold.createShader(const Rect.fromLTRB(406, 150, 618, 362)));
    canvas.restore();
  }

  @override
  bool shouldRepaint(_MarkPainter old) => old.star != star || old.turn != turn;
}
