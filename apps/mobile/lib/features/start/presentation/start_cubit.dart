import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/network/api_problem.dart';
import '../../consent/data/consent_repository.dart';
import '../../consent/domain/consent.dart';
import '../data/start_repository.dart';
import '../domain/start.dart';

/// The steps of starting a course, in their order. [off] (the academy
/// enrols itself), [consent] (asked only when the API asks) and [blocked]
/// (a refusal with its own way on) stand outside the count.
enum StartStep { loading, failed, off, learner, subject, plan, teacher, group, times, review, consent, blocked }

/// A refusal the family cannot fix here, with what it says and the course it is about.
class StartBlock extends Equatable {
  const StartBlock({required this.reason, required this.text, this.enrollmentId, this.code});

  final StartReason reason;
  final String text;
  final String? enrollmentId;

  /// The API's own reason (course_paused, academy_full…).
  final String? code;

  @override
  List<Object?> get props => [reason, text, enrollmentId, code];
}

/// A word on a step: why the family is back on it (a time taken meanwhile, a new price), or what just happened.
class StartNote extends Equatable {
  const StartNote(this.text, {this.warning = true});

  final String text;
  final bool warning;

  @override
  List<Object?> get props => [text, warning];
}

class StartState extends Equatable {
  const StartState({
    this.step = StartStep.loading,
    this.history = const [],
    this.options,
    this.problem,
    this.learnerId,
    this.subjectId,
    this.planId,
    this.teacherId,
    this.groupId,
    this.recommendedPlanId,
    this.slots,
    this.slotsLoading = false,
    this.slotsProblem,
    this.picked = const [],
    this.dayFull = false,
    this.note,
    this.busy = false,
    this.submitProblem,
    this.block,
    this.consent,
    this.consentProblem,
    this.started,
    this.priceChanged = false,
  });

  final StartStep step;

  /// The steps passed through, for going back.
  final List<StartStep> history;
  final StartOptions? options;
  final ApiProblem? problem;
  final String? learnerId;
  final String? subjectId;
  final String? planId;
  final String? teacherId;
  final String? groupId;

  /// The plan the trial's teacher recommended (marked on its card).
  final String? recommendedPlanId;
  final StartSlots? slots;
  final bool slotsLoading;
  final ApiProblem? slotsProblem;

  /// The weekly times chosen, by key, in the order picked.
  final List<String> picked;

  /// A time was tapped on a new day with every time chosen already.
  final bool dayFull;
  final StartNote? note;
  final bool busy;
  final ApiProblem? submitProblem;
  final StartBlock? block;

  /// The child's consent, asked before the course can start.
  final ChildConsent? consent;
  final ApiProblem? consentProblem;
  final StartedCourse? started;

  /// The price changed since it was seen: the review says so before confirming again.
  final bool priceChanged;

  StartLearner? get learner => options?.learner(learnerId);
  StartSubject? get subject => options?.subject(subjectId);
  StartPlan? get plan => options?.plan(planId);
  StartTeacher? get teacher => options?.teacher(teacherId);
  OpenGroup? get group => slots?.groups.where((g) => g.id == groupId).firstOrNull;

  /// How many weekly times to choose (a shared package: up to this many).
  int get perWeek => slots?.perWeek ?? plan?.perWeek ?? 1;
  bool get timesDone => plan?.shared ?? false ? picked.isNotEmpty && picked.length <= perWeek : picked.length == perWeek;
  List<WeeklyTime> get pickedTimes => [for (final k in picked) ?slots?.time(k)];

  /// The weeks the chosen times start without, in date order (the review lists them).
  List<SkippedWeek> get pickedSkips => SkippedWeek.inOrder(pickedTimes.expand((t) => t.skips));

  /// The earliest first lesson among the chosen times.
  WeeklyTime? get firstPicked {
    final times = pickedTimes.where((t) => t.first != null).toList()..sort((a, b) => a.first!.utc.compareTo(b.first!.utc));
    return times.firstOrNull;
  }

  /// The counted steps of this start (the learner only when there is a choice).
  List<StartStep> get path => [
    if ((options?.learners.length ?? 0) > 1) StartStep.learner,
    StartStep.subject,
    StartStep.plan,
    if (plan?.group ?? false) StartStep.group else ...[StartStep.teacher, StartStep.times],
    StartStep.review,
  ];

