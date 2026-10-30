import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../app/theme/app_theme.dart';
import '../../app/theme/stage.dart';
import '../../core/auth/session_cubit.dart';
import '../../core/l10n/l10n.dart';
import '../../core/network/api_problem.dart';
import '../../core/realtime/live.dart';
import '../../core/time/formats.dart';
import '../../core/time/zones.dart';
import '../motion/motion.dart';
import 'minute_tick.dart';
import 'lms.dart';

/// What the app knows of the zones around the open account: the one times
/// are written in, the account's own, the academy's and the phone's, each
/// with its city and, where the API said how far from UTC it is, its time
/// now. Times are never worked out on the phone from a zone's rules: the
/// API's offset ("القاهرة (UTC+2)", `GET timezones`) or the phone's own clock.
class ZoneFacts {
  ZoneFacts._(this._state, this._locale, this._zones);

  /// Null until the profile has loaded.
  static ZoneFacts? of(SessionState state, String locale, [List<Zone> zones = const []]) =>
      state.me == null ? null : ZoneFacts._(state, locale, zones);

  final SessionState _state;
  final String _locale;
  final List<Zone> _zones;

  /// The zone this phone's times come back in.
  String get shown => _state.me!.displayTimezone;

  /// The account's own zone (the academy's while it has none).
  String get account => _state.me!.timezone ?? _state.me!.academyTimezone ?? shown;
  String? get academy => _state.me!.academyTimezone;
  String? get device => _state.deviceTimezone;

  /// The phone's clock differs from the account's.
  bool get mismatch => _state.me!.mismatch;

  Zone? _listed(String zone) => _zones.where((z) => z.id == zone).firstOrNull;

  String? _label(String zone) => switch (zone) {
    _ when zone == shown && _state.me!.timezoneLabel != null => _state.me!.timezoneLabel,
    _ when zone == academy && _state.me!.academyTimezoneLabel != null => _state.me!.academyTimezoneLabel,
    _ => _listed(zone)?.label,
  };

  /// "القاهرة".
  String city(String zone) {
    final label = _label(zone);
    if (label != null) return cityOf(label);
    return zoneName(zone, _locale);
  }

  /// "القاهرة (UTC+2)", or the city alone when no offset is known.
  String label(String zone) => _label(zone) ?? city(zone);

  /// The time now on [zone]'s wall clock, or null when the app cannot say.
  DateTime? now(String zone) {
    if (zone == device) return DateTime.now();
    final offset = utcOffsetOf(_label(zone)) ?? utcOffsetOf(_listed(zone)?.offset);
    if (offset == null) return null;
    final u = DateTime.now().toUtc().add(offset);
    return DateTime(u.year, u.month, u.day, u.hour, u.minute);
  }

  /// "4:52 م" / "16:52" on [zone], the reader's way.
  String? clock(String zone) {
    final t = now(zone);
    return t == null ? null : wallClock('${t.hour}:${t.minute.toString().padLeft(2, '0')}', _locale);
  }
}

/// A small pill in a tab's header: a clock, the city times are shown on and
/// its time, ticking on the minute (the phone's clock; nothing asked of the
/// server). It comes in softly and beats each minute; a dot says the phone's
/// zone is not the account's. A tap opens the zone card. Not in the kids'
/// look, and not before the profile has loaded.
class ZoneChip extends StatelessWidget {
  const ZoneChip({super.key});

