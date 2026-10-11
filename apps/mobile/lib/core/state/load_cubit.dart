import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter/foundation.dart';
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
/// problem. A reload keeps what is shown until the new data arrives. It
/// reloads by itself on [refreshOn] (the live signals it shows, `Live.on`)
/// and at the soonest of [refreshAt] still ahead (what the clock alone
/// changes, such as a lesson's room opening).
class LoadCubit<T> extends Cubit<LoadState<T>> {
  LoadCubit(this._fetch, {Stream<void>? refreshOn, this._refreshAt, DateTime Function()? clock})
    : _clock = clock ?? DateTime.now,
      super(LoadState<T>()) {
    _live = refreshOn?.listen((_) => load());
  }

  final Future<T> Function() _fetch;
  final Iterable<DateTime> Function(T data)? _refreshAt;
  final DateTime Function() _clock;
  StreamSubscription<void>? _live;
  Timer? _next;

  /// The longest single wait; a screen open for days sets the next one then.
  static const longestWait = Duration(hours: 6);

  /// How long until the soonest of [moments] still ahead (and a breath after it), or null.
  static Duration? waitUntilNext(Iterable<DateTime> moments, DateTime now) {
    final ahead = moments.where((m) => m.isAfter(now)).toList()..sort();
    if (ahead.isEmpty) return null;
    final wait = ahead.first.difference(now) + const Duration(seconds: 2);
    return wait > longestWait ? longestWait : wait;
  }

  @override
  Future<void> close() async {
    _next?.cancel();
    await _live?.cancel();
    return super.close();
  }

  void _schedule(T data) {
    _next?.cancel();
    final moments = _refreshAt;
    if (moments == null || isClosed) return;
    final wait = waitUntilNext(moments(data), _clock().toUtc());
    if (wait != null) _next = Timer(wait, load);
  }

  Future<void> load() async {
    emit(LoadState<T>(data: state.data));
    try {
      final data = await _fetch();
      if (!isClosed) {
        emit(LoadState<T>(data: data, loading: false));
        _schedule(data);
      }
    } on ApiProblem catch (problem) {
      if (!isClosed) emit(LoadState<T>(data: state.data, problem: problem, loading: false));
    } on Object catch (error) {
      // An answer the app cannot read (a field missing or of another type): the screen says something went
      // wrong and offers to try again, instead of loading for ever.
      debugPrint('LoadCubit<$T>: $error');
      if (!isClosed) emit(LoadState<T>(data: state.data, problem: ApiProblem.unreadable(), loading: false));
    }
  }
}