  StartState copyWith({
    StartStep? step,
    List<StartStep>? history,
    StartOptions? Function()? options,
    ApiProblem? Function()? problem,
    String? Function()? learnerId,
    String? Function()? subjectId,
    String? Function()? planId,
    String? Function()? teacherId,
    String? Function()? groupId,
    StartSlots? Function()? slots,
    bool? slotsLoading,
    ApiProblem? Function()? slotsProblem,
    List<String>? picked,
    bool? dayFull,
    StartNote? Function()? note,
    bool? busy,
    ApiProblem? Function()? submitProblem,
    StartBlock? Function()? block,
    ChildConsent? Function()? consent,
    ApiProblem? Function()? consentProblem,
    StartedCourse? Function()? started,
    bool? priceChanged,
  }) => StartState(
    step: step ?? this.step,
    history: history ?? this.history,
    options: options == null ? this.options : options(),
    problem: problem == null ? this.problem : problem(),
    learnerId: learnerId == null ? this.learnerId : learnerId(),
    subjectId: subjectId == null ? this.subjectId : subjectId(),
    planId: planId == null ? this.planId : planId(),
    teacherId: teacherId == null ? this.teacherId : teacherId(),
    groupId: groupId == null ? this.groupId : groupId(),
    recommendedPlanId: recommendedPlanId,
    slots: slots == null ? this.slots : slots(),
    slotsLoading: slotsLoading ?? this.slotsLoading,
    slotsProblem: slotsProblem == null ? this.slotsProblem : slotsProblem(),
    picked: picked ?? this.picked,
    dayFull: dayFull ?? this.dayFull,
    note: note == null ? this.note : note(),
    busy: busy ?? this.busy,
    submitProblem: submitProblem == null ? this.submitProblem : submitProblem(),
    block: block == null ? this.block : block(),
    consent: consent == null ? this.consent : consent(),
    consentProblem: consentProblem == null ? this.consentProblem : consentProblem(),
    started: started == null ? this.started : started(),
    priceChanged: priceChanged ?? this.priceChanged,
  );

  @override
  List<Object?> get props => [
    step,
    history,
    options,
    problem,
    learnerId,
    subjectId,
    planId,
    teacherId,
    groupId,
    slots,
    slotsLoading,
    slotsProblem,
    picked,
    dayFull,
    note,
    busy,
    submitProblem,
    block,
    consent,
    consentProblem,
    started,
    priceChanged,
  ];
}

/// Starting a course, one step at a time (docs/architecture/02, section 27,
/// J3): who, the subject, the plan, the teacher and the weekly times (or a
/// group's seat), the summary, and the start itself, which makes the course,
/// its lessons and its invoice. Opened from a trial's report, it starts on
/// the plan with the recommended one and the trial's teacher chosen. Every
/// refusal comes back to the step it is about (a time taken meanwhile: the
/// times again with the others kept), or to a calm screen of its own.
class StartCubit extends Cubit<StartState> {
  StartCubit(this._repo, this._consents, {this.initialLearner, this.initialSubject, this.initialPlan, this.initialTeacher})
    : super(StartState(recommendedPlanId: initialPlan));

  final StartRepository _repo;
  final ConsentRepository _consents;
  final String? initialLearner;
  final String? initialSubject;
  final String? initialPlan;
  final String? initialTeacher;

  void _go(StartStep step, {bool remember = true}) {
    final from = state.step;
    final keep = remember && from != step && from != StartStep.loading && from != StartStep.failed;
    emit(state.copyWith(step: step, history: keep ? [...state.history, from] : state.history, dayFull: false));
  }

  /// Reads what may be started and opens the first step that needs an answer.
  Future<void> load() async {
    emit(state.copyWith(step: StartStep.loading, problem: () => null));
    try {
      var options = await _repo.options(learnerId: initialLearner, subjectId: initialSubject);
      if (isClosed) return;
      if (!options.selfServe) {
        emit(state.copyWith(options: () => options, step: StartStep.off));
        return;
      }
      final learner = options.learner(initialLearner)?.id ?? (options.learners.length == 1 ? options.learners.first.id : null);
      if (learner != null && learner != options.learnerId) {
        options = await _repo.options(learnerId: learner, subjectId: initialSubject);
        if (isClosed) return;
      }
      final subject = options.subject(initialSubject);
      emit(state.copyWith(options: () => options, learnerId: () => learner ?? options.learnerId, step: StartStep.loading));
      if (learner == null) {
        _go(StartStep.learner);
      } else if (subject != null && subject.canStart) {
        emit(state.copyWith(subjectId: () => subject.id, planId: () => options.plan(initialPlan)?.id));
        _go(StartStep.plan);
      } else {
        // The subject asked for cannot start (a course in it already): its card says why.
        emit(state.copyWith(subjectId: () => subject?.id));
        _go(StartStep.subject);
      }
    } on ApiProblem catch (p) {
      if (isClosed) return;
      final why = p.type == 'start-refused' ? p.message : null;
      emit(
        why != null
            ? state.copyWith(
                step: StartStep.blocked,
                block: () => StartBlock(reason: StartReason.of(p.type, p.extra['reason']), text: why, code: p.extra['reason'] as String?),
              )
            : state.copyWith(step: StartStep.failed, problem: () => p),
      );
    }
  }

