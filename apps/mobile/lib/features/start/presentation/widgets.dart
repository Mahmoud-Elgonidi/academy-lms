import 'package:flutter/material.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';
import '../../trial/presentation/widgets.dart' show joinSpoken;
import '../domain/start.dart';

/// A plan as a card to choose: its name and kind (one to one, or a seat in a
/// group: never alike), its shape in plain words, a lesson's price and the
/// block's, the first invoice with what comes off it (the price struck
/// through), when the next invoice comes and that the family can stop.
class PlanChoice extends StatelessWidget {
  const PlanChoice({required this.plan, required this.selected, required this.onTap, this.recommended = false, super.key});

  final StartPlan plan;
  final bool selected;
  final bool recommended;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final p = plan;
    final accent = p.group ? c.info : c.brand;
    final shape = [
      if (p.perWeek > 0) l.trialPlanPerWeek(p.perWeek),
      if (p.sessionMinutes > 0) l.trialPlanLength(l.minutes(p.sessionMinutes)),
      if (p.perBlock > 0 && p.validityDays > 0) l.trialPlanBlock(l.lessonsCount(p.perBlock), l.trialDaysCount(p.validityDays)),
    ];
    return Semantics(
      button: true,
      selected: selected,
      label: joinSpoken(context, [
        p.name,
        p.group ? l.groupPlan : l.formatPrivate,
        ...shape,
        if (p.perLesson != null) l.startPerLesson(p.perLesson!.text),
        '${l.startFirstInvoice} ${p.firstTotal?.text ?? p.price.text}',
      ]),
      excludeSemantics: true,
      child: PressScale(
        onTap: onTap,
        child: AnimatedContainer(
          key: Key('plan-${p.id}'),
          duration: context.calm ? Duration.zero : const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          decoration: BoxDecoration(
            color: c.card,
            borderRadius: BorderRadius.circular(context.shape.card + 2),
            boxShadow: selected ? [BoxShadow(color: accent.withValues(alpha: 0.14), blurRadius: 18, offset: const Offset(0, 6))] : null,
          ),
          // The border over the price's band, so the card's edge stays whole.
          foregroundDecoration: BoxDecoration(
            borderRadius: BorderRadius.circular(context.shape.card + 2),
            border: Border.all(color: selected ? accent : c.line, width: selected ? 2 : 1),
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // A group plan wears the info colour along its top: it never looks like a private one.
              if (p.group) Container(height: 4, color: c.info),
              Padding(
                padding: const EdgeInsets.fromLTRB(Space.l, Space.l, Space.l, Space.m),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(child: Text(p.name, style: context.type.cardTitle.copyWith(fontSize: 17))),
                        const Gap(Space.s),
                        AnimatedSwitcher(
                          duration: context.calm ? Duration.zero : const Duration(milliseconds: 200),
                          transitionBuilder: (child, a) => ScaleTransition(scale: a, child: child),
                          child: selected
                              ? Icon(Icons.check_circle_rounded, key: const ValueKey('on'), color: accent, size: 26)
                              : Icon(Icons.radio_button_unchecked_rounded, key: const ValueKey('off'), color: c.faint, size: 26),
                        ),
                      ],
                    ),
                    const Gap(Space.s),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        if (p.group) GroupPill(taken: 0, capacity: 0, label: l.groupPlan) else const PrivatePill(),
                        if (recommended) StatusChip(l.startRecommended, tone: Tone.reward, icon: Icons.star_rounded),
                      ],
                    ),
                    const Gap(Space.s),
                    Text(
                      p.group ? l.startPlanGroupLine : l.startPlanPrivateLine,
                      style: context.type.body.copyWith(fontWeight: FontWeight.w600),
                    ),
                    if (shape.isNotEmpty) ...[const Gap(2), Text(shape.join(' · '), style: context.type.caption)],
                    if (p.shared) ...[const Gap(Space.xs), Text(l.startPlanShared, style: context.type.caption.copyWith(color: c.infoInk))],
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.fromLTRB(Space.l, Space.m, Space.l, Space.m),
                color: selected ? (p.group ? c.infoSoft : c.brandSoft) : c.page,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        if (p.perLesson != null)
                          Expanded(
                            child: Text.rich(
                              TextSpan(
                                children: [
                                  TextSpan(text: p.perLesson!.text, style: context.type.number.copyWith(fontSize: 20)),
                                  TextSpan(text: '  ${l.startPerLesson('').trim()}', style: context.type.caption),
                                ],
                              ),
                            ),
                          )
                        else
                          const Spacer(),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(l.startFirstInvoice, style: context.type.label),
                            if (p.discounted)
                              Text(
                                p.price.text,
                                key: Key('plan-was-${p.id}'),
                                style: context.type.caption.copyWith(
                                  decoration: TextDecoration.lineThrough,
                                  fontFeatures: const [FontFeature.tabularFigures()],
                                ),
                              ),
                            Text(
                              (p.discounted ? p.firstTotal! : p.price).text,
                              key: Key('plan-total-${p.id}'),
                              style: context.type.cardTitle.copyWith(
                                fontSize: 17,
                                color: p.discounted ? c.brandDeep : c.ink,
                                fontFeatures: const [FontFeature.tabularFigures()],
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                    if (p.discounts.isNotEmpty) ...[
                      const Gap(Space.s),
                      Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: [
                          for (final d in p.discounts)
                            StatusChip(
                              '${d.label}  ${d.amount.text}',
                              tone: p.offerApplied ? Tone.attention : Tone.brand,
                              icon: p.offerApplied ? Icons.local_offer_rounded : Icons.family_restroom_rounded,
                            ),
                        ],
                      ),
                    ],
                    if (p.renewsAt > 0) ...[
                      const Gap(Space.s),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Padding(
                            padding: const EdgeInsets.only(top: 2),
                            child: Icon(Icons.autorenew_rounded, size: 16, color: c.muted),
                          ),
                          const Gap(Space.xs),
                          Expanded(child: Text(l.startRenews(p.renewsAt), style: context.type.caption)),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// A teacher's photo from their card, else their initial.
class TeacherFace extends StatelessWidget {
  const TeacherFace({required this.name, this.photoUrl, this.size = 52, super.key});

  final String name;
  final String? photoUrl;
  final double size;

  @override
  Widget build(BuildContext context) {
    final url = photoUrl;
    if (url == null) return SoftAvatar(name, size: size);
    return ClipOval(
      child: Image.network(
        url,
        width: size,
        height: size,
        fit: BoxFit.cover,
        errorBuilder: (_, _, _) => SoftAvatar(name, size: size),
      ),
    );
  }
}

/// One line of a summary: what it is, and its value (or values, one under another), with a way to change it.
class SummaryRow extends StatelessWidget {
  const SummaryRow({required this.label, required this.value, this.icon, this.onChange, this.detail, super.key});

  final String label;
  final String value;
  final IconData? icon;
  final String? detail;
  final VoidCallback? onChange;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: Space.s + 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (icon != null) ...[IconBadge(icon!, size: 36), const Gap(Space.m)],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: context.type.label),
                const Gap(2),
                Text(value, style: context.type.body.copyWith(fontWeight: FontWeight.w700, height: 1.5)),
                if (detail != null) Text(detail!, style: context.type.caption),
              ],
            ),
          ),
          if (onChange != null)
            TextButton(
              onPressed: onChange,
              style: TextButton.styleFrom(foregroundColor: c.brandDeep, visualDensity: VisualDensity.compact),
              child: Text(context.l10n.startChange),
            ),
        ],
      ),
    );
  }
}

/// A money line: its words and amount, a discount in the brand's colour, a total bigger.
class MoneyRow extends StatelessWidget {
  const MoneyRow({required this.label, required this.amount, this.discount = false, this.total = false, this.muted = false, super.key});

  final String label;
  final String amount;
  final bool discount;
  final bool total;
  final bool muted;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final colour = discount ? c.brandDeep : (muted ? c.muted : c.ink);
    final style = total
        ? context.type.cardTitle.copyWith(fontSize: 18, color: colour)
        : context.type.body.copyWith(color: colour, fontWeight: discount ? FontWeight.w600 : null);
    return Padding(
      padding: EdgeInsets.symmetric(vertical: total ? Space.s : 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (discount) ...[
            Padding(
              padding: const EdgeInsets.only(top: 3),
              child: Icon(Icons.local_offer_outlined, size: 16, color: c.brandDeep),
            ),
            const Gap(Space.xs),
          ],
          Expanded(child: Text(label, style: style)),
          const Gap(Space.m),
          Text(amount, style: style.copyWith(fontFeatures: const [FontFeature.tabularFigures()])),
        ],
      ),
    );
  }
}
