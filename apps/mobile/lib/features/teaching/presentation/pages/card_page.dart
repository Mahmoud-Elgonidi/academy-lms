import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../homework/data/homework_repository.dart';
import '../../data/teaching_repository.dart';
import '../../domain/teacher_card.dart';

const _maxBytes = 1024 * 1024;
const _mimes = {'png': 'image/png', 'jpg': 'image/jpeg', 'jpeg': 'image/jpeg', 'webp': 'image/webp'};

/// "My card on the academy's page" (as /teach's): the teacher writes what
/// families see about them there and switches it on or off, with a preview
/// under the form. Whether the academy took it off is said, never why.
class CardPage extends StatelessWidget {
  const CardPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocProvider(
      create: (c) => LoadCubit<TeacherCard>(c.read<TeachingRepository>().card)..load(),
      child: Scaffold(
        appBar: AppBar(title: Text(l.teacherCardTitle)),
        body: LoadView<TeacherCard>(builder: (context, card) => _CardForm(card: card)),
      ),
    );
  }
}

class _CardForm extends StatefulWidget {
  const _CardForm({required this.card});

  final TeacherCard card;

  @override
  State<_CardForm> createState() => _CardFormState();
}

class _CardFormState extends State<_CardForm> {
  late TeacherCard _card = widget.card;
  late final _name = TextEditingController(text: _card.displayName);
  late final _bio = TextEditingController(text: _card.bio ?? '');
  late bool _shown = _card.shown;
  HandInFile? _photo;
  bool _remove = false;
  bool _busy = false;
  bool _saved = false;
  ApiProblem? _problem;
  String? _error;

  @override
  void initState() {
    super.initState();
    _name.addListener(_changed);
    _bio.addListener(_changed);
  }

  @override
  void dispose() {
    _name.dispose();
    _bio.dispose();
    super.dispose();
  }

  void _changed() => setState(() => _saved = false);

  Future<void> _pick() async {
    final l = context.l10n;
    PlatformFile? picked;
    try {
      picked = await FilePicker.pickFile(type: FileType.custom, allowedExtensions: _mimes.keys.toList());
    } on Object {
      if (mounted) setState(() => _error = l.problem);
      return;
    }
    final file = picked;
    if (file == null || !mounted) return;
    // A big picture is turned down before it is read into memory, as the portal does before sending.
    final size = file.lengthSync();
    if (size != null && size > _maxBytes) {
      setState(() => _error = l.teacherCardTooBig);
      return;
    }
    final bytes = await file.readAsBytes();
    if (!mounted) return;
    if (bytes.length > _maxBytes) {
      setState(() => _error = l.teacherCardTooBig);
      return;
    }
    setState(() {
      _photo = HandInFile(bytes: bytes, name: file.name, mime: _mimes[(file.extension ?? '').toLowerCase()] ?? 'application/octet-stream');
      _remove = false;
      _error = null;
      _saved = false;
    });
  }

