import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/network/api_problem.dart';
import '../data/trial_repository.dart';
import '../domain/trial.dart';

/// The steps of a free trial, in order. [academy] comes first only while the
/// academy is not known; [time] and [code] only while the family books its
/// own time.
enum TrialStep { academy, who, subject, wishes, time, contact, code, done }

/// Why the code was not taken, by what the family should do next.
enum CodeTrouble {
  /// Wrong: type it again (the API says how many tries are left).
  wrong,

  /// Too many wrong tries: only a new code helps.
  locked,

  /// Too old, or already used: only a new code helps.
  expired,
}

class TrialState extends Equatable {
  const TrialState({
    this.step = TrialStep.academy,
    this.academyCode = '',
    this.page,
    this.branding,
    this.draft = const TrialDraft(),
    this.slots,
    this.slotsKey,
    this.slotsLoading = false,
    this.slotsProblem,
    this.onlySuiting = true,
    this.chosen,
    this.timeNote,
    this.viaRequest = false,
    this.code,
    this.codeSpent = false,
    this.tries = 0,
    this.trouble,
    this.busy = false,
    this.problem,
    this.refusal,
    this.booked,
    this.requested = false,
  });

  final TrialStep step;

  /// The academy code typed (or remembered).
  final String academyCode;
  final AcademyPage? page;

  /// The academy's own look (`GET branding`), once known.
  final Map<String, dynamic>? branding;
  final TrialDraft draft;

  /// The times on offer for the draft (null: not asked yet).
  final List<TrialSlot>? slots;

  /// What [slots] were asked for (subject, age, teacher): other wishes ask again.
  final String? slotsKey;
  final bool slotsLoading;
  final ApiProblem? slotsProblem;

  /// Only the times on the days and hours the family gave.
  final bool onlySuiting;
  final TrialSlot? chosen;

  /// The API's words on why the chosen time went (taken meanwhile, too soon), over the fresh times.
  final String? timeNote;

  /// The family chose (or had) to send the request without a time.
  final bool viaRequest;
  final TrialCode? code;

  /// The code was used by a booking the API then refused: a new one is needed.
  final bool codeSpent;

  /// Wrong codes typed for [code].
  final int tries;
  final CodeTrouble? trouble;
  final bool busy;

  /// What went wrong on this step (field errors are read by the step's fields).
  final ApiProblem? problem;

  /// Why this trial cannot be booked here (the API's words), with ways out.
  final String? refusal;
  final BookedTrial? booked;

  /// The request went to the academy (no time booked).
  final bool requested;

  /// The academy lets this family pick its own time: a subject chosen, or help asked for where the academy starts
  /// such trials with its own subject.
  bool get canPick => (page?.trials.familyBooks ?? false) && (!draft.helpChoose || page?.trials.helpChooseSubjectId != null);

  /// The family picks its own time and proves its number with a code.
  bool get selfBooks => canPick && !viaRequest;

  /// The subject whose times are on offer (the academy's own, for help choosing).
  String? get slotsSubject => draft.helpChoose ? page?.trials.helpChooseSubjectId : draft.subjectId;

  /// The steps this family goes through (the academy's step is outside the count).
  List<TrialStep> get steps => [
    TrialStep.who,
    TrialStep.subject,
    TrialStep.wishes,
    if (selfBooks) TrialStep.time,
    TrialStep.contact,
    if (selfBooks) TrialStep.code,
  ];

  /// The days with times, soonest first; only those that suit the family when [onlySuiting] and some do.
  List<SlotDay> get days => SlotDay.group(shownSlots);

  /// Whether any time on offer suits the days and hours the family gave.
  bool get anySuits => (slots ?? const []).any(draft.suits);

  List<TrialSlot> get shownSlots {
    final all = slots ?? const <TrialSlot>[];
    if (!draft.hasWishes || !onlySuiting || !anySuits) return all;
    return [
      for (final s in all)
        if (draft.suits(s)) s,
    ];
  }

