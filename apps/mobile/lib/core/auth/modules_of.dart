import 'package:flutter/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import 'modules.dart';
import 'session_cubit.dart';

export 'modules.dart' show Feature;

extension ModulesOf on BuildContext {
  /// Whether [feature] is on for the open academy, rebuilding when that changes (in `build` only).
  bool hasModule(Feature feature) => select<SessionCubit, bool>((s) => s.state.has(feature));

  /// The same, once (in callbacks).
  bool moduleOn(Feature feature) => read<SessionCubit>().state.has(feature);
}
