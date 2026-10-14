import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../app/theme/tokens.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/motion/shake.dart';
import '../../../../shared/widgets/latin_digits.dart';
import '../../../../shared/widgets/sheets.dart';
import '../../domain/contact.dart';

/// The one field of the first step: a mobile number or an email, told apart
/// as it is typed. A number gets its country code beside it (from the
/// phone's clock, changed from a list with a search); the line under it says
/// where the code will go, or, in red, what is missing. A wrong try shakes
/// it.
class ContactField extends StatefulWidget {
  const ContactField({
    required this.controller,
    required this.country,
    required this.onCountry,
    required this.onSubmit,
    this.error,
    this.shakes = 0,
    super.key,
  });

  final TextEditingController controller;
  final String country;
  final ValueChanged<String> onCountry;
  final VoidCallback onSubmit;

  /// What is wrong, in red under the field (null: where the code will go).
  final String? error;

  /// Goes up each time the field should shake.
  final int shakes;

  @override
  State<ContactField> createState() => _ContactFieldState();
}

class _ContactFieldState extends State<ContactField> {
  final _focus = FocusNode();

  late ContactKind _kind = Contact.kindOf(widget.controller.text);

  @override
  void initState() {
    super.initState();
    _focus.addListener(() => setState(() {}));
    widget.controller.addListener(_typed);
  }

  @override
  void didUpdateWidget(ContactField old) {
    super.didUpdateWidget(old);
    if (old.controller != widget.controller) {
      old.controller.removeListener(_typed);
      widget.controller.addListener(_typed);
    }
  }

  @override
  void dispose() {
    widget.controller.removeListener(_typed);
    _focus.dispose();
    super.dispose();
  }

  void _typed() {
    final kind = Contact.kindOf(widget.controller.text);
    if (kind != _kind) setState(() => _kind = kind);
  }

  Future<void> _pickCountry() async {
    final picked = await pickCountry(context, widget.country);
    if (picked != null) widget.onCountry(picked);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final wrong = widget.error != null;
    final showCountry = _kind == ContactKind.phone && !Contact.international(widget.controller.text);
    final icon = switch (_kind) {
      ContactKind.phone => Icons.phone_iphone_rounded,
      ContactKind.email => Icons.mail_outline_rounded,
      ContactKind.none => Icons.person_outline_rounded,
    };
    final border = wrong ? c.danger : (_focus.hasFocus ? c.brand : c.line);
    final halo = wrong ? c.dangerSoft : (_focus.hasFocus ? c.brandSoft : Colors.transparent);
    final field = AnimatedContainer(
      duration: context.calm ? Duration.zero : const Duration(milliseconds: 200),
      height: 58,
      padding: const EdgeInsetsDirectional.only(start: 14, end: 8),
      decoration: BoxDecoration(
        color: c.card,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: border, width: 1.5),
        boxShadow: [BoxShadow(color: halo, spreadRadius: 4)],
      ),
      child: Row(
        children: [
          AnimatedSwitcher(
            duration: context.calm ? Duration.zero : const Duration(milliseconds: 260),
            transitionBuilder: (child, a) => ScaleTransition(
              scale: CurvedAnimation(parent: a, curve: Curves.easeOutBack),
              child: child,
            ),
            child: Icon(icon, key: ValueKey(icon), size: 24, color: _kind == ContactKind.none ? c.muted : c.brand),
          ),
          const Gap(10),
          Expanded(
            child: TextField(
              key: const Key('contact'),
              controller: widget.controller,
              focusNode: _focus,
              keyboardType: TextInputType.emailAddress,
              autocorrect: false,
              enableSuggestions: false,
              autofillHints: const [AutofillHints.email, AutofillHints.telephoneNumber],
              inputFormatters: const [LatinDigits()],
              textDirection: TextDirection.ltr,
              textInputAction: TextInputAction.send,
              onSubmitted: (_) => widget.onSubmit(),
              style: TextStyle(fontSize: 17, color: c.ink),
              decoration: InputDecoration(
                hintText: l.contactPlaceholder,
                hintTextDirection: TextDirection.ltr,
                hintStyle: TextStyle(fontSize: 16, color: c.faint),
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                errorBorder: InputBorder.none,
                filled: false,
                isCollapsed: true,
              ),
            ),
          ),
          AnimatedSize(
            duration: context.calm ? Duration.zero : const Duration(milliseconds: 220),
            curve: Curves.easeOutCubic,
            child: showCountry
                ? Padding(
                    padding: const EdgeInsetsDirectional.only(start: Space.s),
                    child: _CountryChip(code: widget.country, onTap: _pickCountry),
                  )
                : const SizedBox.shrink(),
          ),
        ],
      ),
    );
    final hint = switch ((widget.error, _kind)) {
      (final String e, _) => _Hint(key: ValueKey('error:$e'), text: e, icon: Icons.error_outline_rounded, colour: c.danger, strong: true),
      (_, ContactKind.phone) => _Hint(
        key: const ValueKey('phone'),
        text: l.phoneHint,
        icon: Icons.chat_bubble_outline_rounded,
        colour: c.muted,
      ),
      (_, ContactKind.email) => _Hint(key: const ValueKey('email'), text: l.emailHint, icon: Icons.mail_outline_rounded, colour: c.muted),
      _ => const SizedBox.shrink(key: ValueKey('none')),
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          l.contactLabel,
          style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: c.ink),
        ),
        const Gap(Space.s),
        Shake(count: widget.shakes, child: field),
        ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 46),
          child: Padding(
            padding: const EdgeInsets.only(top: Space.s),
            child: Semantics(
              liveRegion: true,
              child: AnimatedSwitcher(
                duration: context.calm ? Duration.zero : const Duration(milliseconds: 220),
                layoutBuilder: (current, previous) => Stack(alignment: AlignmentDirectional.topStart, children: [...previous, ?current]),
                child: hint,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _Hint extends StatelessWidget {
  const _Hint({required this.text, required this.icon, required this.colour, this.strong = false, super.key});

  final String text;
  final IconData icon;
  final Color colour;
  final bool strong;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Padding(
        padding: const EdgeInsets.only(top: 1),
        child: Icon(icon, size: 18, color: colour),
      ),
      const Gap(6),
      Expanded(
        child: Text(
          text,
          style: TextStyle(fontSize: 14, height: 1.5, color: colour, fontWeight: strong ? FontWeight.w600 : null),
        ),
      ),
    ],
  );
}

class _CountryChip extends StatelessWidget {
  const _CountryChip({required this.code, required this.onTap});

  final String code;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Semantics(
      button: true,
      label: context.l10n.countryChange(code),
      excludeSemantics: true,
      child: PressScale(
        onTap: onTap,
        child: Container(
          key: const Key('country'),
          height: 42,
          padding: const EdgeInsets.symmetric(horizontal: 10),
          decoration: BoxDecoration(
            color: c.page,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: c.line),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            textDirection: TextDirection.ltr,
            children: [
              Text(
                code,
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: c.ink),
              ),
              const Gap(Space.xs),
              Icon(Icons.expand_more_rounded, size: 18, color: c.muted),
            ],
          ),
        ),
      ),
    );
  }
}

