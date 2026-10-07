import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';

/// This app is older than the oldest the platform still serves: nothing
/// else opens until it is updated from the store.
class UpdatePage extends StatelessWidget {
  const UpdatePage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final policy = context.select<SessionCubit, ({String? android, String? ios})>(
      (s) => (android: s.state.policy?.androidUrl, ios: s.state.policy?.iosUrl),
    );
    final url = defaultTargetPlatform == TargetPlatform.iOS ? policy.ios : policy.android;
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.system_update_rounded, size: 72, color: context.colors.brand).bob(context),
              const SizedBox(height: 18),
              Text(l.updateTitle, textAlign: TextAlign.center, style: context.text.headlineSmall),
              const SizedBox(height: 8),
              Text(l.updateBody, textAlign: TextAlign.center, style: context.text.bodyLarge),
              const SizedBox(height: 24),
              if (url != null)
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: () => launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication),
                    child: Text(l.updateNow),
                  ),
                )
              else
                Text(l.updateFromStore, textAlign: TextAlign.center, style: context.text.titleSmall),
            ],
          ),
        ),
      ),
    );
  }
}
