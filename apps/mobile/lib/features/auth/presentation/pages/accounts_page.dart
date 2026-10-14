import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../app/theme/branding.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/open_link.dart';
import '../../domain/sign_in_models.dart';
import '../bloc/sign_in_cubit.dart';
import '../widgets/refusals.dart';
import '../widgets/sign_in_frame.dart';

/// Step three: the accounts the code found, in every academy. One account:
/// "Found you!" with its academy's logo and a button straight in. Several:
/// the person picks which to keep on this phone. Staff-only accounts belong
/// on the website and cannot be picked. Each academy's privacy notice and
/// terms are there to read first.
class AccountsPage extends StatelessWidget {
  const AccountsPage({this.base = '/welcome', super.key});

  final String base;

  Future<void> _add(BuildContext context) async {
    final cubit = context.read<SignInCubit>();
    if (!await cubit.addSelected() || !context.mounted) return;
    // An account with two-step sign-in asks for the authenticator's code first.
    cubit.state.twoFactor != null ? await context.push('$base/two-factor') : context.go('/');
  }

  void _another(BuildContext context) {
    context.read<SignInCubit>().restart();
    context.go(base);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return BlocBuilder<SignInCubit, SignInState>(
      builder: (context, state) {
        final found = state.discovery?.accounts ?? const <FoundAccount>[];
        final refusal = refusalStep(
          context,
          state.problem,
          busy: state.busy,
          onRetry: () => _add(context),
          onRestart: () => _another(context),
        );
        if (refusal != null) return refusal;
        final error = state.problem == null ? null : context.problemText(state.problem!);
        final lead = TextStyle(fontSize: 16, height: 1.65, color: c.muted);
        if (found.isEmpty) return _NoAccount(contact: state.contact, base: base);
        final problem = error == null ? null : Note(error, tone: Tone.danger, icon: Icons.error_outline_rounded);
        if (found.length == 1 && found.single.hasAppArea) {
          final account = found.single;
          return SignInFrame(
            title: l.foundYou,
            lead: Text(l.foundYouLead, style: lead),
            pinBottom: true,
            bottom: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(
                  height: 56,
                  child: PrimaryAction(
                    l.enterAcademy(account.academyName(context.locale)),
                    busy: state.busy,
                    onPressed: () => _add(context),
                  ),
                ),
                TextButton(
                  style: TextButton.styleFrom(minimumSize: const Size.fromHeight(48), foregroundColor: c.brandDeep),
                  onPressed: () => _another(context),
                  child: Text(l.notYou, style: const TextStyle(fontWeight: FontWeight.w600)),
                ),
              ],
            ),
            children: [
              _FoundCard(account: account),
              const Gap(Space.l),
              _AcademyPages(found: found),
              if (problem != null) ...[const Gap(Space.m), problem],
            ],
          );
        }
        final chosen = state.selected.length;
        return SignInFrame(
          title: l.accountsFound(found.length),
          lead: Text(l.accountsLead, style: lead),
          pinBottom: true,
          bottom: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SizedBox(
                height: 56,
                child: PrimaryAction(
                  l.addAccounts(chosen == 0 ? 1 : chosen),
                  busy: state.busy,
                  onPressed: chosen == 0 ? null : () => _add(context),
                ),
              ),
              TextButton(
                style: TextButton.styleFrom(minimumSize: const Size.fromHeight(48), foregroundColor: c.brandDeep),
                onPressed: () => _another(context),
                child: Text(l.otherContact, style: const TextStyle(fontWeight: FontWeight.w600)),
              ),
            ],
          ),
          children: [
            for (final account in found) ...[_AccountTile(account: account, selected: state.selected.contains(account.id)), const Gap(10)],
            if (chosen == 0)
              Text(
                l.pickOne,
                style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: c.danger),
              ),
            if (problem != null) ...[const Gap(Space.xs), problem],
            const Gap(Space.s),
            _AcademyPages(found: found),
            const Gap(10),
            Text(l.accountsNote, style: TextStyle(fontSize: 13, height: 1.5, color: c.muted)),
          ],
        );
      },
    );
  }
}

/// Nobody with this number or email in any academy: what to check, the
/// email as another way (for a number), another try, or a password.
class _NoAccount extends StatelessWidget {
  const _NoAccount({required this.contact, required this.base});

