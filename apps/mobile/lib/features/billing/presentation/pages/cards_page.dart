import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/done_moment.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/cards_repository.dart';
import '../../domain/cards.dart';

/// The payer's saved cards and automatic renewal: reads them, and each write
/// shows the whole picture the API answered with (no second read).
class CardsCubit extends LoadCubit<SavedCards> {
  CardsCubit(CardsRepository repo, {super.refreshOn}) : _repo = repo, super(repo.cards);

  final CardsRepository _repo;

  /// Turns automatic renewal on or off (one account of several, else all); the API's sentence, or why not.
  Future<({String? message, ApiProblem? problem})> autoRenew(bool on, {String? accountId}) async {
    try {
      final done = await _repo.autoRenew(on: on, accountId: accountId);
      show(done.cards);
      return (message: done.message, problem: null);
    } on ApiProblem catch (p) {
      return (message: null, problem: p);
    }
  }

  /// Lets a card go; the API's sentence, or why not.
  Future<({String? message, ApiProblem? problem})> remove(String cardId) async {
    try {
      final done = await _repo.remove(cardId);
      show(done.cards);
      return (message: done.message, problem: null);
    } on ApiProblem catch (p) {
      return (message: null, problem: p);
    }
  }
}

/// «البطاقات والتجديد التلقائي» under Payments (docs/architecture/02,
/// section 27, J5b; the classic look only): whether renewals are paid from
/// the saved card, switched in one tap (turning it off asked first, since it
/// stops the charges to come); the card as a card (brand, last four, expiry,
/// where it is kept), a warning when it expires soon, and removing it (asked
/// first); the charges coming with their moment as the API wrote it; the
/// words agreed to; and, with no card, how to save one. Follows the payments
/// signal and reads again when the app comes back to the front.
class CardsPage extends StatefulWidget {
  const CardsPage({super.key});

  static const location = '/family/money/cards';

  @override
  State<CardsPage> createState() => _CardsPageState();
}

class _CardsPageState extends State<CardsPage> {
  late final AppLifecycleListener _resume;
  CardsCubit? _cubit;

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
  Widget build(BuildContext context) => BlocProvider<LoadCubit<SavedCards>>(
    create: (c) => _cubit = CardsCubit(c.read<CardsRepository>(), refreshOn: c.read<Live>().on({Live.payments}))..load(),
    child: Scaffold(
      appBar: AppBar(title: Text(context.l10n.cardsTitle)),
      body: LoadView<SavedCards>(
        skeleton: const SkeletonList(),
        builder: (context, data) => _Cards(data: data),
      ),
    ),
  );
}

class _Cards extends StatefulWidget {
  const _Cards({required this.data});

  final SavedCards data;

  @override
  State<_Cards> createState() => _CardsState();
}

class _CardsState extends State<_Cards> {
  /// What is being written now: an account's switch (`auto:{id}`, `auto:` for all) or a card (`card:{id}`).
  String? _busy;

  CardsCubit get _cubit => context.read<LoadCubit<SavedCards>>() as CardsCubit;

  Future<void> _switch(bool on, {String? accountId}) async {
    final l = context.l10n;
    if (!on) {
      final sure = await confirmDestructive(
        context,
        title: l.cardsOffTitle,
        body: l.cardsOffBody,
        confirm: l.cardsOffYes,
        keep: l.cardsOffKeep,
      );
      if (!sure || !mounted) return;
    }
    setState(() => _busy = 'auto:${accountId ?? ''}');
    final done = await _cubit.autoRenew(on, accountId: accountId);
    if (!mounted) return;
    setState(() => _busy = null);
    _said(done);
    if (on && done.problem == null) Juice.done();
  }