  @override
  Widget build(BuildContext context) {
    if (context.isKids) return const SizedBox.shrink();
    final SessionState state;
    try {
      state = context.watch<SessionCubit>().state;
    } on Object {
      return const SizedBox.shrink();
    }
    if (state.stage == Stage.kids) return const SizedBox.shrink();
    final facts = ZoneFacts.of(state, context.locale);
    if (facts == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsetsDirectional.only(end: Space.s),
      child: MinuteTick(builder: (context) => _Pill(facts: facts)),
    ).appear(context, 1);
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.facts});

  final ZoneFacts facts;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final l = context.l10n;
    final city = facts.city(facts.shown);
    final time = facts.clock(facts.shown);
    Widget clock = Text(
      time ?? '',
      key: const Key('zone-chip-time'),
      maxLines: 1,
      style: context.type.label.copyWith(color: c.ink, fontWeight: FontWeight.w700, fontFeatures: const [FontFeature.tabularFigures()]),
    );
    if (!context.calm) {
      // A beat each new minute: the key changes with the time shown.
      clock = clock
          .animate(key: ValueKey(time))
          .scaleXY(begin: 1.14, end: 1, duration: const Duration(milliseconds: 520), curve: Curves.easeOutBack)
          .fadeIn(begin: 0.5, duration: const Duration(milliseconds: 260));
    }
    final said = [l.zoneChip(city, time ?? ''), if (facts.mismatch) l.zoneChipMismatch].join('، ');
    return Semantics(
      button: true,
      label: said,
      excludeSemantics: true,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 128, minHeight: 48),
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Material(
              key: const Key('zone-chip'),
              color: c.card,
              shape: StadiumBorder(side: BorderSide(color: c.line)),
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: () => showZoneSheet(context),
                child: Padding(
                  padding: const EdgeInsetsDirectional.fromSTEB(10, 6, 14, 6),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.schedule_rounded, size: 18, color: c.brand),
                      const SizedBox(width: 6),
                      Flexible(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(city, maxLines: 1, overflow: TextOverflow.ellipsis, style: context.type.label.copyWith(fontSize: 11)),
                            if (time != null) clock,
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            if (facts.mismatch)
              PositionedDirectional(
                top: 2,
                end: 2,
                child: Container(
                  key: const Key('zone-chip-dot'),
                  width: 10,
                  height: 10,
                  decoration: BoxDecoration(
                    color: c.attention,
                    shape: BoxShape.circle,
                    border: Border.all(color: c.card, width: 1.5),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// The zone card: the zone times are shown on; the account's with its time;
/// the academy's and the phone's when they differ, the phone's with "Use my
/// phone's time zone"; and "Change my time zone".
Future<void> showZoneSheet(BuildContext context) =>
    showActionSheet<void>(context, title: context.l10n.timezone, builder: (_) => const ZoneSheet());

class ZoneSheet extends StatefulWidget {
  const ZoneSheet({super.key});

  @override
  State<ZoneSheet> createState() => _ZoneSheetState();
}

class _ZoneSheetState extends State<ZoneSheet> {
  List<Zone> _zones = const [];
  ZoneFacts? _shown;
  bool _busy = false;

  bool _asked = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // The list names and times zones the profile does not (the account's own while the phone differs).
    if (!_asked) {
      _asked = true;
      unawaited(_load());
    }
  }

  Future<void> _load() async {
    try {
      final zones = await context.read<ZoneRepository>().all(context.locale);
      if (mounted) setState(() => _zones = zones);
    } on Exception {
      // The card stands without it: cities by name, no time where none is known.
    }
  }

  Future<void> _run(Future<void> Function() step) async {
    setState(() => _busy = true);
    try {
      await step();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _useDevice() => _run(() async {
    final session = context.read<SessionCubit>();
    final live = context.read<Live>();
    final l = context.l10n;
    final device = ZoneFacts.of(session.state, context.locale, _zones)?.city(session.state.deviceTimezone ?? '') ?? '';
    try {
      await session.usePhoneClock();
      live.catchUp();
      if (mounted) showSnack(context, l.zoneSaved(device));
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    }
  });

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final state = context.watch<SessionCubit>().state;
    // While the profile reloads after a change, the card keeps what it showed.
    final facts = _shown = ZoneFacts.of(state, context.locale, _zones) ?? _shown;
    if (facts == null) return const SizedBox.shrink();
    final account = facts.account;
    final academy = facts.academy;
    final device = facts.device;
    return MinuteTick(
      builder: (context) => SingleChildScrollView(
        padding: const EdgeInsetsDirectional.fromSTEB(Space.xl - 4, 0, Space.xl - 4, Space.xl),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: staggered(context, [
            Text(l.zoneShownIn(facts.city(facts.shown)), key: const Key('zone-shown'), style: context.type.caption),
            const SizedBox(height: Space.m),
            _ZoneLine(key: const Key('zone-yours'), title: l.zoneYours, facts: facts, zone: account, strong: true),
            if (academy != null && academy != account) ...[
              const SizedBox(height: Space.s),
              _ZoneLine(key: const Key('zone-academy'), title: l.zoneAcademy, facts: facts, zone: academy),
            ],
            if (device != null && device != account) ...[
              const SizedBox(height: Space.s),
              _ZoneLine(
                key: const Key('zone-device'),
                title: l.zoneDevice,
                facts: facts,
                zone: device,
                action: FilledButton.tonalIcon(
                  key: const Key('zone-use-device'),
                  onPressed: _busy ? null : _useDevice,
                  icon: const Icon(Icons.smartphone_rounded, size: 18),
                  label: Text(l.zoneUseDevice),
                ),
              ),
            ],
            const SizedBox(height: Space.l),
            OutlinedButton.icon(
              key: const Key('zone-change'),
              onPressed: _busy ? null : () => _run(() => changeZone(context)),
              icon: const Icon(Icons.travel_explore_rounded, size: 18),
              label: Text(l.zoneChange),
            ),
          ]),
        ),
      ),
    );
  }
}

/// One zone on the card: what it is, its city with the offset, and its time now.
class _ZoneLine extends StatelessWidget {
  const _ZoneLine({required this.title, required this.facts, required this.zone, this.strong = false, this.action, super.key});

  final String title;
  final ZoneFacts facts;
  final String zone;
  final bool strong;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final time = facts.clock(zone);
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: strong ? c.brandSoft : c.card,
        borderRadius: BorderRadius.circular(context.shape.card - 2),
        border: Border.all(color: strong ? c.brandSoft : c.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: context.type.label),
                    const SizedBox(height: 2),
                    Text(facts.label(zone), style: context.type.cardTitle),
                  ],
                ),
              ),
              if (time != null) ...[
                const SizedBox(width: Space.s),
                Text(time, style: context.type.number.copyWith(fontSize: 20, color: strong ? c.brandDeep : c.ink)),
              ],
            ],
          ),
          if (action != null) ...[const SizedBox(height: 10), Align(alignment: AlignmentDirectional.centerStart, child: action)],
        ],
      ),
    );
  }
}

