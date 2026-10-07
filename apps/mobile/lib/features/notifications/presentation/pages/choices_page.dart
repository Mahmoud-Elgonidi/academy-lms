import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/notifications_repository.dart';
import '../../domain/notifications.dart';

/// What reaches the person and how (the same choices as "My settings" on the website).
class ChoicesPage extends StatelessWidget {
  const ChoicesPage({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<List<NotificationChoice>>(c.read<NotificationsRepository>().choices)..load(),
    child: Scaffold(
      appBar: AppBar(title: Text(context.l10n.notificationChoices)),
      body: LoadView<List<NotificationChoice>>(
        builder: (context, choices) => ListView(
          padding: pagePadding,
          children: [
            for (final ch in choices) ...[
              LmsCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(ch.label, style: context.text.titleSmall),
                    if ((ch.hint ?? '').isNotEmpty) Text(ch.hint!, style: context.text.bodySmall),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        for (final o in ch.options)
                          ChoiceChip(
                            label: Text(o.label),
                            selected: o.value == ch.value,
                            onSelected: (_) async {
                              try {
                                await context.read<NotificationsRepository>().choose(ch.key, o.value);
                                if (!context.mounted) return;
                                showSnack(context, context.l10n.saved);
                                await context.read<LoadCubit<List<NotificationChoice>>>().load();
                              } on ApiProblem catch (p) {
                                if (context.mounted) showSnack(context, context.problemText(p));
                              }
                            },
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 10),
            ],
          ],
        ),
      ),
    ),
  );
}