  final String contact;
  final String base;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final byEmail = contact.contains('@');
    void again() {
      context.read<SignInCubit>().restart();
      context.go(base);
    }

    return SignInFrame(
      title: contact.isEmpty ? l.noAccountTitle : (byEmail ? l.noAccountByEmail : l.noAccountByPhone),
      badge: Icons.person_search_rounded,
      pinBottom: true,
      lead: Text(
        contact.isEmpty ? l.noAccounts : (byEmail ? l.noAccountCheckEmail : l.noAccountCheckPhone),
        style: TextStyle(fontSize: 16, height: 1.65, color: c.muted),
      ),
      bottom: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(height: 56, child: PrimaryAction(l.noAccountOther, onPressed: again)),
          TextButton(
            style: TextButton.styleFrom(minimumSize: const Size.fromHeight(48), foregroundColor: c.brandDeep),
            onPressed: () {
              context.read<SignInCubit>().restart();
              context.go('$base/password');
            },
            child: Text(l.havePassword, style: const TextStyle(fontWeight: FontWeight.w600)),
          ),
        ],
      ),
      children: [
        if (contact.isNotEmpty)
          Container(
            padding: const EdgeInsetsDirectional.only(start: 14, end: 4),
            decoration: BoxDecoration(
              color: c.card,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: c.line),
            ),
            child: Row(
              children: [
                Icon(byEmail ? Icons.mail_outline_rounded : Icons.phone_iphone_rounded, size: 20, color: c.muted),
                const Gap(10),
                Expanded(
                  child: Text(
                    contact,
                    textDirection: TextDirection.ltr,
                    textAlign: TextAlign.start,
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: c.ink),
                  ),
                ),
                TextButton(
                  style: TextButton.styleFrom(minimumSize: const Size(48, 48), foregroundColor: c.brandDeep),
                  onPressed: again,
                  child: Text(l.contactChange, style: const TextStyle(fontWeight: FontWeight.w700)),
                ),
              ],
            ),
          ),
        if (contact.isNotEmpty && !byEmail) ...[
          const Gap(Space.m),
          LmsCard(
            onTap: again,
            child: Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(color: c.brandSoft, borderRadius: BorderRadius.circular(12)),
                  child: Icon(Icons.alternate_email_rounded, color: c.brandDeep),
                ),
                const Gap(Space.m),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        l.noAccountTryEmail,
                        style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: c.ink),
                      ),
                      const Gap(2),
                      Text(l.noAccountTryEmailBody, style: TextStyle(fontSize: 13.5, height: 1.5, color: c.muted)),
                    ],
                  ),
                ),
                Icon(Icons.chevron_right_rounded, color: c.muted),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

String roleLabel(BuildContext context, String role) => switch (role) {
  'student' => context.l10n.roleStudent,
  'guardian' => context.l10n.roleGuardian,
  'teacher' => context.l10n.roleTeacher,
  _ => context.l10n.roleStaff,
};

/// The academy's logo, or its first letter on its colour while there is none (or it does not load).
class _Logo extends StatelessWidget {
  const _Logo({required this.account, required this.size});

  final FoundAccount account;
  final double size;

  @override
  Widget build(BuildContext context) {
    final letter = InitialAvatar(account.academyName(context.locale), colour: Branding.parseColour(account.colour), size: size);
    final url = account.logoUrl;
    if (url == null || url.isEmpty) return letter;
    return ClipRRect(
      borderRadius: BorderRadius.circular(size * 0.32),
      child: Image.network(
        url,
        width: size,
        height: size,
        fit: BoxFit.cover,
        semanticLabel: account.academyName(context.locale),
        errorBuilder: (_, _, _) => letter,
        loadingBuilder: (context, child, progress) => progress == null ? child : letter,
      ),
    );
  }
}

/// The one account found, up front: its academy, the person's name and what they are there.
class _FoundCard extends StatelessWidget {
  const _FoundCard({required this.account});