  Future<void> _remove(SavedCard card) async {
    final l = context.l10n;
    final last = widget.data.cards.length == 1 && widget.data.autoRenew;
    final sure = await confirmDestructive(
      context,
      title: l.cardsRemoveTitle(card.label),
      body: last ? l.cardsRemoveBodyLast : l.cardsRemoveBody,
      confirm: l.cardsRemoveYes,
      keep: l.notNow,
    );
    if (!sure || !mounted) return;
    setState(() => _busy = 'card:${card.id}');
    final done = await _cubit.remove(card.id);
    if (!mounted) return;
    setState(() => _busy = null);
    _said(done);
  }

  void _said(({String? message, ApiProblem? problem}) done) {
    if (done.problem case final p?) {
      showSnack(context, context.problemText(p));
    } else if (done.message case final m? when m.isNotEmpty) {
      DoneMoment.show(context, title: m);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final d = widget.data;
    final coming = d.coming;
    return ListView(
      key: const Key('cards-view'),
      padding: pagePadding.copyWith(top: Space.m, bottom: Space.xxl),
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        if (!d.hasCard)
          LmsCard(
            key: const Key('cards-empty'),
            child: EmptyState(icon: Icons.credit_card_off_outlined, title: l.cardsEmptyTitle, body: l.cardsEmptyBody),
          )
        else ...[
          _AutoRenew(data: d, busy: _busy, onSwitch: _switch),
          SectionHeader(d.cards.length > 1 ? l.cardsTitle : l.cardsCardTitle),
          for (final (i, card) in d.cards.indexed) ...[
            if (i > 0) const Gap(Space.m),
            _CardTile(
              card: card,
              inUse: d.autoRenew && d.accounts.any((a) => a.autoRenew && a.cardId == card.id),
              busy: _busy == 'card:${card.id}',
              onRemove: _busy == null ? () => _remove(card) : null,
            ),
          ],
        ],
        if (coming.isNotEmpty) ...[
          SectionHeader(l.cardsComingTitle),
          MenuGroup(children: [for (final charge in coming) _ComingRow(charge: charge)]),
          if (d.stopsSomething) ...[const Gap(Space.s), Text(l.cardsComingNote, style: context.type.caption)],
        ],
        // The words agreed to when the card was saved (none agreed yet without one).
        if (d.mandate case final mandate? when mandate.text.isNotEmpty && d.hasCard) ...[
          const Gap(Space.l),
          _Mandate(text: mandate.text, title: l.cardsMandateTitle),
        ],
        const Gap(Space.l),
        _Safe(text: l.cardsSafe),
      ]),
    );
  }
}

/// The switch: on (paid from the card, told three days before), off (paid by hand), or not yet possible (no card).
/// One switch for the payer, or one per currency when they pay in several.
class _AutoRenew extends StatelessWidget {
  const _AutoRenew({required this.data, required this.busy, required this.onSwitch});

