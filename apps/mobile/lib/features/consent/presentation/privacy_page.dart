import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../data/consent_repository.dart';
import '../domain/consent.dart';

/// A guardian's consent to each child's account (the same as in /my and the
/// portal): the wording and "I agree" while a child waits; who agreed, and
/// taking it back, once they have. It follows the consents live.
class PrivacyPage extends StatelessWidget {
  const PrivacyPage({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(context.l10n.privacyTitle)),
    body: BlocProvider(
      create: (c) => LoadCubit<Consents>(c.read<ConsentRepository>().load, refreshOn: c.read<Live>().on({Live.consents}))..load(),
      child: LoadView<Consents>(
        builder: (context, consents) => ListView(
          padding: pagePadding,
          physics: const AlwaysScrollableScrollPhysics(),
          children: staggered(context, [
            Text(context.l10n.privacyLead(consents.age), style: context.text.bodyMedium?.copyWith(color: context.colors.muted)),
            const SizedBox(height: 14),
            if (consents.children.isEmpty) EmptyNote(context.l10n.privacyNone),
            for (final child in consents.children) ...[_ChildCard(child: child), const SizedBox(height: 14)],
          ]),
        ),
      ),
    ),
  );
}

class _ChildCard extends StatefulWidget {
  const _ChildCard({required this.child});

  final ChildConsent child;

  @override
  State<_ChildCard> createState() => _ChildCardState();
}

class _ChildCardState extends State<_ChildCard> {
  bool _ticked = false;
  bool _busy = false;

  Future<void> _run(Future<void> Function() step, String done) async {
    setState(() => _busy = true);
    try {
      await step();
      if (!mounted) return;
      showSnack(context, done);
      await context.read<LoadCubit<Consents>>().load();
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final child = widget.child;
    final repo = context.read<ConsentRepository>();
    return LmsCard(
      color: child.waiting ? c.attentionSoft : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(child.name, style: context.text.titleMedium)),
              Pill(child.waiting ? l.consentWaiting : l.consentGranted, tone: child.waiting ? Tone.attention : Tone.brand),
            ],
          ),
          const SizedBox(height: 8),
          if (child.waiting) ...[
            Text(child.text.intro, style: context.text.bodyMedium),
            const SizedBox(height: 10),
            for (final part in child.text.sections)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(part.title, style: context.text.titleSmall),
                    Text(part.body, style: context.text.bodySmall),
                  ],
                ),
              ),
            CheckboxListTile(
              key: Key('agree-${child.studentId}'),
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              value: _ticked,
              onChanged: _busy ? null : (v) => setState(() => _ticked = v ?? false),
              title: Text(child.text.agree, style: context.text.titleSmall),
            ),
            const SizedBox(height: 6),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: !_ticked || _busy
                    ? null
                    : () => _run(() => repo.grant(child.studentId, child.text.version), l.consentAgreed(child.name)),
                child: Text(l.consentAgree),
              ),
            ),
          ] else ...[
            if (child.by != null) Text(child.by!, style: context.text.bodySmall),
            if (child.canWithdraw)
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: TextButton.icon(
                  style: TextButton.styleFrom(foregroundColor: c.dangerInk),
                  icon: const Icon(Icons.block_rounded),
                  label: Text(l.consentWithdraw),
                  onPressed: _busy
                      ? null
                      : () async {
                          final sure = await showDialog<bool>(
                            context: context,
                            builder: (d) => AlertDialog(
                              content: Text(d.l10n.consentWithdrawConfirm(child.name)),
                              actions: [
                                TextButton(onPressed: () => Navigator.pop(d, false), child: Text(d.l10n.back)),
                                FilledButton(
                                  style: FilledButton.styleFrom(backgroundColor: d.colors.danger),
                                  onPressed: () => Navigator.pop(d, true),
                                  child: Text(d.l10n.consentWithdraw),
                                ),
                              ],
                            ),
                          );
                          if (sure == true) await _run(() => repo.withdraw(child.studentId), l.consentWithdrawn(child.name));
                        },
                ),
              ),
          ],
        ],
      ),
    );
  }
}

/// On a guardian's home and "Me": the children waiting for their consent,
/// and the way to give it. Nothing when none waits (or for anyone else).
class ConsentNudge extends StatelessWidget {
  const ConsentNudge({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<Consents>(c.read<ConsentRepository>().load, refreshOn: c.read<Live>().on({Live.consents}))..load(),
    child: BlocBuilder<LoadCubit<Consents>, LoadState<Consents>>(
      builder: (context, state) {
        final waiting = state.data?.waiting ?? const <ChildConsent>[];
        if (waiting.isEmpty) return const SizedBox.shrink();
        final names = waiting.map((c) => c.name).join('، ');
        return Padding(
          padding: const EdgeInsets.only(bottom: 14),
          child: LmsCard(
            key: const Key('consent-nudge'),
            color: context.colors.attentionSoft,
            onTap: () => context.go('/family/me/privacy'),
            child: Row(
              children: [
                Icon(Icons.verified_user_outlined, color: context.colors.attentionInk),
                const SizedBox(width: 10),
                Expanded(child: Text(context.l10n.consentNudge(waiting.length, names), style: context.text.bodyMedium)),
                Text(context.l10n.consentNudgeAction, style: context.text.labelLarge?.copyWith(color: context.colors.attentionInk)),
              ],
            ),
          ),
        );
      },
    ),
  );
}