  /// One step back; false from the first one (the page closes then).
  bool back() {
    if (state.busy || state.history.isEmpty) return false;
    final history = [...state.history];
    final previous = history.removeLast();
    emit(state.copyWith(step: previous, history: history, note: () => null, submitProblem: () => null, dayFull: false));
    return true;
  }

  Future<bool> _reload({String? learner, String? subject}) async {
    try {
      final options = await _repo.options(learnerId: learner ?? state.learnerId, subjectId: subject ?? state.subjectId);
      if (isClosed) return false;
      emit(state.copyWith(options: () => options, problem: () => null));
      return true;
    } on ApiProblem catch (p) {
      if (!isClosed) emit(state.copyWith(problem: () => p));
      return false;
    }
  }

  Future<void> pickLearner(String id) async {
    if (state.busy) return;
    final changed = id != state.learnerId;
    emit(state.copyWith(learnerId: () => id, busy: true, note: () => null));
    if (changed || state.options?.learnerId != id) {
      final ok = await _reload(learner: id, subject: '');
      if (!ok) {
        emit(state.copyWith(busy: false));
        return;
      }
      emit(state.copyWith(subjectId: () => null, planId: () => null, teacherId: () => null, groupId: () => null, picked: const []));
    }
    emit(state.copyWith(busy: false));
    _go(StartStep.subject);
  }

  Future<void> pickSubject(String id) async {
    if (state.busy) return;
    final subject = state.options?.subject(id);
    if (subject == null || !subject.canStart) return;
    emit(state.copyWith(busy: true, note: () => null));
    final ok = await _reload(subject: id);
    if (!ok || isClosed) {
      emit(state.copyWith(busy: false));
      return;
    }
    final changed = id != state.subjectId;
    emit(
      state.copyWith(
        busy: false,
        subjectId: () => id,
        planId: changed ? () => null : null,
        teacherId: changed ? () => null : null,
        groupId: changed ? () => null : null,
        picked: changed ? const [] : null,
      ),
    );
    _go(StartStep.plan);
  }

  void pickPlan(String id) {
    if (id == state.planId) return;
    emit(state.copyWith(planId: () => id, groupId: () => null, picked: const [], slots: () => null, note: () => null));
  }

  /// On from the plan: the teacher for a one-to-one plan, else the open groups.
  Future<void> planNext() async {
    final plan = state.plan;
    if (plan == null) return;
    if (plan.group) {
      _go(StartStep.group);
      await loadSlots();
      return;
    }
    final teachers = state.options?.privateTeachers ?? const [];
    final chosen = state.options?.teacher(state.teacherId);
    final preferred =
        chosen ??
        teachers.where((t) => t.id == initialTeacher).firstOrNull ??
        teachers.where((t) => t.trialTeacher).firstOrNull ??
        (teachers.length == 1 ? teachers.first : null);
    emit(state.copyWith(teacherId: () => preferred?.id));
    _go(StartStep.teacher);
  }

  void pickTeacher(String id) {
    if (id == state.teacherId) return;
    emit(state.copyWith(teacherId: () => id, picked: const [], slots: () => null, note: () => null));
  }

  Future<void> teacherNext() async {
    if (state.teacherId == null) return;
    _go(StartStep.times);
    await loadSlots();
  }