  final SavedCards data;
  final String? busy;
  final Future<void> Function(bool on, {String? accountId}) onSwitch;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final on = data.autoRenew;
    final rows = [
      if (data.perAccount)
        for (final a in data.accounts)
          _SwitchRow(
            key: Key('cards-auto-${a.id}'),
            label: l.cardsAutoCurrency(a.currency),
            on: a.autoRenew && a.cardId != null,
            busy: busy == 'auto:${a.id}',
            onChanged: busy != null || (a.cardId == null && !a.autoRenew) ? null : (v) => onSwitch(v, accountId: a.id),
          ),
    ];
    return AnimatedContainer(
      key: const Key('cards-auto-card'),
      duration: context.calm ? Duration.zero : const Duration(milliseconds: 280),
      padding: const EdgeInsets.all(Space.l),
      decoration: BoxDecoration(
        color: on ? c.brandSoft : c.card,
        borderRadius: BorderRadius.circular(context.shape.card + 4),
        border: Border.all(color: on ? c.brand.withValues(alpha: 0.35) : c.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              IconBadge(on ? Icons.autorenew_rounded : Icons.pause_circle_outline_rounded, tone: on ? Tone.brand : Tone.neutral, size: 40),
              const Gap(Space.m),
              Expanded(child: Text(l.cardsAutoTitle, style: context.type.cardTitle)),
              if (data.perAccount)
                StatusChip(on ? l.balanceAutoOn : l.balanceAutoOff, tone: on ? Tone.brand : Tone.neutral)
              else ...[
                if (busy == 'auto:') ...[
                  const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2)),
                  const Gap(Space.s),
                ],
                Semantics(
                  label: l.cardsAutoTitle,
                  child: Switch.adaptive(key: const Key('cards-auto'), value: on, onChanged: busy != null ? null : (v) => onSwitch(v)),
                ),
              ],
            ],
          ),
          const Gap(Space.m),
          Semantics(
            liveRegion: true,
            child: Text(
              on ? l.cardsAutoOn : l.cardsAutoOff,
              key: const Key('cards-auto-text'),
              style: context.type.body.copyWith(height: 1.6, color: on ? c.brandDeep : c.muted),
            ),
          ),
          if (rows.isNotEmpty) ...[const Gap(Space.s), ...rows],
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.label, required this.on, required this.busy, required this.onChanged, super.key});

  final String label;
  final bool on;
  final bool busy;
  final ValueChanged<bool>? onChanged;

  @override
  Widget build(BuildContext context) => MergeSemantics(
    child: Row(
      children: [
        Expanded(
          child: Text(label, style: context.type.body.copyWith(fontWeight: FontWeight.w600)),
        ),
        if (busy) ...[const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2)), const Gap(Space.s)],
        Switch.adaptive(value: on, onChanged: onChanged),
      ],
    ),
  );
}

/// A saved card drawn as a card, in the academy's own colour: its brand, the last four, the expiry and where it is
/// kept; a warning when it expires soon; removing it.
class _CardTile extends StatelessWidget {
  const _CardTile({required this.card, required this.inUse, required this.busy, required this.onRemove});

  final SavedCard card;
  final bool inUse;
  final bool busy;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    const ink = Colors.white;
    final brand = card.brandLabel.isNotEmpty ? card.brandLabel : card.label.split(' ').first;
    return Column(
      key: Key('card-${card.id}'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Semantics(
          label: [card.label, if (card.expiry.isNotEmpty) l.cardsExpires(card.expiry)].join('، '),
          excludeSemantics: true,
          child: AspectRatio(
            aspectRatio: 1.75,
            child: Container(
              padding: const EdgeInsets.all(Space.l + 2),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(context.shape.card + 6),
                gradient: LinearGradient(
                  colors: [c.brandDeep, Color.lerp(c.brandDeep, Colors.black, 0.3)!],
                  begin: AlignmentDirectional.topStart,
                  end: AlignmentDirectional.bottomEnd,
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.credit_card_rounded, color: ink, size: 26),
                      const Gap(Space.s),
                      Expanded(
                        child: Text(
                          brand,
                          style: const TextStyle(color: ink, fontWeight: FontWeight.w800, fontSize: 17),
                        ),
                      ),
                      if (inUse)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                          decoration: BoxDecoration(color: ink.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(999)),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(Icons.autorenew_rounded, color: ink, size: 14),
                              const SizedBox(width: 4),
                              Text(
                                l.cardsInUse,
                                style: const TextStyle(color: ink, fontSize: 11.5, fontWeight: FontWeight.w700),
                              ),
                            ],
                          ),
                        ),
                    ],
                  ),
                  const Spacer(),
                  Directionality(
                    textDirection: TextDirection.ltr,
                    child: Text(
                      '••••  ••••  ••••  ${card.last4}',
                      key: Key('card-${card.id}-number'),
                      style: const TextStyle(color: ink, fontSize: 20, letterSpacing: 1.5, fontWeight: FontWeight.w700),
                    ),
                  ),
                  const Gap(Space.s),
                  Row(
                    children: [
                      if (card.account.isNotEmpty)
                        Expanded(
                          child: Text(
                            l.cardsKeptAt(card.account),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(color: ink.withValues(alpha: 0.85), fontSize: 12.5),
                          ),
                        )
                      else
                        const Spacer(),
                      if (card.expiry.isNotEmpty)
                        Text(
                          l.cardsExpires(card.expiry),
                          key: Key('card-${card.id}-expiry'),
                          style: const TextStyle(color: ink, fontSize: 13, fontWeight: FontWeight.w700),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
        if (card.expiresSoon) ...[
          const Gap(Space.s),
          Note(l.cardsExpiresSoon, key: Key('card-${card.id}-soon'), tone: Tone.attention, icon: Icons.event_busy_outlined),
        ],
        const Gap(Space.xs),
        Row(
          children: [
            if (card.savedAt case final at?)
              Expanded(child: Text(l.cardsSavedOn(at.day(context.locale)), style: context.type.caption))
            else
              const Spacer(),
            TextButton.icon(
              key: Key('card-${card.id}-remove'),
              style: TextButton.styleFrom(foregroundColor: c.dangerInk),
              onPressed: busy ? null : onRemove,
              icon: busy
                  ? const SizedBox.square(dimension: 16, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.delete_outline_rounded, size: 18),
              label: Text(l.cardsRemove),
            ),
          ],
        ),
      ],
    );
  }
}

