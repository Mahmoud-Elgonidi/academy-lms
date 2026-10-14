import 'package:flutter/material.dart';

import '../../app/theme/tokens.dart';

/// A sheet from the bottom over the whole app, tabs included, with an
/// optional title and a handle to pull it down. A sheet with fields keeps
/// clear of the keyboard itself (`MediaQuery.viewInsetsOf`).
Future<T?> showActionSheet<T>(BuildContext context, {required WidgetBuilder builder, String? title}) => showModalBottomSheet<T>(
  context: context,
  useRootNavigator: true,
  isScrollControlled: true,
  useSafeArea: true,
  showDragHandle: true,
  builder: (ctx) => title == null
      ? builder(ctx)
      : Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsetsDirectional.fromSTEB(Space.xl - 4, 0, Space.xl - 4, Space.m),
              child: Semantics(header: true, child: Text(title, style: ctx.type.title)),
            ),
            Flexible(child: builder(ctx)),
          ],
        ),
);
