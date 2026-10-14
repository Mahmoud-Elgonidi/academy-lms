import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/router/areas.dart';
import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../journey/data/journey_repository.dart';
import '../../../journey/domain/journey.dart';
import '../../../notifications/presentation/widgets/notification_bell.dart';
import '../widgets/more_rows.dart';

/// "More" for a student: the kids' list as it was; middle school's card of
/// who they are and where they stand, four tiles (homework with what is
/// open, levels, creatures, recordings) and help; secondary school's who
/// card and two groups (studying: messages, recordings, creatures; help),
/// and signing out.
class StudentMorePage extends StatelessWidget {
  const StudentMorePage({super.key});

  @override
  Widget build(BuildContext context) {
    final area = Area.of(context.watch<SessionCubit>().state);
    if (area == Area.kids) return const _KidsMore();
    return BlocProvider(
      create: (c) => LoadCubit<JourneyMap>(
        () => c.read<JourneyRepository>().map(),
        refreshOn: c.read<Live>().on({Live.homework, Live.practice, Live.lessons}),
      )..load(),
      child: Builder(
        builder: (context) {
          final l = context.l10n;
          final map = context.select<LoadCubit<JourneyMap>, JourneyMap?>((c) => c.state.data);
          return Scaffold(
            body: SafeArea(
              bottom: false,
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(18, Space.m, 18, Space.xs),
                    child: AppHeader(title: l.navMore, actions: const [NotificationBell(framed: true)]),
                  ),
                  Expanded(
                    child: ListView(
                      padding: pagePadding.copyWith(top: Space.m),
                      children: staggered(context, [
                        if (area == Area.middle) ..._middle(context, map) else ..._teen(context, map),
                        const Gap(Space.xl),
                        const SignOutButton(),
                        const AppVersionLine(),
                      ]),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  List<Widget> _middle(BuildContext context, JourneyMap? map) {
    final l = context.l10n;
    final open = map?.missions.where((m) => m.kind == 'homework' && !m.done).length ?? 0;
    return [
      _MiddleWho(map: map),
      const Gap(Space.m),
      GridView.count(
        crossAxisCount: 2,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: Space.m,
        crossAxisSpacing: Space.m,
        childAspectRatio: 1.25,
        children: [
          _Tile(
            key: const Key('more-homework'),
            icon: Icons.edit_outlined,
            tone: Tone.attention,
            label: l.navMyHomework,
            count: open,
            onTap: () => context.go('/middle/more/homework'),
          ),
          _Tile(
            key: const Key('more-levels'),
            icon: Icons.bar_chart_rounded,
            tone: Tone.brand,
            label: l.journeyLevels,
            onTap: () => context.go('/middle/journey/levels'),
          ),
          _Tile(
            key: const Key('more-creatures'),
            icon: Icons.pets_outlined,
            tone: Tone.info,
            label: l.gamesAlbum,
            onTap: () => context.go('/middle/journey/album'),
          ),
          _Tile(
            key: const Key('more-recordings'),
            icon: Icons.videocam_outlined,
            tone: Tone.neutral,
            label: l.recordingsMine,
            onTap: () => context.go('/middle/more/recordings'),
          ),
        ],
      ),
      const Gap(Space.m),
      MenuGroup(
        children: [
          MenuRow(
            key: const Key('more-help'),
            icon: Icons.help_outline_rounded,
            label: l.helpTitle,
            onTap: () => context.push('/account/help'),
          ),
          MenuRow(
            key: const Key('more-tell-us'),
            icon: Icons.flag_outlined,
            label: l.tellUsNavMiddle,
            onTap: () => context.push('/account/tell-us'),
          ),
        ],
      ),
    ];
  }

  List<Widget> _teen(BuildContext context, JourneyMap? map) {
    final l = context.l10n;
    final games = map?.games;
    return [
      MoreWho(line: l.yourAccount, onTap: () => context.push('/account')),
      MenuGroup(
        title: l.moreStudy,
        children: [
          MessagesRow(at: '/teen/more/messages', label: l.messagesStudentTitle),
          MenuRow(
            key: const Key('more-recordings'),
            icon: Icons.videocam_outlined,
            label: l.recordingsTitle,
            onTap: () => context.go('/teen/more/recordings'),
          ),
          if (games != null)
            MenuRow(
              key: const Key('more-creatures'),
              icon: Icons.pets_outlined,
              label: l.gamesAlbum,
              value: l.gamesOf(games.found, games.total),
              onTap: () => context.go('/teen/journey/album'),
            ),
        ],
      ),
      MenuGroup(
        title: l.moreHelp,
        children: [
          MenuRow(
            key: const Key('more-help'),
            icon: Icons.help_outline_rounded,
            label: l.helpTitle,
            onTap: () => context.go('/teen/more/help'),
          ),
          MenuRow(
            key: const Key('more-tell-us'),
            icon: Icons.flag_outlined,
            label: l.tellUsNavMiddle,
            onTap: () => context.go('/teen/more/tell-us'),
          ),
        ],
      ),
    ];
  }
}

/// Middle school's who card, in the academy's colour: their initial, name,
/// rank and stars, and their account a tap away.
class _MiddleWho extends StatelessWidget {
  const _MiddleWho({required this.map});

  final JourneyMap? map;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final account = context.watch<SessionCubit>().state.current;
    if (account == null) return const SizedBox.shrink();
    final m = map;
    final line = [
      if (m != null && m.rewards && m.stars.rank.label.isNotEmpty) m.stars.rank.label,
      if (m != null && m.rewards) l.journeyStarsCount(m.stars.total),
    ];
    return Material(
      key: const Key('more-who'),
      borderRadius: BorderRadius.circular(context.shape.card + 2),
      clipBehavior: Clip.antiAlias,
      child: Ink(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [c.brandDeep, Color.lerp(c.brandDeep, Colors.black, 0.25)!],
            begin: AlignmentDirectional.topStart,
            end: AlignmentDirectional.bottomEnd,
          ),
        ),
        child: InkWell(
          onTap: PressScale.withPress(context, () => context.push('/account')),
          child: Padding(
            padding: const EdgeInsets.all(Space.l),
            child: Row(
              children: [
                Container(
                  width: 52,
                  height: 52,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: c.brand,
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white24, width: 2),
                  ),
                  child: Text(
                    givenName(account.userName).characters.first,
                    style: TextStyle(fontFamily: context.shape.headingFont, color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22),
                  ),
                ),
                const Gap(Space.m),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(account.userName, style: context.type.cardTitle.copyWith(color: Colors.white, fontSize: 17)),
                      if (line.isNotEmpty)
                        Text(line.join(' · '), style: context.type.caption.copyWith(color: Colors.white.withValues(alpha: 0.8))),
                    ],
                  ),
                ),
                Text(
                  l.meTitle,
                  style: context.type.body.copyWith(color: c.reward, fontWeight: FontWeight.w700),
                ),
                Icon(Icons.chevron_right_rounded, color: Colors.white.withValues(alpha: 0.8)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// One of middle school's tiles: an icon on its colour, what it is, a count
/// on its corner when something waits.
class _Tile extends StatelessWidget {
  const _Tile({required this.icon, required this.tone, required this.label, required this.onTap, this.count = 0, super.key});

  final IconData icon;
  final Tone tone;
  final String label;
  final VoidCallback onTap;
  final int count;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return PressScale(
      child: Material(
        color: c.card,
        shape: RoundedRectangleBorder(
          borderRadius: context.shape.cardRadius,
          side: BorderSide(color: c.line),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: PressScale.withPress(context, onTap),
          child: Padding(
            padding: const EdgeInsets.all(Space.l),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Badge(
                  isLabelVisible: count > 0,
                  label: Text('$count'),
                  child: IconBadge(icon, tone: tone, size: 48),
                ),
                const Spacer(),
                Text(label, style: context.type.cardTitle),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// A child's More, as it has been: their homework, creatures, sticker album,
/// telling a grown-up, and their account.
class _KidsMore extends StatelessWidget {
  const _KidsMore();

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final area = Area.of(context.watch<SessionCubit>().state);
    final kids = area == Area.kids;
    final base = area.prefix;
    final items = <(Key, IconData, String, VoidCallback)>[
      (
        const Key('more-homework'),
        Icons.assignment_outlined,
        kids ? l.navMyHomework : l.navHomework,
        () => context.go('$base/more/homework'),
      ),
      (const Key('more-creatures'), Icons.pets_outlined, l.gamesAlbum, () => context.go('$base/journey/album')),
      if (kids) (const Key('more-stickers'), Icons.auto_awesome_outlined, l.album, () => context.go('$base/more/stickers')),
      if (!kids) (const Key('more-help'), Icons.support_agent_outlined, l.helpTitle, () => context.push('/account/help')),
      (
        const Key('more-tell-us'),
        Icons.shield_outlined,
        kids ? l.tellUsNavKids : l.tellUsNavMiddle,
        () => context.push('/account/tell-us'),
      ),
      (const Key('more-account'), Icons.person_outline_rounded, l.meTitle, () => context.push('/account')),
    ];
    return Scaffold(
      appBar: AppBar(title: Text(l.navMore)),
      body: ListView(
        padding: pagePadding,
        children: [
          LmsCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                for (final (i, (key, icon, label, onTap)) in items.indexed) ...[
                  if (i > 0) const Divider(height: 1),
                  ListTile(
                    key: key,
                    leading: Icon(icon, color: context.colors.brand),
                    title: Text(label, style: kids ? context.text.titleMedium : context.text.titleSmall),
                    trailing: const Icon(Icons.chevron_right_rounded),
                    minVerticalPadding: kids ? 16 : null,
                    onTap: onTap,
                  ),
                ],
              ],
            ),
          ).appear(context, 1),
        ],
      ),
    );
  }
}