/// An automatic charge coming: which invoice, how much, when (as the API wrote it) and where it stands; opens the invoice.
class _ComingRow extends StatelessWidget {
  const _ComingRow({required this.charge});

  final ComingCharge charge;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final (label, tone) = switch (charge.status) {
      ChargeStatus.scheduled => (l.chargeScheduled, Tone.info),
      ChargeStatus.retry => (l.chargeRetry, Tone.attention),
      ChargeStatus.charging => (l.chargeCharging, Tone.brand),
    };
    return InkWell(
      key: Key('coming-${charge.invoiceId}'),
      onTap: charge.invoiceId.isEmpty ? null : () => context.push('/family/pay/${charge.invoiceId}'),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.m),
        child: Row(
          children: [
            const IconBadge(Icons.event_repeat_rounded, tone: Tone.info, size: 36),
            const Gap(Space.m),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(charge.amount, style: context.type.cardTitle.copyWith(fontFeatures: const [FontFeature.tabularFigures()])),
                  if (charge.at case final at?) Text(at.label, key: Key('coming-${charge.invoiceId}-at'), style: context.type.body),
                  Text(l.payInvoiceNumber(charge.number), style: context.type.caption),
                ],
              ),
            ),
            const Gap(Space.s),
            StatusChip(label, tone: tone),
          ],
        ),
      ),
    );
  }
}

/// The words agreed to when the card was saved, folded.
class _Mandate extends StatelessWidget {
  const _Mandate({required this.text, required this.title});

  final String text;
  final String title;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Material(
      color: c.card,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(context.shape.card),
        side: BorderSide(color: c.line),
      ),
      clipBehavior: Clip.antiAlias,
      child: Theme(
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          key: const Key('cards-mandate'),
          leading: Icon(Icons.description_outlined, color: c.muted),
          title: Text(title, style: context.type.body.copyWith(fontWeight: FontWeight.w600)),
          childrenPadding: const EdgeInsets.fromLTRB(Space.l, 0, Space.l, Space.l),
          expandedCrossAxisAlignment: CrossAxisAlignment.start,
          children: [SelectableText(text, style: context.type.caption.copyWith(height: 1.7))],
        ),
      ),
    );
  }
}

class _Safe extends StatelessWidget {
  const _Safe({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Padding(
        padding: const EdgeInsets.only(top: 2),
        child: Icon(Icons.lock_outline_rounded, size: 16, color: context.colors.muted),
      ),
      const Gap(Space.s),
      Expanded(child: Text(text, style: context.type.caption)),
    ],
  );
}

/// Opens the cards screen from anywhere in the family's look (a tab location under Payments).
void openCards(BuildContext context) => context.go(CardsPage.location);
