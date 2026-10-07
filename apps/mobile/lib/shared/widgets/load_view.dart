import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../app/theme/app_theme.dart';
import '../../core/l10n/l10n.dart';
import '../../core/state/load_cubit.dart';

/// Shows a [LoadCubit]'s data, a quiet loader the first time, or the
/// problem with a retry. Pull down to reload once data is shown.
class LoadView<T> extends StatelessWidget {
  const LoadView({required this.builder, this.refreshable = true, super.key});

  final Widget Function(BuildContext context, T data) builder;
  final bool refreshable;

  @override
  Widget build(BuildContext context) => BlocBuilder<LoadCubit<T>, LoadState<T>>(
    builder: (context, state) {
      final data = state.data;
      if (data == null) {
        if (state.problem != null) {
          return ProblemView(message: context.problemText(state.problem!), onRetry: () => context.read<LoadCubit<T>>().load());
        }
        return const Center(child: CircularProgressIndicator.adaptive());
      }
      final child = builder(context, data);
      if (!refreshable) return child;
      return RefreshIndicator.adaptive(onRefresh: () => context.read<LoadCubit<T>>().load(), child: child);
    },
  );
}

class ProblemView extends StatelessWidget {
  const ProblemView({required this.message, required this.onRetry, super.key});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.wifi_off_rounded, size: 40, color: context.colors.faint),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center, style: context.text.bodyLarge),
          const SizedBox(height: 16),
          OutlinedButton(onPressed: onRetry, child: Text(context.l10n.retry)),
        ],
      ),
    ),
  );
}
