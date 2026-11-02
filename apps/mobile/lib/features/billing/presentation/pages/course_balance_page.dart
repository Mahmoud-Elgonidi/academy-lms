import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../roadmap/data/learning_repository.dart';
import '../../data/billing_repository.dart';
import '../../domain/course_balance.dart';
import '../widgets/course_balance_card.dart';

class _Page {
  const _Page(this.balance, this.subject, this.learner);

  final CourseBalance balance;
  final String? subject;
  final String? learner;
}

/// One course's balance on a page of its own (Payments › a course, and the
/// `course_balance` notifications: the next invoice coming, lessons about to
/// expire): the card, then how the balance works in three plain lines.
/// Read again on the payments and lessons signals and when the app comes
/// back to the front.
class CourseBalancePage extends StatefulWidget {
  const CourseBalancePage({required this.enrollmentId, super.key});

  final String enrollmentId;

  @override
  State<CourseBalancePage> createState() => _CourseBalancePageState();
}

class _CourseBalancePageState extends State<CourseBalancePage> {
  late final AppLifecycleListener _resume;
  LoadCubit<_Page>? _cubit;

  @override
  void initState() {
    super.initState();
    _resume = AppLifecycleListener(onResume: () => _cubit?.load());
  }

  @override
  void dispose() {
    _resume.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.balanceTitle)),
      body: BlocProvider(
        create: (c) {
          final billing = c.read<BillingRepository>();
          final learning = c.read<LearningRepository>();
          return _cubit = LoadCubit<_Page>(() async {
            // The course's subject and learner come from the family's courses; the page stands without them.
            final names = learning.students().then<({String? subject, String? learner})>((students) {
              for (final s in students) {
                for (final course in s.courses) {
                  if (course.enrollmentId == widget.enrollmentId) return (subject: course.subject, learner: s.name);
                }
              }
              return (subject: null, learner: null);
            }, onError: (Object _) => (subject: null, learner: null));
            final balance = await billing.courseBalance(widget.enrollmentId);
            final named = await names;
            return _Page(balance, named.subject, named.learner);
          }, refreshOn: c.read<Live>().on({Live.payments, Live.lessons}))..load();
        },
        child: LoadView<_Page>(
          skeleton: ListView(
            padding: pagePadding.copyWith(top: Space.m),
            children: [
              Skeleton(height: 260, radius: context.shape.card),
              const Gap(Space.l),
              const Skeleton(height: 140, radius: 16),
            ],
          ),
          builder: (context, page) => _Body(page: page),
        ),
      ),
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({required this.page});

  final _Page page;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final b = page.balance;
    final title = page.subject ?? b.plan?.name ?? l.balanceTitle;
    return ListView(
      key: const Key('course-balance-page'),
      padding: pagePadding.copyWith(top: Space.m),
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        if (page.learner case final learner? when page.subject != null) ...[Text(learner, style: context.type.label), const Gap(Space.xs)],
        CourseBalanceCard(subject: title, balance: b, planName: page.subject != null),
        if (!b.kids) ...[SectionHeader(l.balanceHowTitle), _How(balance: b)],
      ]),
    );
  }
}

/// How the balance works, in the course's own numbers.
class _How extends StatelessWidget {
  const _How({required this.balance});

  final CourseBalance balance;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final mark = balance.plan?.renewsAt;
    return LmsCard(
      key: const Key('balance-how'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _Point(icon: Icons.check_circle_outline_rounded, text: l.balanceHowTaken),
          if (balance.classic && mark != null) ...[
            const Gap(Space.m),
            _Point(icon: Icons.receipt_long_outlined, text: l.balanceHowMark(mark)),
          ],
          const Gap(Space.m),
          _Point(icon: Icons.event_available_outlined, text: l.balanceHowExpiry),
        ],
      ),
    );
  }
}

class _Point extends StatelessWidget {
  const _Point({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      IconBadge(icon, size: 32),
      const Gap(Space.m),
      Expanded(child: Text(text, style: context.type.body.copyWith(height: 1.6))),
    ],
  );
}
