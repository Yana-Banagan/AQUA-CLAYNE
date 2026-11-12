import 'package:flutter/material.dart';

import '../screens/login_screen.dart';

void replaceWithLogin(BuildContext context, {String? noticeMessage}) {
  Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
    MaterialPageRoute<void>(
      builder: (_) => LoginScreen(noticeMessage: noticeMessage),
    ),
    (_) => false,
  );
}