  final FoundAccount account;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final roles = {for (final r in account.roles) roleLabel(context, r)};
    final card = Container(
      padding: const EdgeInsets.all(Space.l),
      decoration: BoxDecoration(
        color: c.card,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: c.brand, width: 2),
        boxShadow: [BoxShadow(color: c.brandDeep.withValues(alpha: 0.12), blurRadius: 24, offset: const Offset(0, 10))],
      ),
      child: Row(
        children: [
          _Logo(account: account, size: 60),
          const Gap(14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  account.academyName(context.locale),
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: c.ink),
                ),
                const Gap(2),
                Text(account.userName, style: TextStyle(fontSize: 15, color: c.muted)),
                const Gap(6),
                Wrap(spacing: 6, runSpacing: 4, children: [for (final r in roles) Pill(r)]),
              ],
            ),
          ),
          const _Tick(on: true, size: 32),
        ],
      ),
    );
    return context.calm
        ? card
        : card
              .animate(delay: const Duration(milliseconds: 120))
              .fadeIn(duration: const Duration(milliseconds: 300))
              .scaleXY(begin: 0.85, end: 1, duration: const Duration(milliseconds: 520), curve: Curves.easeOutBack);
  }
}

/// A round check that fills in when an account is chosen.
class _Tick extends StatelessWidget {
  const _Tick({required this.on, this.size = 28});

  final bool on;
  final double size;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return AnimatedContainer(
      duration: context.calm ? Duration.zero : const Duration(milliseconds: 220),
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: on ? c.brand : c.card,
        shape: BoxShape.circle,
        border: Border.all(color: on ? c.brand : c.line, width: 2),
      ),
      child: AnimatedSwitcher(
        duration: context.calm ? Duration.zero : const Duration(milliseconds: 260),
        transitionBuilder: (child, a) => ScaleTransition(
          scale: CurvedAnimation(parent: a, curve: Curves.easeOutBack),
          child: child,
        ),
        child: on
            ? Icon(Icons.check_rounded, key: const ValueKey(true), size: size * 0.62, color: Colors.white)
            : const SizedBox.shrink(key: ValueKey(false)),
      ),
    );
  }
}

/// Each academy's own privacy notice and terms (it decides what happens to the person's data there),
/// to read before adding the account.
class _AcademyPages extends StatelessWidget {
  const _AcademyPages({required this.found});

  final List<FoundAccount> found;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final academies = {
      for (final a in found)
        if (a.privacyUrl != null || a.termsUrl != null) a.academySlug: a,
    }.values.toList();
    if (academies.isEmpty) return const SizedBox.shrink();
    Widget link(String key, String? url, String label) => url == null
        ? const SizedBox.shrink()
        : TextButton(
            key: Key(key),
            style: TextButton.styleFrom(minimumSize: const Size(0, 44), foregroundColor: c.brandDeep),
            onPressed: () => openLink(context, Uri.parse(url)),
            child: Text(
              label,
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, decoration: TextDecoration.underline),
            ),
          );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l.accountsLegal, style: TextStyle(fontSize: 13, height: 1.5, color: c.muted)),
        for (final a in academies)
          Wrap(
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              if (academies.length > 1 || found.length > 1)
                Text(
                  a.academyName(context.locale),
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: c.ink),
                ),
              link('academy-privacy-${a.academySlug}', a.privacyUrl, l.myDataPrivacy),
              link('academy-terms-${a.academySlug}', a.termsUrl, l.myDataTerms),
            ],
          ),
      ],
    );
  }
}

class _AccountTile extends StatelessWidget {
  const _AccountTile({required this.account, required this.selected});

  final FoundAccount account;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final usable = account.hasAppArea;
    final roles = {for (final r in account.roles) roleLabel(context, r)}.toList();
    void toggle() => context.read<SignInCubit>().toggle(account.id);
    return Opacity(
      opacity: usable ? 1 : 0.55,
      child: Semantics(
        checked: usable ? selected : null,
        enabled: usable,
        child: PressScale(
          onTap: usable ? toggle : null,
          child: AnimatedContainer(
            duration: context.calm ? Duration.zero : const Duration(milliseconds: 200),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: selected ? c.brandSoft : c.card,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: selected ? c.brand : c.line, width: selected ? 2 : 1),
            ),
            child: Row(
              children: [
                _Logo(account: account, size: 44),
                const Gap(Space.m),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        account.academyName(context.locale),
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: c.ink),
                      ),
                      const Gap(2),
                      Text(account.userName, style: TextStyle(fontSize: 14, color: c.muted)),
                      const Gap(6),
                      Wrap(spacing: 6, runSpacing: 4, children: [for (final r in roles) Pill(r)]),
                    ],
                  ),
                ),
                if (usable) _Tick(on: selected),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
