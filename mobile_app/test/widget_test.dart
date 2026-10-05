import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:seller_africa_app/main.dart';

void main() {
  testWidgets('shows onboarding before first shop visit', (
    WidgetTester tester,
  ) async {
    SharedPreferences.setMockInitialValues({});

    await tester.pumpWidget(const SellerAfricaApp());
    await tester.pumpAndSettle();

    expect(find.text('Daily shopping\nin one place'), findsOneWidget);
    expect(find.text('Get Started Now'), findsOneWidget);
  });
}
