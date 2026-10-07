import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../network/api_problem.dart';

class LoadState<T> extends Equatable {
  const LoadState({this.data, this.problem, this.loading = true});

  final T? data;
  final ApiProblem? problem;
  final bool loading;

  @override
  List<Object?> get props => [data, problem, loading];
}

/// One screen's data from the API: loading, loaded, or failed with the
/// problem. A reload keeps what is shown until the new data arrives.
class LoadCubit<T> extends Cubit<LoadState<T>> {
  LoadCubit(this._fetch) : super(LoadState<T>());

  final Future<T> Function() _fetch;

  Future<void> load() async {
    emit(LoadState<T>(data: state.data));
    try {
      final data = await _fetch();
      if (!isClosed) emit(LoadState<T>(data: data, loading: false));
    } on ApiProblem catch (problem) {
      if (!isClosed) emit(LoadState<T>(data: state.data, problem: problem, loading: false));
    }
  }
}