  TrialState copyWith({
    TrialStep? step,
    String? academyCode,
    AcademyPage? page,
    Map<String, dynamic>? branding,
    TrialDraft? draft,
    List<TrialSlot>? Function()? slots,
    String? Function()? slotsKey,
    bool? slotsLoading,
    ApiProblem? Function()? slotsProblem,
    bool? onlySuiting,
    TrialSlot? Function()? chosen,
    String? Function()? timeNote,
    bool? viaRequest,
    TrialCode? Function()? code,
    bool? codeSpent,
    int? tries,
    CodeTrouble? Function()? trouble,
    bool? busy,
    ApiProblem? Function()? problem,
    String? Function()? refusal,
    BookedTrial? Function()? booked,
    bool? requested,
  }) => TrialState(
    step: step ?? this.step,
    academyCode: academyCode ?? this.academyCode,
    page: page ?? this.page,
    branding: branding ?? this.branding,
    draft: draft ?? this.draft,
    slots: slots != null ? slots() : this.slots,
    slotsKey: slotsKey != null ? slotsKey() : this.slotsKey,
    slotsLoading: slotsLoading ?? this.slotsLoading,
    slotsProblem: slotsProblem != null ? slotsProblem() : this.slotsProblem,
    onlySuiting: onlySuiting ?? this.onlySuiting,
    chosen: chosen != null ? chosen() : this.chosen,
    timeNote: timeNote != null ? timeNote() : this.timeNote,
    viaRequest: viaRequest ?? this.viaRequest,
    code: code != null ? code() : this.code,
    codeSpent: codeSpent ?? this.codeSpent,
    tries: tries ?? this.tries,
    trouble: trouble != null ? trouble() : this.trouble,
    busy: busy ?? this.busy,
    problem: problem != null ? problem() : this.problem,
    refusal: refusal != null ? refusal() : this.refusal,
    booked: booked != null ? booked() : this.booked,
    requested: requested ?? this.requested,
  );

  @override
  List<Object?> get props => [
    step,
    academyCode,
    page,
    branding,
    draft,
    slots,
    slotsKey,
    slotsLoading,
    slotsProblem,
    onlySuiting,
    chosen,
    timeNote,
    viaRequest,
    code,
    codeSpent,
    tries,
    trouble,
    busy,
    problem,
    refusal,
    booked,
    requested,
  ];
}

/// A family's free trial, a step at a time: the academy (unless known), who
/// is learning, the subject (or help choosing), the days and hours that suit
/// them and the teacher they would rather have; then, while the academy lets
/// families book, a time from those on offer, the number and the code sent
/// to it, and the booking; else the request alone. Nothing typed is lost
/// going back and forth, nor when the API refuses: a time taken meanwhile
/// brings the fresh times with the API's words; a field it refuses brings
/// its step back with them.
class TrialCubit extends Cubit<TrialState> {
  TrialCubit(
    this._repo, {
    required this.timezone,
    this._recall,
    this._remember,
    DateTime Function()? clock,
    Future<void> Function(Duration)? wait,
    String? academy,
  }) : _clock = clock ?? DateTime.now,
       _wait = wait ?? Future<void>.delayed,
       super(TrialState(academyCode: academy ?? ''));

  final TrialRepository _repo;

  /// The family's clock (the phone's zone), for the times on offer and the request.
  final String? timezone;
  final Future<String?> Function()? _recall;
  final Future<void> Function(String slug)? _remember;
  final DateTime Function() _clock;
  final Future<void> Function(Duration) _wait;

  /// A form sent sooner than this after the page was read is dropped by the API as a machine's.
  static const formFresh = Duration(seconds: 4);

  /// A page read longer ago than this is read again before sending (its token lasts six hours).
  static const formStale = Duration(hours: 5);

  String get _slug => state.page?.slug ?? state.academyCode;

