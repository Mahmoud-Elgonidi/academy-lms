import '../network/api_client.dart';
import 'account.dart';

/// What the API client reads on every request: the open account, the
/// language and the clock. The session cubit keeps it current; a refused
/// token is reported back through [expired].
class SessionHolder implements ApiContext {
  Account? account;

  @override
  String locale = 'ar';

  @override
  String? timezone;

  /// Set by the session cubit.
  void Function()? expired;

  @override
  String? get token => account?.token;

  @override
  String? get academy => account?.academySlug;

  @override
  void onUnauthorized() => expired?.call();
}
