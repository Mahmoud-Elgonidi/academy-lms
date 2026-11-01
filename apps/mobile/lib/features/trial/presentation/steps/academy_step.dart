import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/shake.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../auth/presentation/widgets/retry_wait.dart';
import '../trial_cubit.dart';
import '../widgets.dart';

/// Which academy: its code (the one asked for last on this phone comes back
/// by itself). An academy with no page, or not taking requests, says so
/// plainly; the network says it is the network.
class AcademyStep extends StatefulWidget {
  const AcademyStep({super.key});

  @override
  State<AcademyStep> createState() => _AcademyStepState();
}

class _AcademyStepState extends State<AcademyStep> {
  late final _code = TextEditingController(text: context.read<TrialCubit>().state.academyCode);
  String? _missing;
  int _shakes = 0;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _open() async {
    if (_code.text.trim().isEmpty) {
      Juice.wrong();
      setState(() {
        _missing = context.l10n.academyCodeMissing;
        _shakes++;
      });
      return;
    }
    setState(() => _missing = null);
    final ok = await context.read<TrialCubit>().openAcademy(_code.text);
    if (!ok && mounted) {
      Juice.wrong();
      setState(() => _shakes++);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocBuilder<TrialCubit, TrialState>(
      builder: (context, state) {
        final p = state.problem;
        final waiting = p?.status == 429;
        final error =
            _missing ??
            switch (p) {
              null => null,
              _ when waiting => null,
              _ when p.isNotFound => l.trialAcademyNotFound,
              _ => context.problemText(p),
            };
        return StepBody(
          title: l.trialAcademyTitle,
          lead: Lead(l.trialAcademyLead),
          badge: Icons.school_outlined,
          bottom: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (waiting) ...[
                RetryWait(key: ValueKey(p), seconds: p!.retryAfter ?? 60, onDone: context.read<TrialCubit>().clearProblem),
                const Gap(Space.s),
              ],
              SizedBox(
                height: 56,
                child: BusyButton(label: l.trialNext, busy: state.busy, onPressed: waiting ? null : _open),
              ),
            ],
          ),
          children: [
            Shake(
              count: _shakes,
              child: TextField(
                key: const Key('trial-academy'),
                controller: _code,
                textDirection: TextDirection.ltr,
                autocorrect: false,
                enableSuggestions: false,
                textInputAction: TextInputAction.go,
                onSubmitted: (_) => _open(),
                onChanged: (_) {
                  if (_missing != null || state.problem != null) {
                    setState(() => _missing = null);
                    context.read<TrialCubit>().clearProblem();
                  }
                },
                decoration: InputDecoration(labelText: l.academyCode, helperText: l.trialAcademyHint, helperMaxLines: 3),
              ),
            ),
            FieldError(error),
            if (state.busy) ...[
              const Gap(Space.l),
              Row(
                children: [
                  SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2.5, color: context.colors.brand)),
                  const Gap(Space.s),
                  Expanded(
                    child: Text(l.trialAcademyOpening, style: TextStyle(color: context.colors.muted)),
                  ),
                ],
              ),
            ],
          ],
        );
      },
    );
  }
}