  /// Opens the academy given (or the one asked for last on this phone) straight away.
  Future<void> start() async {
    final known = state.academyCode.isNotEmpty ? state.academyCode : (await _recall?.call() ?? '');
    if (known.isEmpty) return;
    emit(state.copyWith(academyCode: known));
    await openAcademy(known);
  }

  /// Reads the academy's page; on to who is learning.
  Future<bool> openAcademy(String code) async {
    final slug = code.trim().toLowerCase();
    if (slug.isEmpty || state.busy) return false;
    emit(state.copyWith(academyCode: slug, busy: true, problem: () => null));
    try {
      final page = await _repo.page(slug, now: _clock());
      emit(state.copyWith(page: page, busy: false, step: TrialStep.who));
      await _remember?.call(page.slug);
      final branding = await _repo.branding(page.slug);
      if (branding != null && !isClosed) emit(state.copyWith(branding: branding));
      return true;
    } on ApiProblem catch (p) {
      emit(state.copyWith(busy: false, problem: () => p, step: TrialStep.academy));
      return false;
    }
  }

  /// Another academy: back to its code (what was typed about the learner stays).
  void changeAcademy() => emit(TrialState(academyCode: state.academyCode, draft: state.draft, step: TrialStep.academy));

  void chooseLearner(Learner learner) => emit(
    state.copyWith(
      draft: state.draft.copyWith(learner: learner),
      problem: () => null,
    ),
  );

  /// Who is learning, as typed; on to the subject.
  void saveWho({required String name, String childName = '', int? childAge}) {
    emit(
      state.copyWith(
        draft: state.draft.copyWith(name: name, childName: childName, childAge: childAge),
        step: TrialStep.subject,
        problem: () => null,
      ),
    );
  }

  /// Keeps what is typed without moving on (going back keeps it too).
  void keep({String? name, String? childName, int? childAge, String? phone, String? email}) => emit(
    state.copyWith(
      draft: state.draft.copyWith(name: name, childName: childName, childAge: childAge, phone: phone, email: email),
    ),
  );

  void chooseSubject(String id) => _toWishes(state.draft.copyWith(subjectId: () => id, helpChoose: false));

  /// "Help me choose": the academy chooses the subject with the family, and calls them.
  void helpMeChoose() => _toWishes(state.draft.copyWith(subjectId: () => null, helpChoose: true));

  void _toWishes(TrialDraft draft) {
    final subjectChanged = draft.subjectId != state.draft.subjectId || draft.helpChoose != state.draft.helpChoose;
    emit(
      state.copyWith(
        draft: draft,
        step: TrialStep.wishes,
        problem: () => null,
        // Another subject has other times.
        slots: subjectChanged ? () => null : null,
        chosen: subjectChanged ? () => null : null,
        timeNote: subjectChanged ? () => null : null,
        refusal: () => null,
      ),
    );
  }

  /// The days, hours and teacher as they are picked (kept going back and forth).
  void keepWishes({Set<int>? days, DayPart? dayPart, TeacherGender? teacherGender}) => emit(
    state.copyWith(
      draft: state.draft.copyWith(days: days, dayPart: dayPart, teacherGender: teacherGender),
    ),
  );

  /// On to the times (or the number), with the days, hours and teacher kept.
  Future<void> saveWishes() async {
    emit(state.copyWith(step: state.selfBooks ? TrialStep.time : TrialStep.contact, onlySuiting: true, problem: () => null));
    if (state.selfBooks && (state.slots == null || state.slotsKey != _slotsKey)) await loadSlots();
  }

  String get _slotsKey => '${state.slotsSubject}|${state.draft.birthYear(_clock().year)}|${state.draft.teacherGender.name}';

