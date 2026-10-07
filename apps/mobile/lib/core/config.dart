/// Where the app finds the API. One build serves every academy: the academy
/// comes from the signed-in account (or `X-Academy` before that).
///
/// Set at build time: `--dart-define=API_BASE_URL=https://api.example.com/api/v1`.
class AppConfig {
  const AppConfig({required this.apiBaseUrl});

  factory AppConfig.fromEnvironment() =>
      const AppConfig(apiBaseUrl: String.fromEnvironment('API_BASE_URL', defaultValue: 'http://localhost:8000/api/v1'));

  final String apiBaseUrl;
}
