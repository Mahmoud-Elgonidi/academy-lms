import 'package:flutter/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../core/network/api_client.dart';
import '../../core/realtime/live.dart';
import '../../core/social/social_sign_in.dart';
import '../../features/auth/data/auth_repository.dart';
import '../../features/billing/data/billing_repository.dart';
import '../../features/challenges/data/challenges_repository.dart';
import '../../features/classroom/data/classroom_repository.dart';
import '../../features/dashboard/data/family_repository.dart';
import '../../features/dashboard/data/home_loader.dart';
import '../../features/homework/data/homework_repository.dart';
import '../../features/live/data/live_repository.dart';
import '../../features/notifications/data/notifications_repository.dart';
import '../../features/profile/data/profile_repository.dart';
import '../../features/roadmap/data/learning_repository.dart';
import '../../features/schedule/data/lessons_repository.dart';
import '../../features/consent/data/consent_repository.dart';
import '../../features/privacy/data/privacy_repository.dart';
import '../../features/recordings/data/recordings_repository.dart';
import '../../features/safeguarding/data/safeguarding_repository.dart';
import '../../features/support/data/support_repository.dart';
import '../../features/teaching/data/teaching_repository.dart';

/// The repositories every screen reads from, over one API client.
class Services {
  Services(this.api, {SocialSignIn? social})
    : social = social ?? NativeSocialSignIn(),
      auth = AuthRepository(api),
      family = FamilyRepository(api),
      lessons = LessonsRepository(api),
      billing = BillingRepository(api),
      learning = LearningRepository(api),
      homework = HomeworkRepository(api),
      classroom = ClassroomRepository(api),
      profile = ProfileRepository(api),
      teaching = TeachingRepository(api),
      challenges = ChallengesRepository(api),
      notifications = NotificationsRepository(api),
      consent = ConsentRepository(api),
      privacy = PrivacyRepository(api),
      recordings = RecordingsRepository(api),
      support = SupportRepository(api),
      safeguarding = SafeguardingRepository(api),
      live = Live(source: LiveRepository(api), apiBaseUrl: api.dio.options.baseUrl) {
    home = HomeLoader(lessons: lessons, billing: billing, learning: learning, homework: homework, family: family);
  }

  final ApiClient api;

  /// The phone's Google and Apple sign-in.
  final SocialSignIn social;
  final AuthRepository auth;
  final FamilyRepository family;
  final LessonsRepository lessons;
  final BillingRepository billing;
  final LearningRepository learning;
  final HomeworkRepository homework;
  final ClassroomRepository classroom;
  final ProfileRepository profile;
  final TeachingRepository teaching;
  final ChallengesRepository challenges;
  final NotificationsRepository notifications;
  final ConsentRepository consent;
  final PrivacyRepository privacy;
  final RecordingsRepository recordings;
  final SupportRepository support;
  final SafeguardingRepository safeguarding;

  /// The person's own signals while the app is open (screens reload on them).
  final Live live;
  late final HomeLoader home;

  Widget provide({required Widget child}) => MultiRepositoryProvider(
    providers: [
      RepositoryProvider.value(value: this),
      RepositoryProvider.value(value: auth),
      RepositoryProvider<SocialSignIn>.value(value: social),
      RepositoryProvider.value(value: family),
      RepositoryProvider.value(value: lessons),
      RepositoryProvider.value(value: billing),
      RepositoryProvider.value(value: consent),
      RepositoryProvider.value(value: privacy),
      RepositoryProvider.value(value: recordings),
      RepositoryProvider.value(value: support),
      RepositoryProvider.value(value: safeguarding),
      RepositoryProvider.value(value: learning),
      RepositoryProvider.value(value: homework),
      RepositoryProvider.value(value: classroom),
      RepositoryProvider.value(value: profile),
      RepositoryProvider.value(value: teaching),
      RepositoryProvider.value(value: challenges),
      RepositoryProvider.value(value: notifications),
      RepositoryProvider.value(value: home),
      RepositoryProvider.value(value: live),
    ],
    child: child,
  );
}