  /// The times on offer for the subject, the learner's age and the teacher they would rather have.
  Future<void> loadSlots() async {
    final page = state.page;
    final subject = state.slotsSubject;
    if (page == null || subject == null) return;
    emit(state.copyWith(slotsLoading: true, slotsProblem: () => null));
    try {
      final slots = await _repo.slots(
        page.slug,
        subjectId: subject,
        birthYear: state.draft.birthYear(_clock().year),
        teacherGender: state.draft.teacherGender.api,
        timezone: timezone,
      );
      final chosen = state.chosen;
      emit(
        state.copyWith(
          slots: () => slots,
          slotsKey: () => _slotsKey,
          slotsLoading: false,
          // A time still on offer stays chosen.
          chosen: () => chosen != null && slots.contains(chosen) ? chosen : null,
        ),
      );
    } on ApiProblem catch (p) {
      emit(state.copyWith(slotsLoading: false, slotsProblem: () => p));
    }
  }

  void showAllTimes(bool all) => emit(state.copyWith(onlySuiting: !all));

  void chooseSlot(TrialSlot slot) => emit(state.copyWith(chosen: () => slot, timeNote: () => null));

  /// Any teacher, when none of the one they would rather have is free.
  Future<void> anyTeacher() async {
    emit(state.copyWith(draft: state.draft.copyWith(teacherGender: TeacherGender.any)));
    await loadSlots();
  }

  /// The chosen time; on to the number.
  void confirmTime() {
    if (state.chosen == null) return;
    emit(state.copyWith(step: TrialStep.contact, problem: () => null));
  }

  /// No time suits them (or none is free): the request goes to the academy, which arranges one.
  void sendInstead() => emit(state.copyWith(viaRequest: true, step: TrialStep.contact, problem: () => null, refusal: () => null));

  /// Back to choosing a time themselves, from the number's step.
  Future<void> pickTimeAfterAll() async {
    emit(state.copyWith(viaRequest: false, step: TrialStep.time, problem: () => null));
    if (state.slots == null) await loadSlots();
  }

  /// The number (and an email, if they gave one): a code to it while the family books, else the request goes.
  Future<bool> submitContact({required String phone, String email = ''}) async {
    emit(
      state.copyWith(
        draft: state.draft.copyWith(phone: phone, email: email),
      ),
    );
    return state.selfBooks ? sendCode() : sendRequest();
  }

  /// A (new) code to the number given.
  Future<bool> sendCode() async {
    if (state.busy) return false;
    emit(state.copyWith(busy: true, problem: () => null));
    try {
      final code = await _repo.sendCode(_slug, state.draft.phone, now: _clock());
      emit(
        state.copyWith(
          busy: false,
          code: () => code,
          codeSpent: false,
          tries: 0,
          trouble: () => null,
          refusal: () => null,
          step: TrialStep.code,
        ),
      );
      return true;
    } on ApiProblem catch (p) {
      emit(state.copyWith(busy: false, problem: () => p));
      return false;
    }
  }

  /// Another number: back to the number's step (the code sent stays unused).
  void changeNumber() =>
      emit(state.copyWith(step: TrialStep.contact, code: () => null, trouble: () => null, problem: () => null, refusal: () => null));

  /// Books the chosen time with the code. True once booked.
  Future<bool> book(String code) async {
    final sent = state.code;
    final slot = state.chosen;
    final page = state.page;
    if (sent == null || slot == null || page == null || state.busy) return false;
    emit(state.copyWith(busy: true, problem: () => null, trouble: () => null, refusal: () => null));
    try {
      final booked = await _repo.book(
        page.slug,
        state.draft.toJson(thisYear: _clock().year, timezone: timezone),
        challengeId: sent.challengeId,
        code: code,
        startsAt: slot.startsAt,
      );
      emit(state.copyWith(busy: false, booked: () => booked, step: TrialStep.done));
      return true;
    } on ApiProblem catch (p) {
      await _refused(p, slot);
      return false;
    }
  }

