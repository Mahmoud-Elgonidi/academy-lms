import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/teaching_repository.dart';
import '../../domain/teaching.dart';

/// The teacher's own Zoom or Meet link (the same as "Your link" on /teach's today page): when the
/// academy's lessons run on outside links, families join through it and a lesson cannot open without it.
class MeetingLinkPage extends StatelessWidget {
  const MeetingLinkPage({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (c) => LoadCubit<MeetingLinkInfo>(c.read<TeachingRepository>().meetingLink)..load(),
      child: Scaffold(
        appBar: AppBar(title: Text(context.l10n.meetingLinkTitle)),
        body: LoadView<MeetingLinkInfo>(
          builder: (context, info) => ListView(
            padding: pagePadding,
            children: staggered(context, [
              if (info.used && info.url == null) ...[
                Note(context.l10n.meetingLinkNeeded, icon: Icons.info_outline_rounded),
                const SizedBox(height: 12),
              ],
              _LinkForm(info: info),
            ]),
          ),
        ),
      ),
    );
  }
}

class _LinkForm extends StatefulWidget {
  const _LinkForm({required this.info});

  final MeetingLinkInfo info;

  @override
  State<_LinkForm> createState() => _LinkFormState();
}

class _LinkFormState extends State<_LinkForm> {
  late final _url = TextEditingController(text: widget.info.url ?? '');
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _url.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final l = context.l10n;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final saved = await context.read<TeachingRepository>().saveMeetingLink(_url.text);
      if (!mounted) return;
      showSnack(context, saved.url == null ? l.meetingLinkRemoved : l.meetingLinkSaved);
      setState(() => _busy = false);
      await context.read<LoadCubit<MeetingLinkInfo>>().load();
    } on ApiProblem catch (p) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = context.problemText(p);
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(widget.info.hint, style: context.text.bodySmall),
          const SizedBox(height: 12),
          TextField(
            key: const Key('meeting-link'),
            controller: _url,
            keyboardType: TextInputType.url,
            textDirection: TextDirection.ltr,
            maxLength: 500,
            decoration: InputDecoration(labelText: widget.info.label, hintText: 'https://meet.google.com/…', errorText: _error),
          ),
          const SizedBox(height: 8),
          FilledButton(onPressed: _busy ? null : _save, child: Text(context.l10n.meetingLinkSave)),
        ],
      ),
    );
  }
}