  /// The teacher's weekly times (or the open groups) for the plan; [keep] the times still free.
  Future<void> loadSlots({bool keep = true}) async {
    final plan = state.plan;
    final subject = state.subjectId;
    if (plan == null || subject == null) return;
    emit(state.copyWith(slotsLoading: true, slotsProblem: () => null));
    try {
      final slots = await _repo.slots(
        learnerId: state.learnerId,
        subjectId: subject,
        planId: plan.id,
        teacherId: plan.group ? null : state.teacherId,
      );
      if (isClosed) return;
      final kept = keep ? state.picked.where((k) => slots.time(k) != null).toList() : <String>[];
      final picked = kept.isEmpty && state.note == null ? slots.suggested.where((k) => slots.time(k) != null).toList() : kept;
      final group =
          slots.groups.where((g) => g.id == state.groupId).firstOrNull?.id ?? (slots.groups.length == 1 ? slots.groups.first.id : null);
      emit(state.copyWith(slots: () => slots, slotsLoading: false, picked: picked, groupId: () => group));
    } on ApiProblem catch (p) {
      if (isClosed) return;
      final refusal = p.type == 'start-refused';
      emit(state.copyWith(slotsLoading: false, slotsProblem: () => p));
      if (refusal && StartReason.of(p.type, p.extra['reason']) == StartReason.teacherUnavailable) {
        await _reload();
        emit(state.copyWith(teacherId: () => null, slotsProblem: () => null, note: () => StartNote(p.message ?? '')));
        _back(StartStep.teacher);
      }
    }
  }

  /// A weekly time chosen or let go: one a day, [StartState.perWeek] of them.
  void toggleTime(String key) {
    final slots = state.slots;
    final time = slots?.time(key);
    if (time == null) return;
    final picked = [...state.picked];
    if (picked.remove(key)) {
      emit(state.copyWith(picked: picked, dayFull: false));
      return;
    }
    final sameDay = picked.where((k) => slots!.time(k)?.weekday == time.weekday).toList();
    if (sameDay.isNotEmpty) {
      picked
        ..removeWhere(sameDay.contains)
        ..add(key);
      emit(state.copyWith(picked: picked, dayFull: false));
      return;
    }
    if (picked.length >= state.perWeek) {
      emit(state.copyWith(dayFull: true));
      return;
    }
    emit(state.copyWith(picked: [...picked, key], dayFull: false));
  }

  void timesNext() {
    if (!state.timesDone) return;
    emit(state.copyWith(note: () => null));
    _go(StartStep.review);
  }

  void pickGroup(String id) => emit(state.copyWith(groupId: () => id, note: () => null));

  void groupNext() {
    if (state.group == null) return;
    _go(StartStep.review);
  }

  /// From the summary, back to a step to change it (what was chosen stays).
  void edit(StartStep step) {
    if (state.busy) return;
    emit(state.copyWith(note: () => null, submitProblem: () => null, priceChanged: false));
    _back(step);
  }

  /// Back to [step] without counting the review as a step to return to.
  void _back(StartStep step) {
    final history = [...state.history];
    final at = history.lastIndexOf(step);
    if (at >= 0) history.removeRange(at, history.length);
    emit(state.copyWith(step: step, history: history, busy: false, dayFull: false));
  }

  /// Starts it: the course, its lessons and its invoice, or the refusal's own way on.
  Future<void> confirm() async {
    final plan = state.plan;
    final subject = state.subjectId;
    if (plan == null || subject == null || state.busy) return;
    emit(state.copyWith(busy: true, submitProblem: () => null));
    try {
      final started = await _repo.start(
        learnerId: state.learnerId,
        subjectId: subject,
        planId: plan.id,
        price: plan.price,
        teacherId: plan.group ? null : state.teacherId,
        times: plan.group ? const [] : state.picked,
        groupId: plan.group ? state.groupId : null,
      );
      if (!isClosed) emit(state.copyWith(busy: false, started: () => started, priceChanged: false));
    } on ApiProblem catch (p) {
      if (isClosed) return;
      if (p.type != 'start-refused' && p.type != 'guardian-consent-needed') {
        emit(state.copyWith(busy: false, submitProblem: () => p));
        return;
      }
      await _refused(p);
    }
  }

