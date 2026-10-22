/// How a reader writes a clock: 12 hours with the day's half (5:00 م,
/// 5:00 PM) or 24 hours (17:00). The API's `time_format`.
enum TimeFormat {
  h12,
  h24;

  /// `h12` or `h24` as the API writes it; anything else is null.
  static TimeFormat? parse(Object? value) => switch (value) {
    'h12' => h12,
    'h24' => h24,
    _ => null,
  };
}

/// The one place the app decides how a clock is written: the open account's
/// way (their own choice, else their academy's), 12 hours before anyone
/// signs in. The app sets it from the session each time it rebuilds for a
/// new one (`app.dart`, as it sets `Juice.sounds`); `LocalTimeFormats` and
/// `wallClock` read it, and the app's `MediaQuery` carries it to Flutter's
/// own time pickers and `TimeOfDay.format`. The API writes its `label`s the
/// same way by itself.
abstract final class ClockStyle {
  static TimeFormat format = TimeFormat.h12;

  static bool get use24 => format == TimeFormat.h24;
}
