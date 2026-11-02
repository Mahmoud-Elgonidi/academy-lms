import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/network/api_problem.dart';
import '../../../consent/data/consent_repository.dart';
import '../../../consent/domain/consent.dart';
import '../../data/family_repository.dart';
import '../../domain/learner.dart';

/// The two steps of adding a child: about them, then the guardian's consent.
enum AddChildStep { about, consent }

/// How the child's birth is given: the day, or the year alone.
enum BirthBy { date, year }

/// A field the guardian must fix before going on (said under it).
enum AddChildField { name, birth }

class AddChildState extends Equatable {
  const AddChildState({
    this.step = AddChildStep.about,
    this.name = '',
    this.birthBy = BirthBy.date,
    this.birthDate,
    this.birthYear,
    this.gender,
    this.errors = const {},
    this.wording,
    this.loadingWording = false,
    this.agreed = false,
    this.agreeMissing = false,
    this.busy = false,
    this.problem,
    this.added,
  });

  final AddChildStep step;
  final String name;
  final BirthBy birthBy;
  final DateTime? birthDate;
  final int? birthYear;

  /// female, male, or not said.
  final String? gender;

  /// What to fix, in the app's words or the API's.
  final Map<AddChildField, String> errors;

  /// The consent wording for a child about to be added (`{child}` where the name goes).
  final ConsentText? wording;
  final bool loadingWording;
  final bool agreed;

  /// "I agree" was not ticked when adding was pressed.
  final bool agreeMissing;
  final bool busy;
  final ApiProblem? problem;
  final AddedChild? added;

  String get trimmedName => name.trim().replaceAll(RegExp(r'\s+'), ' ');

  bool get hasBirth => birthBy == BirthBy.date ? birthDate != null : birthYear != null;

  AddChildState copyWith({
    AddChildStep? step,
    String? name,
    BirthBy? birthBy,
    DateTime? Function()? birthDate,
    int? Function()? birthYear,
    String? Function()? gender,
    Map<AddChildField, String>? errors,
    ConsentText? Function()? wording,
    bool? loadingWording,
    bool? agreed,
    bool? agreeMissing,
    bool? busy,
    ApiProblem? Function()? problem,
    AddedChild? Function()? added,
  }) => AddChildState(
    step: step ?? this.step,
    name: name ?? this.name,
    birthBy: birthBy ?? this.birthBy,
    birthDate: birthDate == null ? this.birthDate : birthDate(),
    birthYear: birthYear == null ? this.birthYear : birthYear(),
    gender: gender == null ? this.gender : gender(),
    errors: errors ?? this.errors,
    wording: wording == null ? this.wording : wording(),
    loadingWording: loadingWording ?? this.loadingWording,
    agreed: agreed ?? this.agreed,
    agreeMissing: agreeMissing ?? this.agreeMissing,
    busy: busy ?? this.busy,
    problem: problem == null ? this.problem : problem(),
    added: added == null ? this.added : added(),
  );

  @override
  List<Object?> get props => [
    step,
    name,
    birthBy,
    birthDate,
    birthYear,
    gender,
    errors,
    wording,
    loadingWording,
    agreed,
    agreeMissing,
    busy,
    problem,
    added,
  ];
}

/// «إضافة طفل» (docs/architecture/02, section 27, J5a): the child's name, birth
/// (a day, or a year alone) and, if the guardian likes, gender; then the
/// academy's consent wording with the name in it, agreed to before adding.
/// Once added, the start flow takes the child. A refusal about a field (the
/// same child twice) goes back to it in the API's words.
class AddChildCubit extends Cubit<AddChildState> {
  AddChildCubit(this._family, this._consents, {DateTime Function()? clock}) : _clock = clock ?? DateTime.now, super(const AddChildState());

  final FamilyRepository _family;
  final ConsentRepository _consents;
  final DateTime Function() _clock;

  /// The words the app says itself when a field is missing (the page fills them in its language).
  static const nameShort = 'name-short';
  static const birthMissing = 'birth-missing';

  DateTime get today => _clock();

  void setName(String name) => emit(state.copyWith(name: name, errors: _without(AddChildField.name), problem: () => null));

  void birthBy(BirthBy by) => emit(state.copyWith(birthBy: by, errors: _without(AddChildField.birth)));

  void setDate(DateTime day) => emit(state.copyWith(birthDate: () => day, errors: _without(AddChildField.birth)));

  void setYear(int year) => emit(state.copyWith(birthYear: () => year, errors: _without(AddChildField.birth)));

  /// Picks [gender], or leaves it unsaid when it is picked again.
  void setGender(String gender) => emit(state.copyWith(gender: () => state.gender == gender ? null : gender));

  void agree(bool agreed) => emit(state.copyWith(agreed: agreed, agreeMissing: false));

  Map<AddChildField, String> _without(AddChildField field) => {...state.errors}..remove(field);

  /// On to the consent: the name and birth checked here, then the wording read (once).
  Future<void> next() async {
    final errors = <AddChildField, String>{
      if (state.trimmedName.length < 2) AddChildField.name: nameShort,
      if (!state.hasBirth) AddChildField.birth: birthMissing,
    };
    if (errors.isNotEmpty) {
      emit(state.copyWith(errors: errors));
      return;
    }
    emit(state.copyWith(step: AddChildStep.consent, errors: const {}, problem: () => null));
    if (state.wording == null) await loadWording();
  }

  Future<void> loadWording() async {
    emit(state.copyWith(loadingWording: true, problem: () => null));
    try {
      final consents = await _consents.load();
      if (isClosed) return;
      final wording = consents.newChild;
      emit(state.copyWith(loadingWording: false, wording: () => wording, problem: () => wording == null ? ApiProblem.unreadable() : null));
    } on ApiProblem catch (p) {
      if (!isClosed) emit(state.copyWith(loadingWording: false, problem: () => p));
    }
  }

  /// Back to the child's details (the consent stays ticked).
  bool back() {
    if (state.busy || state.step == AddChildStep.about) return false;
    emit(state.copyWith(step: AddChildStep.about, problem: () => null));
    return true;
  }

  Future<void> submit() async {
    final wording = state.wording;
    if (state.busy || wording == null) return;
    if (!state.agreed) {
      emit(state.copyWith(agreeMissing: true));
      return;
    }
    emit(state.copyWith(busy: true, problem: () => null));
    final date = state.birthDate;
    try {
      final added = await _family.addChild(
        name: state.trimmedName,
        version: wording.version,
        birthDate: state.birthBy == BirthBy.date && date != null ? _day(date) : null,
        birthYear: state.birthBy == BirthBy.year ? state.birthYear : null,
        gender: state.gender,
      );
      if (!isClosed) emit(state.copyWith(busy: false, added: () => added));
    } on ApiProblem catch (p) {
      if (isClosed) return;
      final name = p.field('name');
      final birth = p.field('birth_date') ?? p.field('birth_year');
      if (name != null || birth != null) {
        emit(state.copyWith(busy: false, step: AddChildStep.about, errors: {AddChildField.name: ?name, AddChildField.birth: ?birth}));
        return;
      }
      // A newer wording since it was read: read it again, to be agreed to afresh.
      if (p.field('version') != null) {
        emit(state.copyWith(busy: false, agreed: false, wording: () => null, problem: () => p));
        await loadWording();
        if (!isClosed) emit(state.copyWith(problem: () => p));
        return;
      }
      emit(state.copyWith(busy: false, problem: () => p));
    }
  }

  static String _day(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
}
