import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/audio_clip.dart';
import '../../data/homework_repository.dart';
import '../../domain/homework.dart';

/// One hand-in as it was sent: the words, a recording to play, a picture
/// to show, or the name of a file that opens on the website.
class SubmissionView extends StatefulWidget {
  const SubmissionView({required this.submission, this.title, super.key});

  final Submission submission;
  final String? title;

  @override
  State<SubmissionView> createState() => _SubmissionViewState();
}

class _SubmissionViewState extends State<SubmissionView> {
  Future<Uint8List>? _picture;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final s = widget.submission;
    final file = s.file;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: c.chip, borderRadius: BorderRadius.circular(context.shape.card - 6)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(widget.title ?? l.yourSubmission, style: context.text.labelMedium),
          if (s.at != null) Text(l.handedInAt(s.at!.when(context)), style: context.text.bodySmall),
          if ((s.body ?? '').isNotEmpty) ...[const SizedBox(height: 4), Text(s.body!, style: context.text.bodyMedium)],
          if (file != null) ...[
            const SizedBox(height: 8),
            if (file.isAudio)
              AudioClip(load: () => context.read<HomeworkRepository>().file(s.id), mime: file.mime)
            else if (file.isImage)
              _picture == null
                  ? OutlinedButton.icon(
                      onPressed: () => setState(() => _picture = context.read<HomeworkRepository>().file(s.id)),
                      icon: const Icon(Icons.image_outlined),
                      label: Text(l.showPicture),
                    )
                  : FutureBuilder<Uint8List>(
                      future: _picture,
                      builder: (context, snap) => snap.hasData
                          ? ClipRRect(
                              borderRadius: BorderRadius.circular(10),
                              child: Image.memory(snap.data!, fit: BoxFit.contain, height: 260),
                            )
                          : const Padding(
                              padding: EdgeInsets.all(12),
                              child: Center(child: CircularProgressIndicator.adaptive()),
                            ),
                    )
            else
              Row(
                children: [
                  const Icon(Icons.attach_file_rounded, size: 18),
                  const SizedBox(width: 4),
                  Flexible(child: Text(file.name, style: context.text.bodyMedium)),
                  const SizedBox(width: 6),
                  Flexible(child: Text(l.fileOnWeb, style: context.text.bodySmall)),
                ],
              ),
          ],
        ],
      ),
    );
  }
}
