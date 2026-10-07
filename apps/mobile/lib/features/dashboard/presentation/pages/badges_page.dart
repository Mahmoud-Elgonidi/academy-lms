import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/family_repository.dart';
import '../../domain/journey.dart';
import '../widgets/badge_tile.dart';

/// Every badge, earned or still to win, with how to get it.
class BadgesPage extends StatelessWidget {
  const BadgesPage({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<Journey>(() => c.read<FamilyRepository>().journey())..load(),
    child: Scaffold(
      appBar: AppBar(title: Text(context.l10n.navBadges)),
      body: LoadView<Journey>(
        builder: (context, j) => GridView.count(
          padding: pagePadding,
          crossAxisCount: 3,
          mainAxisSpacing: 16,
          crossAxisSpacing: 10,
          childAspectRatio: 0.62,
          children: [for (final b in j.badges) BadgeTile(badge: b, showHint: true)],
        ),
      ),
    ),
  );
}