/// A country's name in the reader's language.
String countryName(BuildContext context, String code) {
  final l = context.l10n;
  return switch (code) {
    '+966' => l.countrySa,
    '+20' => l.countryEg,
    '+971' => l.countryAe,
    '+965' => l.countryKw,
    '+974' => l.countryQa,
    '+973' => l.countryBh,
    '+968' => l.countryOm,
    '+962' => l.countryJo,
    '+212' => l.countryMa,
    '+1' => l.countryUs,
    '+44' => l.countryGb,
    _ => code,
  };
}

/// The country list over the whole app, with a search by name or code.
Future<String?> pickCountry(BuildContext context, String current) => showActionSheet<String>(
  context,
  title: context.l10n.countryCode,
  builder: (context) => _CountrySheet(current: current),
);

class _CountrySheet extends StatefulWidget {
  const _CountrySheet({required this.current});

  final String current;

  @override
  State<_CountrySheet> createState() => _CountrySheetState();
}

class _CountrySheetState extends State<_CountrySheet> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final q = latinDigits(_query).trim().toLowerCase();
    final digits = q.replaceAll(RegExp(r'\D'), '');
    final shown = [
      for (final code in countryCodes)
        if (q.isEmpty || countryName(context, code).toLowerCase().contains(q) || (digits.isNotEmpty && code.contains(digits))) code,
    ];
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(Space.l, 0, Space.l, Space.s),
            child: TextField(
              key: const Key('country-search'),
              autofocus: true,
              onChanged: (v) => setState(() => _query = v),
              decoration: InputDecoration(hintText: l.countrySearch, prefixIcon: const Icon(Icons.search_rounded)),
            ),
          ),
          Flexible(
            child: shown.isEmpty
                ? Padding(
                    padding: const EdgeInsets.all(Space.xl),
                    child: Text(
                      l.countryNone,
                      textAlign: TextAlign.center,
                      style: TextStyle(color: c.muted),
                    ),
                  )
                : ListView(
                    shrinkWrap: true,
                    children: [
                      for (final code in shown)
                        ListTile(
                          key: Key('country-$code'),
                          title: Text(countryName(context, code)),
                          trailing: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                code,
                                textDirection: TextDirection.ltr,
                                style: TextStyle(color: c.muted, fontWeight: FontWeight.w600),
                              ),
                              if (code == widget.current) ...[const Gap(Space.s), Icon(Icons.check_rounded, color: c.brand)],
                            ],
                          ),
                          selected: code == widget.current,
                          onTap: () => Navigator.of(context).pop(code),
                        ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}
