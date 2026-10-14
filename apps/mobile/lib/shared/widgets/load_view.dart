import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../core/l10n/l10n.dart';
import '../../core/state/load_cubit.dart';
import 'lms.dart';

/// Shows a [LoadCubit]'s data, a quiet loader the first time ([skeleton]
/// when the screen gives one, shaped like what is coming), or the problem
/// with a retry. Pull down to reload once data is shown; a reload that
/// fails says so in a snack and leaves what was shown.
class LoadView<T> extends StatelessWidget {
  const LoadView({required this.builder, this.refreshable = true, this.skeleton, super.key});

  final Widget Function(BuildContext context, T data) builder;
  final bool refreshable;
  final Widget? skeleton;

  @override
  Widget build(BuildContext context) => BlocConsumer<LoadCubit<T>, LoadState<T>>(
    listenWhen: (before, now) => now.data != null && now.problem != null && before.problem == null,
    listener: (context, state) => showSnack(context, context.l10n.reloadFailed),
    builder: (context, state) {
      final data = state.data;
      if (data == null) {
        if (state.problem != null) {
          return ErrorState.of(state.problem!, onRetry: () => context.read<LoadCubit<T>>().load());
        }
        return skeleton ?? const Center(child: CircularProgressIndicator.adaptive());
      }
      final child = builder(context, data);
      if (!refreshable) return child;
      return RefreshIndicator.adaptive(onRefresh: () => context.read<LoadCubit<T>>().load(), child: child);
    },
  );
}

/// A problem with a retry, for screens that load on their own.
class ProblemView extends StatelessWidget {
  const ProblemView({required this.message, required this.onRetry, super.key});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => ErrorState(kind: ErrorKind.other, message: message, onRetry: onRetry);
}