/// "Change my time zone": the list of zones to choose from; the one chosen
/// becomes the account's (`PATCH me`), times on this phone follow it, and
/// every open screen reloads. The API's refusal is said in a snack bar.
Future<void> changeZone(BuildContext context) async {
  final session = context.read<SessionCubit>();
  final live = context.read<Live>();
  final l = context.l10n;
  final current = session.state.me?.timezone ?? session.state.timezone;
  final zone = await showActionSheet<Zone>(
    context,
    title: l.zonePick,
    builder: (_) => ZonePicker(current: current),
  );
  if (zone == null || !context.mounted) return;
  try {
    await session.chooseZone(zone.id);
    live.catchUp();
    if (context.mounted) showSnack(context, l.zoneSaved(zone.city));
  } on ApiProblem catch (p) {
    if (context.mounted) showSnack(context, context.problemText(p));
  }
}

/// Every zone from `GET timezones`, under its region, with a search by city,
/// region or offset; a tap answers the zone.
class ZonePicker extends StatefulWidget {
  const ZonePicker({this.current, super.key});

  final String? current;

  @override
  State<ZonePicker> createState() => _ZonePickerState();
}

class _ZonePickerState extends State<ZonePicker> {
  late final Future<List<Zone>> _zones = context.read<ZoneRepository>().all(context.locale);
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final height = MediaQuery.sizeOf(context).height * 0.72;
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: height,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: Space.xl - 4),
              child: TextField(
                key: const Key('zone-search'),
                decoration: InputDecoration(prefixIcon: const Icon(Icons.search_rounded), hintText: l.zoneSearch),
                onChanged: (v) => setState(() => _query = v),
              ),
            ),
            const SizedBox(height: Space.s),
            Expanded(
              child: FutureBuilder<List<Zone>>(
                future: _zones,
                builder: (context, snap) {
                  if (snap.hasError) {
                    return Center(child: Text(context.problemText(snap.error!), textAlign: TextAlign.center));
                  }
                  final all = snap.data;
                  if (all == null) return const Center(child: CircularProgressIndicator());
                  final zones = all.where((z) => z.matches(_query)).toList();
                  if (zones.isEmpty) return Center(child: Text(l.zoneNone, style: context.type.caption));
                  final rows = <Widget>[];
                  String? region;
                  for (final z in zones) {
                    if (z.region != region) {
                      region = z.region;
                      rows.add(
                        Padding(
                          padding: const EdgeInsetsDirectional.fromSTEB(Space.xl - 4, Space.m, Space.xl - 4, Space.xs),
                          child: Semantics(header: true, child: Text(z.region, style: context.type.section)),
                        ),
                      );
                    }
                    final selected = z.id == widget.current;
                    rows.add(
                      ListTile(
                        key: Key('zone-${z.id}'),
                        selected: selected,
                        selectedColor: c.brandDeep,
                        title: Text(z.city),
                        subtitle: selected ? Text(l.zoneCurrent) : null,
                        trailing: Text(z.offset, style: context.type.label, textDirection: TextDirection.ltr),
                        onTap: () => Navigator.of(context).pop(z),
                      ),
                    );
                  }
                  return ListView(children: rows);
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
