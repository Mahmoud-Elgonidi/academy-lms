import 'package:academy_app/core/network/api_problem.dart';
import 'package:academy_app/core/state/load_cubit.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('an answer the app cannot read ends the wait with a problem to retry, keeping what was shown', () async {
    var calls = 0;
    final cubit = LoadCubit<String>(() async {
      calls++;
      if (calls == 1) return 'first';
      // A field the app expects as text came as a number.
      return (<String, Object?>{'name': 3}['name'])! as String;
    });
    await cubit.load();
    expect(cubit.state.data, 'first');

    await cubit.load();
    expect(cubit.state.loading, isFalse);
    expect(cubit.state.data, 'first');
    expect(cubit.state.problem?.type, 'unreadable');
    expect(cubit.state.problem?.isOffline, isFalse);
    await cubit.close();
  });

  test('the API\'s own problem is kept as it came', () async {
    final cubit = LoadCubit<String>(() async => throw const ApiProblem(type: 'forbidden', title: 'ممنوع.', status: 403));
    await cubit.load();
    expect(cubit.state.problem?.status, 403);
    expect(cubit.state.problem?.message, 'ممنوع.');
    await cubit.close();
  });

  test('a write\'s answer is shown at once, and a load asked before it does not bring the old page back', () async {
    var answer = 'old';
    final cubit = LoadCubit<String>(() async {
      final was = answer;
      await Future<void>.delayed(const Duration(milliseconds: 10));
      return was;
    });
    final loading = cubit.load();
    cubit.show('marked');
    await loading;
    expect(cubit.state.data, 'marked');
    expect(cubit.state.loading, isFalse);
    answer = 'next';
    await cubit.load();
    expect(cubit.state.data, 'next');
    await cubit.close();
  });
}