  Future<void> _refused(ApiProblem p, TrialSlot slot) async {
    if (p.field('code') != null) {
      final tries = state.tries + 1;
      final expiresAt = state.code?.expiresAt;
      final trouble = expiresAt != null && !_clock().isBefore(expiresAt)
          ? CodeTrouble.expired
          : tries >= TrialCode.maxTries
          ? CodeTrouble.locked
          : CodeTrouble.wrong;
      emit(state.copyWith(busy: false, problem: () => p, tries: tries, trouble: () => trouble));
      return;
    }
    if (!p.isValidation) {
      // The network, a wait (429) or the server: nothing was decided, try again.
      emit(state.copyWith(busy: false, problem: () => p));
      return;
    }
    // The code was right and is spent now: the next try asks for a new one.
    final why = p.field('starts_at');
    if (why != null) {
      emit(state.copyWith(codeSpent: true));
      await loadSlots();
      final stillThere = state.slotsProblem == null && (state.slots ?? const []).contains(slot);
      if (!stillThere && state.slotsProblem == null) {
        // Taken meanwhile, or too soon now: the fresh times, with the API's words over them.
        emit(state.copyWith(busy: false, step: TrialStep.time, chosen: () => null, timeNote: () => why, code: () => null));
      } else {
        // Not about the time: about this learner or this number here.
        emit(state.copyWith(busy: false, refusal: () => why));
      }
      return;
    }
    emit(state.copyWith(busy: false, codeSpent: true, code: () => null, problem: () => p, step: stepFor(p)));
  }

  /// The step whose field the API refused.
  static TrialStep stepFor(ApiProblem p) {
    for (final field in p.errors.keys) {
      switch (field) {
        case 'name' || 'child_name' || 'child_birth_year' || 'for_child':
          return TrialStep.who;
        case 'subject_id' || 'help_choose':
          return TrialStep.subject;
        case 'preferred_days' || 'preferred_from' || 'preferred_to' || 'teacher_gender':
          return TrialStep.wishes;
        case 'starts_at':
          return TrialStep.time;
      }
    }
    return TrialStep.contact;
  }

  /// The request without a time: the academy answers it within its hours.
  Future<bool> sendRequest() async {
    final page = state.page;
    if (page == null || state.busy) return false;
    emit(state.copyWith(busy: true, problem: () => null, refusal: () => null));
    try {
      var current = page;
      final age = _clock().difference(current.loadedAt);
      if (age > formStale) {
        current = await _repo.page(current.slug, now: _clock());
        emit(state.copyWith(page: current));
      } else if (age < formFresh) {
        await _wait(formFresh - age);
      }
      await _repo.request(
        current.slug,
        state.draft.toJson(thisYear: _clock().year, timezone: timezone),
        formToken: current.formToken,
      );
      emit(state.copyWith(busy: false, requested: true, step: TrialStep.done));
      return true;
    } on ApiProblem catch (p) {
      final step = p.isValidation ? stepFor(p) : state.step;
      emit(state.copyWith(busy: false, problem: () => p, step: step));
      return false;
    }
  }

  /// One step back; false at the first step (the page closes).
  bool back() {
    final steps = state.steps;
    final at = steps.indexOf(state.step);
    if (state.step == TrialStep.done || state.step == TrialStep.academy) return false;
    if (at <= 0) {
      // Before who is learning: the academy, when it was typed here.
      return false;
    }
    var previous = steps[at - 1];
    // Back from the number after choosing to send the request: the times again, when the family books.
    if (state.step == TrialStep.contact && state.viaRequest && state.canPick) {
      emit(state.copyWith(viaRequest: false));
      previous = TrialStep.time;
    }
    emit(state.copyWith(step: previous, problem: () => null, refusal: () => null));
    return true;
  }

  /// Another child of the same family: the parent's name and number stay; the rest starts again.
  void anotherChild() => emit(
    TrialState(
      step: TrialStep.who,
      academyCode: state.academyCode,
      page: state.page,
      branding: state.branding,
      draft: state.draft.anotherChild(),
    ),
  );

  /// Straight to a step to change it (from the summary before sending).
  void edit(TrialStep step) => emit(state.copyWith(step: step, problem: () => null, refusal: () => null));

  void clearProblem() => emit(state.copyWith(problem: () => null));
}