  Future<void> _refused(ApiProblem p) async {
    final text = p.message ?? '';
    final reason = StartReason.of(p.type, p.extra['reason']);
    final note = StartNote(text);
    switch (reason) {
      case StartReason.timeTaken:
        emit(state.copyWith(note: () => note));
        _back(StartStep.times);
        await loadSlots();
      case StartReason.priceChanged:
        final fresh = Amount.fromJson(p.extra['price']);
        await _reload();
        final plan = state.plan;
        // The options read again carry the new price; else the one the refusal named.
        if (plan != null && fresh != null && plan.price.minor != fresh.minor) {
          final options = state.options!;
          emit(
            state.copyWith(
              options: () => StartOptions(
                selfServe: options.selfServe,
                learnerId: options.learnerId,
                learners: options.learners,
                subjects: options.subjects,
                region: options.region,
                plans: [for (final x in options.plans) x.id == plan.id ? x.withPrice(fresh) : x],
                teachers: options.teachers,
                offerOpen: options.offerOpen,
                offerPercent: options.offerPercent,
                offerEndsAt: options.offerEndsAt,
                trialTeacherAvailable: options.trialTeacherAvailable,
                unpaidCancelHours: options.unpaidCancelHours,
              ),
            ),
          );
        }
        emit(state.copyWith(busy: false, priceChanged: true, note: () => note));
      case StartReason.consent:
        await _askConsent((p.extra['student_id'] as String?) ?? state.learnerId, text);
      case StartReason.teacherUnavailable:
        await _reload();
        emit(state.copyWith(teacherId: () => null, picked: const [], slots: () => null, note: () => note));
        _back(StartStep.teacher);
      case StartReason.groupGone:
        emit(state.copyWith(groupId: () => null, note: () => note));
        _back(StartStep.group);
        await loadSlots();
      case StartReason.planGone:
        await _reload();
        emit(state.copyWith(planId: () => null, note: () => note));
        _back(StartStep.plan);
      case StartReason.timesWrong:
        emit(state.copyWith(note: () => note));
        _back(state.plan?.group ?? false ? StartStep.group : StartStep.times);
      case StartReason.courseExists || StartReason.coursePending || StartReason.coursePendingOwn || StartReason.closed:
        emit(
          state.copyWith(
            busy: false,
            block: () => StartBlock(
              reason: reason,
              text: text,
              enrollmentId: p.extra['enrollment_id'] as String?,
              code: p.extra['reason'] as String?,
            ),
          ),
        );
        _go(StartStep.blocked);
      case StartReason.other:
        emit(state.copyWith(busy: false, submitProblem: () => p));
    }
  }

  /// The child's consent, read from the guardian's list: shown in full, then agreed.
  Future<void> _askConsent(String? studentId, String text) async {
    try {
      final consents = await _consents.load();
      if (isClosed) return;
      final child = consents.children.where((c) => c.studentId == studentId).firstOrNull ?? consents.waiting.firstOrNull;
      if (child == null) {
        emit(
          state.copyWith(
            busy: false,
            submitProblem: () => ApiProblem(type: 'consent', title: text, status: 409),
          ),
        );
        return;
      }
      emit(state.copyWith(busy: false, consent: () => child, consentProblem: () => null, note: () => StartNote(text, warning: false)));
      _go(StartStep.consent);
    } on ApiProblem catch (p) {
      if (!isClosed) emit(state.copyWith(busy: false, submitProblem: () => p));
    }
  }

  /// "I agree", then the same start sent again.
  Future<void> agree() async {
    final consent = state.consent;
    if (consent == null || state.busy) return;
    emit(state.copyWith(busy: true, consentProblem: () => null));
    try {
      await _consents.grant(consent.studentId, consent.text.version);
    } on ApiProblem catch (p) {
      if (!isClosed) emit(state.copyWith(busy: false, consentProblem: () => p));
      return;
    }
    if (isClosed) return;
    emit(state.copyWith(busy: false, note: () => null));
    _back(StartStep.review);
    await confirm();
  }

  /// Calls off the course the family started and is waiting for payment, then starts again from the plan.
  Future<String?> cancelPending(String enrollmentId) async {
    if (state.busy) return null;
    emit(state.copyWith(busy: true, submitProblem: () => null));
    try {
      final message = await _repo.cancel(enrollmentId);
      if (isClosed) return null;
      await _reload();
      final subject = state.subject;
      final again = subject != null && subject.canStart;
      emit(
        state.copyWith(
          busy: false,
          block: () => null,
          note: () => StartNote(message, warning: false),
          step: again ? StartStep.plan : StartStep.subject,
          history: again ? const [StartStep.subject] : const [],
        ),
      );
      return message;
    } on ApiProblem catch (p) {
      if (!isClosed) emit(state.copyWith(busy: false, submitProblem: () => p));
      return null;
    }
  }
}
