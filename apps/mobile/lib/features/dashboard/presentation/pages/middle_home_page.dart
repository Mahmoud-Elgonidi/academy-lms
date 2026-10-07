import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/home_loader.dart';
import '../widgets/badge_tile.dart';
import '../widgets/next_lesson_card.dart';

/// A middle-school student's home: rank and points toward the next one,
/// the streak, the world and stage they are on, badges, and homework.
class MiddleHomePage extends StatelessWidget {
  const MiddleHomePage({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<StudentHome>(c.read<HomeLoader>().studentHome)..load(),
    child: Scaffold(
      body: SafeArea(
        child: LoadView<StudentHome>(builder: (context, home) => _MiddleHome(home: home)),
      ),
    ),
  );
}

class _MiddleHome extends StatelessWidget {
  const _MiddleHome({required this.home});

  final StudentHome home;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final j = home.journey;
    final name = context.select<SessionCubit, String>((s) => s.state.current?.userName ?? '');
    final course = j.courses.firstOrNull;
    final world = course?.currentWorld;
    final hereIndex = world == null ? -1 : world.lessons.indexWhere((s) => s.state == 'here');
    final earned = j.badges.where((b) => b.earned).length;
    final next = j.rank.next;
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        const SizedBox(height: 4),
        LmsCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 56,
                    height: 56,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(color: c.brand, borderRadius: BorderRadius.circular(16)),
                    child: Text(
                      '${course?.level?.index ?? 1}',
                      style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w800),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(l.levelRank(course?.level?.index ?? 1, j.rank.label), style: context.text.bodySmall),
                        Text(name.split(' ').first, style: context.text.headlineSmall),
                      ],
                    ),
                  ),
                  GestureDetector(onTap: () => context.push('/account'), child: InitialAvatar(name, size: 40)),
                ],
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(child: Text(next == null ? l.topRank : l.pointsTo(j.rank.nextLabel ?? ''), style: context.text.titleSmall)),
                  Text(
                    next == null ? '${j.points}' : '${j.points} / $next',
                    textDirection: TextDirection.ltr,
                    style: context.text.titleSmall?.copyWith(color: c.muted),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              ProgressBar(j.rank.progress / 100, colour: c.reward, height: 12),
              if (next != null) ...[const SizedBox(height: 6), Text(l.pointsLeft(next - j.points), style: context.text.bodySmall)],
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                children: [
                  Pill(l.streak(j.streak), tone: Tone.attention, icon: Icons.local_fire_department_rounded),
                  Pill('${j.stars}', tone: Tone.reward, icon: Icons.star_rounded),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        if (world != null)
          LmsCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.worldStage(world.index, world.title), style: context.text.labelMedium?.copyWith(fontSize: 13)),
                const SizedBox(height: 4),
                Text(l.stageOf(hereIndex < 0 ? world.done : hereIndex + 1, world.lessons.length), style: context.text.bodySmall),
                const SizedBox(height: 6),
                Text(course?.next.firstOrNull?.title ?? world.title, style: context.text.titleLarge),
                const SizedBox(height: 10),
                ProgressBar(world.percent / 100),
                if (home.next != null) ...[
                  const SizedBox(height: 10),
                  Text('${l.nextLesson}: ${home.next!.startsAt.when(context)} · ${home.next!.withName}', style: context.text.bodySmall),
                ],
              ],
            ),
          ),
        if (home.next?.joinOpen ?? false) ...[const SizedBox(height: 12), NextLessonCard(lesson: home.next)],
        const SizedBox(height: 12),
        SectionLabel(
          l.navBadges,
          trailing: TextButton(onPressed: () => context.go('/middle/badges'), child: Text(l.badgesCount(earned, j.badges.length))),
        ),
        LmsCard(
          child: Wrap(
            alignment: WrapAlignment.spaceAround,
            spacing: 8,
            runSpacing: 12,
            children: [for (final b in j.badges.take(6)) SizedBox(width: 84, child: BadgeTile(badge: b))],
          ),
        ),
        if (home.homework != null) ...[
          const SizedBox(height: 12),
          LmsCard(
            onTap: () => context.go('/middle/homework'),
            child: Row(
              children: [
                Icon(Icons.assignment_outlined, color: c.brand),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(l.homeworkLine(home.homework!.title), style: context.text.titleSmall),
                      if (home.homework!.dueAt != null)
                        Text(l.dueBy(home.homework!.dueAt!.day(context.locale)), style: context.text.bodySmall),
                    ],
                  ),
                ),
                FilledButton(
                  style: FilledButton.styleFrom(minimumSize: const Size(64, 40)),
                  onPressed: () => context.go('/middle/homework'),
                  child: Text(l.handIn),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}