  Future<void> _save() async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _problem = null;
      _error = null;
      _saved = false;
    });
    try {
      final saved = await context.read<TeachingRepository>().saveCard(
        displayName: _name.text,
        bio: _bio.text,
        shown: _shown,
        photo: _photo,
        removePhoto: _remove,
      );
      if (!mounted) return;
      context.read<LoadCubit<TeacherCard>>().show(saved);
      setState(() {
        _card = saved;
        _shown = saved.shown;
        _photo = null;
        _remove = false;
        _busy = false;
        _saved = true;
      });
    } on ApiProblem catch (p) {
      if (mounted) {
        setState(() {
          _busy = false;
          _problem = p;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final card = _card;
    final p = _problem;
    final fieldsWrong = p != null && ['display_name', 'bio', 'photo', 'shown'].any((f) => p.field(f) != null);
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        Text(l.teacherCardLead, style: context.type.caption),
        const Gap(Space.m),
        if (card.stateLabel.isNotEmpty) ...[
          Note(
            card.stateLabel,
            key: const Key('card-state'),
            tone: switch (card.state) {
              'hidden' => Tone.attention,
              'on' => Tone.brand,
              _ => Tone.info,
            },
            icon: switch (card.state) {
              'hidden' => Icons.visibility_off_outlined,
              'on' => Icons.visibility_outlined,
              _ => Icons.info_outline_rounded,
            },
          ),
          const Gap(Space.m),
        ],
        LmsCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SwitchListTile.adaptive(
                key: const Key('card-shown'),
                contentPadding: EdgeInsets.zero,
                value: _shown,
                onChanged: _busy ? null : (v) => setState(() => _shown = v),
                title: Text(l.teacherCardShown, style: context.type.cardTitle),
                subtitle: Text(l.teacherCardShownHint, style: context.type.caption),
              ),
              const Divider(height: Space.l),
              TextField(
                key: const Key('card-name'),
                controller: _name,
                maxLength: 60,
                textInputAction: TextInputAction.next,
                decoration: InputDecoration(
                  labelText: l.teacherCardName,
                  helperText: l.teacherCardNameHint,
                  errorText: p?.field('display_name'),
                ),
              ),
              const Gap(Space.s),
              TextField(
                key: const Key('card-bio'),
                controller: _bio,
                maxLength: 280,
                minLines: 3,
                maxLines: 6,
                decoration: InputDecoration(
                  labelText: l.teacherCardBio,
                  helperText: l.teacherCardBioHint,
                  helperMaxLines: 3,
                  errorText: p?.field('bio'),
                  errorMaxLines: 3,
                ),
              ),
              const Gap(Space.m),
              Text(l.teacherCardPhoto, style: context.type.cardTitle),
              const Gap(Space.xs),
              Text(l.teacherCardPhotoHint, style: context.type.caption),
              const Gap(Space.s),
              OutlinedButton.icon(
                key: const Key('card-photo'),
                onPressed: _busy ? null : _pick,
                icon: const Icon(Icons.add_photo_alternate_outlined),
                label: Text(
                  _photo?.name ?? (card.photoUrl == null ? l.teacherCardPickPhoto : l.teacherCardChangePhoto),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (p?.field('photo') case final String wrong) ...[
                const Gap(Space.xs),
                Text(wrong, style: context.type.caption.copyWith(color: c.dangerInk)),
              ],
              if (card.photoUrl != null && _photo == null)
                CheckboxListTile(
                  key: const Key('card-remove-photo'),
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  value: _remove,
                  onChanged: _busy ? null : (v) => setState(() => _remove = v ?? false),
                  title: Text(l.teacherCardRemovePhoto),
                ),
              if (_error != null || (p != null && !fieldsWrong)) ...[
                const Gap(Space.s),
                Note(_error ?? context.problemText(p!), key: const Key('card-error'), tone: Tone.danger),
              ],
              if (_saved) ...[
                const Gap(Space.s),
                Note(l.teacherCardSaved, key: const Key('card-saved'), tone: Tone.brand, icon: Icons.check_circle_outline_rounded),
              ],
              const Gap(Space.m),
              FilledButton.icon(
                key: const Key('card-save'),
                onPressed: _busy ? null : _save,
                icon: const Icon(Icons.check_rounded),
                label: Text(l.teacherCardSave),
              ),
            ],
          ),
        ),
        SectionHeader(l.teacherCardPreview),
        _Preview(
          card: card,
          name: _name.text.trim().isEmpty ? card.displayName : _name.text.trim(),
          bio: _bio.text.trim(),
          picked: _photo?.bytes,
          photoUrl: _remove ? null : card.photoUrl,
        ),
      ]),
    );
  }
}

/// The card as the academy's page shows it: the photo (or the name's first
/// letter), the name, the subjects with their levels, and the words.
class _Preview extends StatelessWidget {
  const _Preview({required this.card, required this.name, required this.bio, required this.picked, required this.photoUrl});

  final TeacherCard card;
  final String name;
  final String bio;
  final Uint8List? picked;
  final String? photoUrl;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final word = givenName(name);
    final letter = Center(
      child: Text(
        word.isEmpty ? '?' : word.characters.first,
        style: TextStyle(fontFamily: context.shape.headingFont, color: c.onBrand, fontWeight: FontWeight.w700, fontSize: 36),
      ),
    );
    final url = photoUrl;
    final photo = picked != null
        ? Image.memory(picked!, fit: BoxFit.cover, errorBuilder: (_, _, _) => letter)
        : url != null
        ? Image.network(url, fit: BoxFit.cover, errorBuilder: (_, _, _) => letter)
        : letter;
    String? levels(String? from, String? to) =>
        from == null && to == null ? null : l.teacherCardLevels(from ?? l.teacherCardFromStart, to ?? l.teacherCardToEnd);
    return LmsCard(
      key: const Key('card-preview'),
      padding: const EdgeInsets.all(Space.l),
      child: Column(
        children: [
          Container(
            width: 96,
            height: 96,
            clipBehavior: Clip.antiAlias,
            decoration: BoxDecoration(
              color: c.brand,
              shape: BoxShape.circle,
              border: Border.all(color: c.brandSoft, width: 4),
            ),
            child: ExcludeSemantics(child: photo),
          ),
          const Gap(Space.m),
          Text(name, textAlign: TextAlign.center, style: context.type.title),
          for (final s in card.subjects) ...[
            const Gap(Space.xs),
            Text(s.name, textAlign: TextAlign.center, style: context.type.cardTitle.copyWith(fontSize: 14)),
            if (levels(s.from, s.to) case final String range) Text(range, textAlign: TextAlign.center, style: context.type.caption),
          ],
          if (bio.isNotEmpty) ...[
            const Gap(Space.m),
            Text(
              bio,
              textAlign: TextAlign.center,
              style: context.type.body.copyWith(color: c.muted),
            ),
          ],
          if (card.subjects.isNotEmpty) ...[
            const Gap(Space.m),
            Text(l.teacherCardSubjectsFrom, textAlign: TextAlign.center, style: context.type.label),
          ],
        ],
      ),
    );
  }
}
