import 'package:flutter/material.dart';

import '../../app/theme/app_theme.dart';
import '../../app/theme/tokens.dart';
import '../../core/l10n/l10n.dart';
import '../../core/network/api_problem.dart';

/// Nothing here yet: a picture or an icon, what that means, and what to do.
class EmptyState extends StatelessWidget {
  const EmptyState({required this.title, this.body, this.icon = Icons.inbox_outlined, this.art, this.action, super.key});

  final String title;
  final String? body;
  final IconData icon;

  /// A drawing in place of the icon.
  final Widget? art;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: Space.xxl, horizontal: Space.xl),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          art ??
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(color: c.chip, shape: BoxShape.circle),
                child: Icon(icon, size: 34, color: c.muted),
              ),
          const Gap(Space.l),
          Text(title, textAlign: TextAlign.center, style: context.type.cardTitle),
          if (body != null) ...[const Gap(Space.xs), Text(body!, textAlign: TextAlign.center, style: context.type.caption)],
          if (action != null) ...[const Gap(Space.l), action!],
        ],
      ),
    );
  }
}

/// Why something could not load, by kind, and a way to try again.
enum ErrorKind { offline, server, forbidden, other }

class ErrorState extends StatelessWidget {
  const ErrorState({required this.kind, this.message, this.onRetry, super.key});

  factory ErrorState.of(ApiProblem problem, {VoidCallback? onRetry, Key? key}) => ErrorState(
    key: key,
    kind: problem.isOffline
        ? ErrorKind.offline
        : problem.status == 403
        ? ErrorKind.forbidden
        : problem.status >= 500
        ? ErrorKind.server
        : ErrorKind.other,
    message: problem.message,
    onRetry: problem.status == 403 ? null : onRetry,
  );

  final ErrorKind kind;

  /// The API's own words, for a problem that is neither the network nor the server.
  final String? message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final (icon, title, body) = switch (kind) {
      ErrorKind.offline => (Icons.wifi_off_rounded, l.errorOfflineTitle, l.offline),
      ErrorKind.server => (Icons.cloud_off_rounded, l.errorServerTitle, l.errorServerBody),
      ErrorKind.forbidden => (Icons.lock_outline_rounded, l.errorForbiddenTitle, l.errorForbiddenBody),
      ErrorKind.other => (Icons.error_outline_rounded, message ?? l.problem, null),
    };
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Space.xl + 4),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(color: kind == ErrorKind.other ? c.dangerSoft : c.chip, shape: BoxShape.circle),
              child: Icon(icon, size: 30, color: kind == ErrorKind.other ? c.dangerInk : c.muted),
            ),
            const Gap(Space.l),
            Text(title, textAlign: TextAlign.center, style: context.type.cardTitle),
            if (body != null) ...[const Gap(Space.xs), Text(body, textAlign: TextAlign.center, style: context.type.caption)],
            if (onRetry != null) ...[
              const Gap(Space.l),
              OutlinedButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh_rounded), label: Text(l.retry)),
            ],
          ],
        ),
      ),
    );
  }
}
