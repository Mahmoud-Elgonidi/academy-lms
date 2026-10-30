import 'package:flutter/material.dart';

/// The lesson room's own neutrals, in one place: a dark stage, as in a video
/// call, whatever the academy's colours (they are the accents only: the
/// speaking ring, a button switched on). Red is the room's own for leaving
/// and recording, so it reads the same in every academy.
abstract final class RoomColors {
  /// Behind everything.
  static const stage = Color(0xFF0F1114);

  /// A tile with no picture yet.
  static const tile = Color(0xFF1E2126);

  /// The floating self view and small tiles.
  static const tileRaised = Color(0xFF2A2E35);

  /// The bars over the picture in landscape.
  static const scrim = Color(0xCC0F1114);

  /// The sheet of more options.
  static const sheet = Color(0xFF1B1E23);

  static const ink = Color(0xFFF4F5F6);
  static const inkSoft = Color(0xFFB4B9C0);

  /// A round button at rest, and one that cannot be pressed.
  static const control = Color(0x29FFFFFF);
  static const controlDisabled = Color(0x12FFFFFF);

  /// Leaving, recording, a microphone or camera off.
  static const red = Color(0xFFE5483D);

  /// The name pill on a tile.
  static const pill = Color(0x99000000);

  static const good = Color(0xFF34D399);
  static const fair = Color(0xFFFBBF24);
}
