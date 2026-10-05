import 'dart:convert';
import 'dart:math';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

const apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'https://sellerafrica.com/api/mobile/v1',
);

final money = NumberFormat.currency(symbol: r'$');
final Set<String> _queuedImageCacheUrls = <String>{};

const green = Color(0xFF0D8A5A);
const deepGreen = Color(0xFF063B2A);
const orange = Color(0xFFFF8A1F);
const sun = Color(0xFFFFDE00);
const pageBg = Color(0xFFF3F6F1);
const softLine = Color(0xFFE4EAE4);
const textDark = Color(0xFF10231A);

void main() {
  runApp(const SellerAfricaApp());
}

class SellerAfricaApp extends StatelessWidget {
  const SellerAfricaApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider(create: (_) => ApiClient(apiBaseUrl)),
        ChangeNotifierProvider(create: (_) => AuthController()),
        ChangeNotifierProvider(create: (_) => CartController()),
        ChangeNotifierProvider(create: (_) => WishlistController()),
        ChangeNotifierProvider(create: (_) => LocationController()),
      ],
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        title: 'Seller Africa',
        theme: ThemeData(
          useMaterial3: true,
          scaffoldBackgroundColor: pageBg,
          colorScheme: ColorScheme.fromSeed(
            seedColor: green,
            primary: green,
            secondary: orange,
            surface: Colors.white,
          ),
          fontFamily: 'Arial',
          navigationBarTheme: NavigationBarThemeData(
            backgroundColor: Colors.white,
            indicatorColor: green.withValues(alpha: .13),
            labelTextStyle: WidgetStateProperty.resolveWith(
              (states) => TextStyle(
                fontSize: 11,
                fontWeight: states.contains(WidgetState.selected)
                    ? FontWeight.w900
                    : FontWeight.w600,
              ),
            ),
          ),
        ),
        home: const LaunchGate(),
      ),
    );
  }
}

class LaunchGate extends StatefulWidget {
  const LaunchGate({super.key});

  @override
  State<LaunchGate> createState() => _LaunchGateState();
}

class _LaunchGateState extends State<LaunchGate> {
  bool? onboarded;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() => onboarded = prefs.getBool('onboarding_complete') ?? false);
  }

  @override
  Widget build(BuildContext context) {
    if (onboarded == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    return onboarded! ? const MainShell() : const OnboardingScreen();
  }
}

class OnboardingScreen extends StatelessWidget {
  const OnboardingScreen({super.key});

  Future<void> _finish(BuildContext context) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('onboarding_complete', true);
    if (context.mounted) {
      Navigator.of(
        context,
      ).pushReplacement(MaterialPageRoute(builder: (_) => const MainShell()));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) => SingleChildScrollView(
            child: ConstrainedBox(
              constraints: BoxConstraints(minHeight: constraints.maxHeight),
              child: Column(
                children: [
                  Container(
                    height: max(640, constraints.maxHeight - 220),
                    width: double.infinity,
                    margin: const EdgeInsets.fromLTRB(18, 18, 18, 0),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF7FBEA),
                      borderRadius: BorderRadius.circular(36),
                    ),
                    child: Stack(
                      children: [
                        Positioned(
                          right: -56,
                          top: -44,
                          child: CircleBlob(
                            size: 190,
                            color: sun.withValues(alpha: .55),
                          ),
                        ),
                        Positioned(
                          left: -60,
                          bottom: 80,
                          child: CircleBlob(
                            size: 180,
                            color: green.withValues(alpha: .10),
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.all(26),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.center,
                            children: [
                              const Align(
                                alignment: Alignment.topRight,
                                child: Text(
                                  'Skip',
                                  style: TextStyle(
                                    color: green,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                              const SizedBox(height: 26),
                              const AppMark(size: 80),
                              const SizedBox(height: 30),
                              const Text(
                                'Daily shopping\nin one place',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  fontSize: 42,
                                  fontWeight: FontWeight.w900,
                                  height: .98,
                                  color: textDark,
                                ),
                              ),
                              const SizedBox(height: 16),
                              const Text(
                                'Fresh groceries, diaspora favorites, beauty, fashion, farm goods, and trusted marketplace sellers.',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  color: Color(0xFF67736D),
                                  fontSize: 16,
                                  height: 1.45,
                                ),
                              ),
                              const Spacer(),
                              Container(
                                height: 180,
                                width: double.infinity,
                                decoration: BoxDecoration(
                                  borderRadius: BorderRadius.circular(34),
                                  gradient: const LinearGradient(
                                    begin: Alignment.topCenter,
                                    end: Alignment.bottomCenter,
                                    colors: [
                                      Color(0xFFEAF9D7),
                                      Color(0xFFFFFFFF),
                                    ],
                                  ),
                                ),
                                child: const Icon(
                                  Icons.local_grocery_store,
                                  size: 96,
                                  color: green,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.fromLTRB(26, 28, 26, 24),
                    color: const Color(0xFF111111),
                    child: Column(
                      children: [
                        const Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            _AvatarDot(label: 'B'),
                            _AvatarDot(label: 'V'),
                            _AvatarDot(label: 'F'),
                            SizedBox(width: 16),
                            Text(
                              '1.4k+',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 34,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ],
                        ),
                        const Text(
                          'regular customers',
                          style: TextStyle(color: Color(0xFFC9D0CA)),
                        ),
                        const SizedBox(height: 22),
                        SizedBox(
                          width: double.infinity,
                          height: 60,
                          child: FilledButton(
                            style: FilledButton.styleFrom(
                              backgroundColor: sun,
                              foregroundColor: Colors.black,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(28),
                              ),
                            ),
                            onPressed: () => _finish(context),
                            child: const Text(
                              'Get Started Now',
                              style: TextStyle(
                                fontSize: 17,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int tab = 0;
  late Future<void> _restoreSessionFuture;

  @override
  void initState() {
    super.initState();
    _restoreSessionFuture = Future.wait([
      context.read<AuthController>().restore(context.read<ApiClient>()),
      context.read<LocationController>().restore(),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    final screens = [
      HomeScreen(onOpenCart: () => setState(() => tab = 2)),
      const CategoriesScreen(),
      const CartScreen(),
      const OrdersScreen(),
      const AccountScreen(),
    ];
    return FutureBuilder<void>(
      future: _restoreSessionFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Scaffold(
            body: Center(child: CircularProgressIndicator()),
          );
        }
        return Scaffold(
          body: screens[tab],
          bottomNavigationBar: NavigationBar(
            selectedIndex: tab,
            onDestinationSelected: (index) => setState(() => tab = index),
            destinations: [
              const NavigationDestination(
                icon: Icon(Icons.home_outlined),
                selectedIcon: Icon(Icons.home),
                label: 'Home',
              ),
              const NavigationDestination(
                icon: Icon(Icons.category_outlined),
                selectedIcon: Icon(Icons.category),
                label: 'Categories',
              ),
              NavigationDestination(
                icon: Consumer<CartController>(
                  builder: (_, cart, _) => Badge.count(
                    isLabelVisible: cart.count > 0,
                    count: cart.count,
                    child: const Icon(Icons.shopping_cart_outlined),
                  ),
                ),
                selectedIcon: const Icon(Icons.shopping_cart),
                label: 'Cart',
              ),
              const NavigationDestination(
                icon: Icon(Icons.receipt_long_outlined),
                selectedIcon: Icon(Icons.receipt_long),
                label: 'Orders',
              ),
              const NavigationDestination(
                icon: Icon(Icons.person_outline),
                selectedIcon: Icon(Icons.person),
                label: 'Account',
              ),
            ],
          ),
        );
      },
    );
  }
}

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.onOpenCart});

  final VoidCallback onOpenCart;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late Future<HomePayload> homeFuture;
  bool _guestPromptShown = false;

  @override
  void initState() {
    super.initState();
    homeFuture = context.read<ApiClient>().home();
    WidgetsBinding.instance.addPostFrameCallback((_) => _showGuestPrompt());
  }

  void _showGuestPrompt() {
    if (!mounted ||
        _guestPromptShown ||
        context.read<AuthController>().isLoggedIn) {
      return;
    }
    _guestPromptShown = true;
    showDialog<void>(
      context: context,
      builder: (dialogContext) => Dialog(
        insetPadding: const EdgeInsets.symmetric(horizontal: 22),
        backgroundColor: Colors.transparent,
        child: Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(30),
            boxShadow: [
              BoxShadow(
                color: deepGreen.withValues(alpha: .16),
                blurRadius: 32,
                offset: const Offset(0, 18),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const AppMark(size: 54),
              const SizedBox(height: 16),
              const Text(
                'Shop faster with an account',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: deepGreen,
                  fontSize: 24,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 10),
              const Text(
                'Save addresses, track real orders, chat with sellers, and keep your shopping history in one place.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Color(0xFF6E7A72), height: 1.45),
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () {
                        Navigator.of(dialogContext).pop();
                        showAuthSheet(context, isRegister: false);
                      },
                      child: const Text('Login'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton(
                      onPressed: () {
                        Navigator.of(dialogContext).pop();
                        showAuthSheet(context, isRegister: true);
                      },
                      child: const Text('Register'),
                    ),
                  ),
                ],
              ),
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(),
                child: const Text('Continue as guest'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: FutureBuilder<HomePayload>(
          future: homeFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return ErrorState(
                message: snapshot.error.toString(),
                onRetry: () => setState(
                  () => homeFuture = context.read<ApiClient>().home(),
                ),
              );
            }
            final home = snapshot.data!;
            precacheHomeImages(context, home);
            final flash = discounted(home.products).take(8).toList();
            final recommended = home.products.take(10).toList();
            final trending = home.products.skip(4).take(10).toList();
            return RefreshIndicator(
              onRefresh: () async {
                setState(() => homeFuture = context.read<ApiClient>().home());
                await homeFuture;
              },
              child: ListView(
                padding: const EdgeInsets.fromLTRB(18, 14, 18, 22),
                children: [
                  HomeHeader(onOpenCart: widget.onOpenCart),
                  const SizedBox(height: 16),
                  SearchBarButton(onTap: () => openSearch(context)),
                  const SizedBox(height: 12),
                  HomeActionRow(),
                  const SizedBox(height: 18),
                  PromoCarousel(banners: home.banners),
                  const SizedBox(height: 24),
                  HorizontalCategories(categories: home.categories),
                  if (flash.isNotEmpty) ...[
                    const SizedBox(height: 26),
                    SectionHeader(title: 'Flash Sale', action: 'Ends 04:12:39'),
                    const SizedBox(height: 12),
                    ProductRail(products: flash, compact: true),
                  ],
                  const SizedBox(height: 26),
                  SectionHeader(
                    title: 'Recommended For You',
                    action: 'View All',
                  ),
                  const SizedBox(height: 12),
                  ProductGridPreview(products: recommended),
                  const SizedBox(height: 26),
                  SectionHeader(
                    title: 'Top Sellers',
                    action: 'View All',
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const SellersScreen()),
                    ),
                  ),
                  const SizedBox(height: 12),
                  SellerRail(vendors: home.vendors),
                  const SizedBox(height: 26),
                  SectionHeader(title: 'Trending Products', action: 'Popular'),
                  const SizedBox(height: 12),
                  ProductRail(
                    products: trending.isEmpty ? recommended : trending,
                  ),
                  const SizedBox(height: 26),
                  SectionHeader(title: 'New Arrivals', action: 'Just Listed'),
                  const SizedBox(height: 12),
                  ProductRail(products: home.latest),
                  const SizedBox(height: 26),
                  SectionHeader(
                    title: 'Popular Near You',
                    action: context.watch<LocationController>().displayName,
                  ),
                  const SizedBox(height: 12),
                  ProductRail(products: recommended.reversed.toList()),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

class HomeHeader extends StatelessWidget {
  const HomeHeader({super.key, required this.onOpenCart});

  final VoidCallback onOpenCart;

  @override
  Widget build(BuildContext context) {
    final location = context.watch<LocationController>();
    return Row(
      children: [
        const AppMark(size: 42),
        const SizedBox(width: 12),
        Expanded(
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: () => showLocationSheet(context),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  location.hasLocation ? location.city : 'Add your location',
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    color: textDark,
                  ),
                ),
                Text(
                  location.hasLocation
                      ? '${location.state}, ${location.country}'
                      : 'Set delivery city and country',
                  style: const TextStyle(
                    color: Color(0xFF7A867F),
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
        ),
        RoundIcon(
          icon: Icons.favorite_border,
          onTap: () => showWishlist(context),
        ),
        const SizedBox(width: 8),
        RoundIcon(icon: Icons.notifications_none, onTap: () {}),
        const SizedBox(width: 8),
        Consumer<CartController>(
          builder: (_, cart, _) => Badge.count(
            isLabelVisible: cart.count > 0,
            count: cart.count,
            child: RoundIcon(
              icon: Icons.shopping_cart_outlined,
              onTap: onOpenCart,
            ),
          ),
        ),
      ],
    );
  }
}

class HomeActionRow extends StatelessWidget {
  const HomeActionRow({super.key});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: ActionChip(
            avatar: const Icon(Icons.storefront, color: green),
            label: const Text('Search sellers'),
            onPressed: () => Navigator.of(
              context,
            ).push(MaterialPageRoute(builder: (_) => const SellersScreen())),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: ActionChip(
            avatar: const Icon(Icons.map_outlined, color: green),
            label: const Text('Farmers Near Me'),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const FarmersNearMeScreen()),
            ),
          ),
        ),
      ],
    );
  }
}

class SearchBarButton extends StatelessWidget {
  const SearchBarButton({super.key, required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(24),
      onTap: onTap,
      child: Container(
        height: 58,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),
        child: const Row(
          children: [
            Icon(Icons.search, color: Color(0xFF7A867F)),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                'Search products, stores and categories',
                style: TextStyle(color: Color(0xFF7A867F)),
              ),
            ),
            Icon(Icons.mic_none, color: green),
            SizedBox(width: 12),
            Icon(Icons.qr_code_scanner, color: green),
          ],
        ),
      ),
    );
  }
}

class PromoCarousel extends StatelessWidget {
  const PromoCarousel({super.key, required this.banners});

  final List<AppBanner> banners;

  @override
  Widget build(BuildContext context) {
    final items = banners.isEmpty
        ? [
            AppBanner(
              title: 'Flash sales',
              subtitle: 'Shop today on Seller Africa.',
              imageUrl: '',
              targetUrl: '',
            ),
          ]
        : banners;
    return SizedBox(
      height: 168,
      child: PageView.builder(
        controller: PageController(viewportFraction: .94),
        itemCount: items.length,
        itemBuilder: (context, index) {
          final banner = items[index];
          return Padding(
            padding: const EdgeInsets.only(right: 12),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(30),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  if (banner.imageUrl.isNotEmpty)
                    CachedNetworkImage(
                      imageUrl: banner.imageUrl,
                      fit: BoxFit.cover,
                    )
                  else
                    Container(color: green),
                  Container(
                    decoration: const BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.centerLeft,
                        end: Alignment.centerRight,
                        colors: [Color(0xCC063B2A), Color(0x22063B2A)],
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Text(
                          banner.title,
                          maxLines: 2,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 25,
                            fontWeight: FontWeight.w900,
                            height: 1.02,
                          ),
                        ),
                        if (banner.subtitle.isNotEmpty) ...[
                          const SizedBox(height: 8),
                          Text(
                            banner.subtitle,
                            maxLines: 2,
                            style: const TextStyle(
                              color: Color(0xFFEAF6EF),
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class HorizontalCategories extends StatelessWidget {
  const HorizontalCategories({super.key, required this.categories});

  final List<CategoryItem> categories;

  @override
  Widget build(BuildContext context) {
    final visible = categories.take(12).toList();
    return Column(
      children: [
        SectionHeader(
          title: 'Categories',
          action: 'View All',
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => CategoriesScreen(categories: categories),
            ),
          ),
        ),
        const SizedBox(height: 12),
        SizedBox(
          height: 94,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: visible.length,
            separatorBuilder: (_, _) => const SizedBox(width: 14),
            itemBuilder: (context, index) {
              final category = visible[index];
              return GestureDetector(
                onTap: () => openSearch(context, category: category),
                child: SizedBox(
                  width: 72,
                  child: Column(
                    children: [
                      Container(
                        width: 64,
                        height: 64,
                        clipBehavior: Clip.antiAlias,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: .05),
                              blurRadius: 12,
                              offset: const Offset(0, 6),
                            ),
                          ],
                        ),
                        child: category.imageUrl.isNotEmpty
                            ? ProductImage(url: category.imageUrl)
                            : Icon(
                                categoryIcon(category.name),
                                color: green,
                                size: 30,
                              ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        category.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}

class ProductRail extends StatelessWidget {
  const ProductRail({super.key, required this.products, this.compact = false});

  final List<Product> products;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: compact ? 242 : 258,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: products.length,
        separatorBuilder: (_, _) => const SizedBox(width: 14),
        itemBuilder: (context, index) =>
            SizedBox(width: 162, child: ProductCard(product: products[index])),
      ),
    );
  }
}

class ProductGridPreview extends StatelessWidget {
  const ProductGridPreview({super.key, required this.products});

  final List<Product> products;

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: min(4, products.length),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        childAspectRatio: .66,
        crossAxisSpacing: 14,
        mainAxisSpacing: 14,
      ),
      itemBuilder: (context, index) => ProductCard(product: products[index]),
    );
  }
}

class ProductCard extends StatelessWidget {
  const ProductCard({super.key, required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final hasDeal = product.oldPrice > product.price;
    return InkWell(
      borderRadius: BorderRadius.circular(24),
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ProductDetailScreen(productId: product.id),
        ),
      ),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          boxShadow: const [
            BoxShadow(
              color: Color(0x0D000000),
              blurRadius: 20,
              offset: Offset(0, 10),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Stack(
                children: [
                  Positioned.fill(
                    child: ClipRRect(
                      borderRadius: const BorderRadius.vertical(
                        top: Radius.circular(24),
                      ),
                      child: ProductImage(url: product.imageUrl),
                    ),
                  ),
                  if (hasDeal)
                    Positioned(
                      top: 9,
                      left: 9,
                      child: DealBadge(text: '${product.discountPercent}% OFF'),
                    ),
                  Positioned(
                    top: 6,
                    right: 6,
                    child: WishlistButton(product: product),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontWeight: FontWeight.w900,
                      height: 1.1,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    product.vendorName ?? 'Verified seller',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: green,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      const Icon(
                        Icons.star,
                        size: 15,
                        color: Color(0xFFF4B400),
                      ),
                      const SizedBox(width: 3),
                      Text(
                        product.rating.toStringAsFixed(1),
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF7B877F),
                        ),
                      ),
                      const SizedBox(width: 4),
                      Text(
                        '(${product.reviewCount})',
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF9AA39D),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: Wrap(
                          crossAxisAlignment: WrapCrossAlignment.end,
                          spacing: 5,
                          children: [
                            Text(
                              money.format(product.price),
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                                color: textDark,
                              ),
                            ),
                            if (hasDeal)
                              Text(
                                money.format(product.oldPrice),
                                style: const TextStyle(
                                  color: Color(0xFF9EA7A1),
                                  decoration: TextDecoration.lineThrough,
                                  fontSize: 12,
                                ),
                              ),
                          ],
                        ),
                      ),
                      IconButton.filled(
                        constraints: const BoxConstraints.tightFor(
                          width: 38,
                          height: 38,
                        ),
                        padding: EdgeInsets.zero,
                        style: IconButton.styleFrom(backgroundColor: orange),
                        onPressed: () =>
                            context.read<CartController>().add(product),
                        icon: const Icon(Icons.add, color: Colors.white),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class SellerRail extends StatelessWidget {
  const SellerRail({super.key, required this.vendors});

  final List<Vendor> vendors;

  @override
  Widget build(BuildContext context) {
    if (vendors.isEmpty) {
      return const SizedBox.shrink();
    }
    return SizedBox(
      height: 132,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: vendors.length,
        separatorBuilder: (_, _) => const SizedBox(width: 12),
        itemBuilder: (context, index) {
          final vendor = vendors[index];
          return InkWell(
            borderRadius: BorderRadius.circular(22),
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => SellerDetailScreen(vendor: vendor),
              ),
            ),
            child: Container(
              width: 190,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(22),
              ),
              child: Row(
                children: [
                  ClipOval(
                    child: SizedBox(
                      width: 56,
                      height: 56,
                      child: ProductImage(url: vendor.logoUrl),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          vendor.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 4),
                        const Row(
                          children: [
                            Icon(Icons.verified, color: green, size: 15),
                            SizedBox(width: 4),
                            Text(
                              'Verified',
                              style: TextStyle(
                                color: green,
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Text(
                          '${vendor.productCount} products',
                          style: const TextStyle(
                            color: Color(0xFF7B877F),
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class SellersScreen extends StatefulWidget {
  const SellersScreen({super.key});

  @override
  State<SellersScreen> createState() => _SellersScreenState();
}

class _SellersScreenState extends State<SellersScreen> {
  late Future<List<Vendor>> future;
  final searchController = TextEditingController();
  String query = '';

  @override
  void initState() {
    super.initState();
    future = context.read<ApiClient>().vendors();
  }

  @override
  void dispose() {
    searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Search Sellers')),
      body: FutureBuilder<List<Vendor>>(
        future: future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ErrorState(
              message: snapshot.error.toString(),
              onRetry: () =>
                  setState(() => future = context.read<ApiClient>().vendors()),
            );
          }
          final sellers = (snapshot.data ?? [])
              .where(
                (seller) =>
                    query.isEmpty ||
                    seller.name.toLowerCase().contains(query.toLowerCase()) ||
                    seller.description.toLowerCase().contains(
                      query.toLowerCase(),
                    ),
              )
              .toList();
          return ListView(
            padding: const EdgeInsets.all(18),
            children: [
              TextField(
                controller: searchController,
                onChanged: (value) => setState(() => query = value.trim()),
                decoration: InputDecoration(
                  hintText: 'Search seller name or products',
                  prefixIcon: const Icon(Icons.search),
                  filled: true,
                  fillColor: Colors.white,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(22),
                    borderSide: BorderSide.none,
                  ),
                ),
              ),
              const SizedBox(height: 16),
              if (sellers.isEmpty)
                const EmptyState(
                  icon: Icons.storefront_outlined,
                  title: 'No sellers found',
                  body: 'Try another seller name or product keyword.',
                )
              else
                ...sellers.map((seller) => SellerListCard(vendor: seller)),
            ],
          );
        },
      ),
    );
  }
}

class SellerListCard extends StatelessWidget {
  const SellerListCard({super.key, required this.vendor});

  final Vendor vendor;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(24),
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => SellerDetailScreen(vendor: vendor)),
      ),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),
        child: Row(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(18),
              child: SizedBox(
                width: 68,
                height: 68,
                child: ProductImage(url: vendor.logoUrl),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    vendor.name,
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    vendor.description.isEmpty
                        ? '${vendor.productCount} products available'
                        : vendor.description,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Color(0xFF6C7871),
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right),
          ],
        ),
      ),
    );
  }
}

class SellerDetailScreen extends StatefulWidget {
  const SellerDetailScreen({super.key, required this.vendor});

  final Vendor vendor;

  @override
  State<SellerDetailScreen> createState() => _SellerDetailScreenState();
}

class _SellerDetailScreenState extends State<SellerDetailScreen> {
  late Future<List<Product>> productsFuture;

  @override
  void initState() {
    super.initState();
    productsFuture = context.read<ApiClient>().vendorProducts(widget.vendor.id);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.vendor.name)),
      body: FutureBuilder<List<Product>>(
        future: productsFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ErrorState(
              message: snapshot.error.toString(),
              onRetry: () => setState(
                () => productsFuture = context.read<ApiClient>().vendorProducts(
                  widget.vendor.id,
                ),
              ),
            );
          }
          final products = snapshot.data ?? [];
          return ListView(
            padding: const EdgeInsets.all(18),
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(28),
                child: SizedBox(
                  height: 180,
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      ProductImage(
                        url: widget.vendor.bannerUrl.isNotEmpty
                            ? widget.vendor.bannerUrl
                            : widget.vendor.logoUrl,
                      ),
                      Container(color: Colors.black.withValues(alpha: .28)),
                      Padding(
                        padding: const EdgeInsets.all(18),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.end,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              widget.vendor.name,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 26,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            Text(
                              '${widget.vendor.productCount} products',
                              style: const TextStyle(color: Colors.white),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              if (widget.vendor.description.isNotEmpty) ...[
                const SizedBox(height: 14),
                Text(widget.vendor.description),
              ],
              const SizedBox(height: 20),
              SectionHeader(
                title: 'Products',
                action: '${products.length} items',
              ),
              const SizedBox(height: 12),
              if (products.isEmpty)
                const EmptyState(
                  icon: Icons.inventory_2_outlined,
                  title: 'No products available',
                  body: 'This seller has no active products right now.',
                )
              else
                ProductGridPreview(products: products),
            ],
          );
        },
      ),
    );
  }
}

class FarmersNearMeScreen extends StatefulWidget {
  const FarmersNearMeScreen({super.key});

  @override
  State<FarmersNearMeScreen> createState() => _FarmersNearMeScreenState();
}

class _FarmersNearMeScreenState extends State<FarmersNearMeScreen> {
  final mapController = MapController();
  late Future<List<Vendor>> future;
  int selected = 0;
  bool locating = false;

  @override
  void initState() {
    super.initState();
    future = context.read<ApiClient>().vendors(limit: 50);
  }

  List<Vendor> _farmers(List<Vendor> vendors) {
    final filtered = vendors
        .where(
          (vendor) =>
              vendor.name.toLowerCase().contains('farm') ||
              vendor.description.toLowerCase().contains('farm') ||
              vendor.description.toLowerCase().contains('produce') ||
              vendor.description.toLowerCase().contains('fresh'),
        )
        .toList();
    return filtered.isEmpty ? vendors : filtered;
  }

  Future<void> _locate() async {
    setState(() => locating = true);
    try {
      final location = context.read<LocationController>();
      await location.useCurrentLocation();
      if (!mounted) {
        return;
      }
      final center = location.mapCenter;
      mapController.move(center, 10);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => locating = false);
      }
    }
  }

  void _selectFarm(Vendor farm, LatLng point, int index) {
    setState(() => selected = index);
    mapController.move(point, 12);
    showModalBottomSheet(
      context: context,
      builder: (_) => FarmPreviewSheet(farm: farm),
    );
  }

  @override
  Widget build(BuildContext context) {
    final location = context.watch<LocationController>();
    final center = location.mapCenter;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Farmers Near Me'),
        actions: [
          IconButton(
            onPressed: locating ? null : _locate,
            icon: locating
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.my_location),
          ),
        ],
      ),
      body: FutureBuilder<List<Vendor>>(
        future: future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ErrorState(
              message: snapshot.error.toString(),
              onRetry: () =>
                  setState(() => future = context.read<ApiClient>().vendors()),
            );
          }
          final farms = _farmers(snapshot.data ?? []);
          if (farms.isEmpty) {
            return const EmptyState(
              icon: Icons.agriculture_outlined,
              title: 'No farms listed yet',
              body: 'Farm Fresh vendors will appear here when available.',
            );
          }
          return Stack(
            children: [
              FlutterMap(
                mapController: mapController,
                options: MapOptions(initialCenter: center, initialZoom: 8),
                children: [
                  TileLayer(
                    urlTemplate:
                        'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                    userAgentPackageName: 'com.sellerafrica.mobile',
                  ),
                  MarkerLayer(
                    markers: [
                      Marker(
                        point: center,
                        width: 44,
                        height: 44,
                        child: const Icon(
                          Icons.my_location,
                          color: orange,
                          size: 36,
                        ),
                      ),
                      for (var i = 0; i < farms.length; i++)
                        Marker(
                          point: farmPoint(farms[i], center, i),
                          width: 48,
                          height: 48,
                          child: IconButton.filled(
                            style: IconButton.styleFrom(
                              backgroundColor: i == selected ? orange : green,
                            ),
                            onPressed: () => _selectFarm(
                              farms[i],
                              farmPoint(farms[i], center, i),
                              i,
                            ),
                            icon: const Icon(Icons.agriculture),
                          ),
                        ),
                    ],
                  ),
                ],
              ),
              Positioned(
                left: 14,
                right: 14,
                top: 14,
                child: Material(
                  borderRadius: BorderRadius.circular(24),
                  color: Colors.white,
                  elevation: 3,
                  child: ListTile(
                    leading: const Icon(Icons.location_on, color: green),
                    title: Text(
                      location.hasLocation
                          ? location.displayName
                          : 'Use your location',
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    subtitle: const Text('Find fresh farms near your area'),
                    trailing: TextButton(
                      onPressed: () => showLocationSheet(context),
                      child: const Text('Change'),
                    ),
                  ),
                ),
              ),
              Positioned(
                left: 0,
                right: 0,
                bottom: 18,
                child: SizedBox(
                  height: 136,
                  child: PageView.builder(
                    controller: PageController(
                      viewportFraction: .86,
                      initialPage: selected,
                    ),
                    itemCount: farms.length,
                    onPageChanged: (index) {
                      final point = farmPoint(farms[index], center, index);
                      setState(() => selected = index);
                      mapController.move(point, 11);
                    },
                    itemBuilder: (context, index) => Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 6),
                      child: FarmMapCard(
                        farm: farms[index],
                        onTap: () => _selectFarm(
                          farms[index],
                          farmPoint(farms[index], center, index),
                          index,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class FarmMapCard extends StatelessWidget {
  const FarmMapCard({super.key, required this.farm, required this.onTap});

  final Vendor farm;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(24),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          boxShadow: const [
            BoxShadow(color: Color(0x22000000), blurRadius: 18),
          ],
        ),
        child: Row(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(18),
              child: SizedBox(
                width: 86,
                height: 96,
                child: ProductImage(
                  url: farm.bannerUrl.isNotEmpty
                      ? farm.bannerUrl
                      : farm.logoUrl,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    farm.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    farm.description.isEmpty
                        ? 'Fresh produce and local farm goods'
                        : farm.description,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Color(0xFF6C7871),
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${farm.productCount} products',
                    style: const TextStyle(
                      color: green,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class FarmPreviewSheet extends StatelessWidget {
  const FarmPreviewSheet({super.key, required this.farm});

  final Vendor farm;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(24),
            child: SizedBox(
              height: 150,
              width: double.infinity,
              child: ProductImage(
                url: farm.bannerUrl.isNotEmpty ? farm.bannerUrl : farm.logoUrl,
              ),
            ),
          ),
          const SizedBox(height: 14),
          Text(
            farm.name,
            style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          Text(
            farm.description.isEmpty
                ? 'Fresh produce and local farm goods.'
                : farm.description,
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => SellerDetailScreen(vendor: farm),
                ),
              ),
              child: const Text('View Farm & Produce'),
            ),
          ),
        ],
      ),
    );
  }
}

class SearchScreen extends StatefulWidget {
  const SearchScreen({super.key, this.initialCategory});

  final CategoryItem? initialCategory;

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  final controller = TextEditingController();
  List<Product> results = [];
  String sort = 'recommended';
  bool loading = false;

  @override
  void initState() {
    super.initState();
    _search();
  }

  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  Future<void> _search() async {
    setState(() => loading = true);
    try {
      final products = await context.read<ApiClient>().products(
        query: controller.text,
        category: widget.initialCategory?.slug ?? '',
        sort: sort,
      );
      setState(() => results = products);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final suggestions = [
      'iPhone',
      'African groceries',
      'Ankara',
      'Shea butter',
      'Farm fresh',
      'Jollof rice',
    ];
    return Scaffold(
      appBar: AppBar(
        title: TextField(
          controller: controller,
          autofocus: true,
          onSubmitted: (_) => _search(),
          decoration: const InputDecoration(
            prefixIcon: Icon(Icons.search),
            hintText: 'Search products, stores and categories',
            border: InputBorder.none,
          ),
        ),
        actions: [
          IconButton(
            onPressed: () => controller.clear(),
            icon: const Icon(Icons.close),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          Wrap(
            spacing: 9,
            runSpacing: 9,
            children: [
              FilterChip(
                label: Text(widget.initialCategory?.name ?? 'All Categories'),
                selected: widget.initialCategory != null,
                onSelected: (_) {},
              ),
              FilterChip(
                label: const Text('Offers'),
                selected: false,
                onSelected: (_) {},
              ),
              FilterChip(
                label: const Text('4.0+'),
                selected: false,
                onSelected: (_) {},
              ),
              FilterChip(
                label: const Text('Free delivery'),
                selected: false,
                onSelected: (_) {},
              ),
              ActionChip(
                label: const Text('Filters'),
                avatar: const Icon(Icons.tune),
                onPressed: () => showFilters(context),
              ),
            ],
          ),
          const SizedBox(height: 18),
          SectionHeader(
            title: 'Trending searches',
            action: loading ? 'Loading' : '${results.length} results',
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 9,
            children: suggestions
                .map(
                  (text) => ActionChip(
                    label: Text(text),
                    onPressed: () {
                      controller.text = text;
                      _search();
                    },
                  ),
                )
                .toList(),
          ),
          const SizedBox(height: 18),
          DropdownButtonFormField<String>(
            initialValue: sort,
            decoration: InputDecoration(
              filled: true,
              fillColor: Colors.white,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(18),
                borderSide: BorderSide.none,
              ),
            ),
            items: const [
              DropdownMenuItem(
                value: 'recommended',
                child: Text('Recommended'),
              ),
              DropdownMenuItem(value: 'popular', child: Text('Popular')),
              DropdownMenuItem(value: 'latest', child: Text('Newest')),
              DropdownMenuItem(
                value: 'price_asc',
                child: Text('Price: Low to High'),
              ),
              DropdownMenuItem(
                value: 'price_desc',
                child: Text('Price: High to Low'),
              ),
            ],
            onChanged: (value) {
              sort = value ?? 'recommended';
              _search();
            },
          ),
          const SizedBox(height: 18),
          if (loading && results.isEmpty)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: CircularProgressIndicator(),
              ),
            )
          else
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: results.length,
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                childAspectRatio: .65,
                crossAxisSpacing: 14,
                mainAxisSpacing: 14,
              ),
              itemBuilder: (context, index) =>
                  ProductCard(product: results[index]),
            ),
        ],
      ),
    );
  }
}

class CategoriesScreen extends StatefulWidget {
  const CategoriesScreen({super.key, this.categories});

  final List<CategoryItem>? categories;

  @override
  State<CategoriesScreen> createState() => _CategoriesScreenState();
}

class _CategoriesScreenState extends State<CategoriesScreen> {
  late Future<List<CategoryItem>> future;

  @override
  void initState() {
    super.initState();
    future = widget.categories != null
        ? Future.value(widget.categories)
        : context.read<ApiClient>().categories();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Categories')),
      body: FutureBuilder<List<CategoryItem>>(
        future: future,
        builder: (context, snapshot) {
          if (!snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          final categories = snapshot.data!;
          return GridView.builder(
            padding: const EdgeInsets.all(18),
            itemCount: categories.length,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              childAspectRatio: 1.18,
              crossAxisSpacing: 14,
              mainAxisSpacing: 14,
            ),
            itemBuilder: (context, index) {
              final category = categories[index];
              return InkWell(
                borderRadius: BorderRadius.circular(26),
                onTap: () => openSearch(context, category: category),
                child: Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(26),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(20),
                        child: SizedBox(
                          width: 58,
                          height: 58,
                          child: category.imageUrl.isNotEmpty
                              ? ProductImage(url: category.imageUrl)
                              : Icon(
                                  categoryIcon(category.name),
                                  size: 36,
                                  color: green,
                                ),
                        ),
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            category.name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          Text(
                            '${category.productCount} products',
                            style: const TextStyle(color: Color(0xFF7B877F)),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class ProductDetailScreen extends StatefulWidget {
  const ProductDetailScreen({super.key, required this.productId});

  final int productId;

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  late Future<Product> productFuture;
  int quantity = 1;

  @override
  void initState() {
    super.initState();
    productFuture = context.read<ApiClient>().product(widget.productId);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: FutureBuilder<Product>(
          future: productFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return ErrorState(
                message: snapshot.error.toString(),
                onRetry: () => setState(
                  () => productFuture = context.read<ApiClient>().product(
                    widget.productId,
                  ),
                ),
              );
            }
            final product = snapshot.data!;
            precacheProductImages(context, product);
            return Column(
              children: [
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(18, 8, 18, 18),
                    children: [
                      Row(
                        children: [
                          RoundIcon(
                            icon: Icons.arrow_back,
                            onTap: () => Navigator.pop(context),
                          ),
                          const Expanded(
                            child: Center(
                              child: Text(
                                'Product Details',
                                style: TextStyle(
                                  fontWeight: FontWeight.w900,
                                  fontSize: 18,
                                ),
                              ),
                            ),
                          ),
                          WishlistButton(product: product, large: true),
                          const SizedBox(width: 8),
                          RoundIcon(icon: Icons.share_outlined, onTap: () {}),
                        ],
                      ),
                      const SizedBox(height: 16),
                      HeroImage(product: product),
                      const SizedBox(height: 20),
                      Text(
                        product.name,
                        style: const TextStyle(
                          fontSize: 26,
                          fontWeight: FontWeight.w900,
                          height: 1.08,
                          color: textDark,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          Text(
                            'By ${product.vendorName ?? 'Verified seller'}',
                            style: const TextStyle(
                              color: green,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(width: 10),
                          const Icon(Icons.verified, color: green, size: 18),
                          const Spacer(),
                          const Icon(
                            Icons.star,
                            color: Color(0xFFF4B400),
                            size: 18,
                          ),
                          Text(
                            ' ${product.rating.toStringAsFixed(1)} (${product.reviewCount})',
                          ),
                        ],
                      ),
                      const SizedBox(height: 18),
                      Row(
                        children: [
                          Expanded(
                            child: Wrap(
                              crossAxisAlignment: WrapCrossAlignment.end,
                              spacing: 8,
                              children: [
                                Text(
                                  money.format(product.price),
                                  style: const TextStyle(
                                    fontSize: 25,
                                    fontWeight: FontWeight.w900,
                                    color: deepGreen,
                                  ),
                                ),
                                if (product.oldPrice > product.price)
                                  Text(
                                    money.format(product.oldPrice),
                                    style: const TextStyle(
                                      color: Color(0xFF9EA7A1),
                                      decoration: TextDecoration.lineThrough,
                                      fontSize: 16,
                                    ),
                                  ),
                              ],
                            ),
                          ),
                          QtyControl(
                            quantity: quantity,
                            onChanged: (value) =>
                                setState(() => quantity = value),
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      ProductInfoPanel(product: product),
                      const SizedBox(height: 14),
                      RateSellerPanel(
                        product: product,
                        onRate: () => _openSellerReviewSheet(product),
                      ),
                      const SizedBox(height: 22),
                      DetailAccordion(
                        title: 'Product Information',
                        body: product.description.isNotEmpty
                            ? product.description
                            : product.shortDescription,
                      ),
                      DetailAccordion(
                        title: 'Specifications',
                        body:
                            'Seller: Verified\nStock: ${product.stockStatus}\nSKU: ${product.sku}\nCondition: New',
                      ),
                      const DetailAccordion(
                        title: 'Shipping & Returns',
                        body:
                            'Delivery options and fees are calculated during checkout. Returns are handled under Seller Africa marketplace policy.',
                      ),
                      const SizedBox(height: 20),
                      SellerInfoCard(product: product),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.fromLTRB(18, 12, 18, 18),
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    boxShadow: [
                      BoxShadow(
                        color: Color(0x11000000),
                        blurRadius: 16,
                        offset: Offset(0, -8),
                      ),
                    ],
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: FilledButton.tonalIcon(
                              style: FilledButton.styleFrom(
                                padding: const EdgeInsets.symmetric(
                                  vertical: 16,
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(22),
                                ),
                              ),
                              onPressed: () => context
                                  .read<CartController>()
                                  .add(product, quantity: quantity),
                              icon: const Icon(Icons.shopping_cart_outlined),
                              label: const Text(
                                'Add to Cart',
                                style: TextStyle(fontWeight: FontWeight.w900),
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: OutlinedButton.icon(
                              style: OutlinedButton.styleFrom(
                                padding: const EdgeInsets.symmetric(
                                  vertical: 16,
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(22),
                                ),
                              ),
                              onPressed: () => Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => const ChatSupportScreen(),
                                ),
                              ),
                              icon: const Icon(Icons.chat_bubble_outline),
                              label: const Text(
                                'Chat Seller',
                                style: TextStyle(fontWeight: FontWeight.w900),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton(
                          style: FilledButton.styleFrom(
                            backgroundColor: sun,
                            foregroundColor: Colors.black,
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(22),
                            ),
                          ),
                          onPressed: () {
                            context.read<CartController>().add(
                              product,
                              quantity: quantity,
                            );
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => const CheckoutScreen(),
                              ),
                            );
                          },
                          child: const Text(
                            'Buy Now',
                            style: TextStyle(fontWeight: FontWeight.w900),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  void _openSellerReviewSheet(Product product) {
    final auth = context.read<AuthController>();
    if (!auth.isLoggedIn) {
      showAuthSheet(context, isRegister: false);
      return;
    }

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (_) => RateSellerSheet(product: product),
    );
  }
}

class CartScreen extends StatelessWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Cart'),
        actions: [
          IconButton(
            onPressed: () => context.read<CartController>().clear(),
            icon: const Icon(Icons.delete_outline),
          ),
          IconButton(onPressed: () {}, icon: const Icon(Icons.share_outlined)),
        ],
      ),
      body: Consumer<CartController>(
        builder: (context, cart, _) {
          if (cart.items.isEmpty) {
            return const EmptyState(
              icon: Icons.shopping_cart_outlined,
              title: 'Your cart is empty',
              body: 'Add products from the marketplace to start checkout.',
            );
          }
          final groups = cart.itemsBySeller;
          return ListView(
            padding: const EdgeInsets.all(18),
            children: [
              ...groups.entries.map(
                (entry) =>
                    MerchantCartGroup(seller: entry.key, items: entry.value),
              ),
              PromoCodeBox(
                total: cart.estimatedTotal,
                onApply: () => showPromoSheet(context, cart.estimatedTotal),
              ),
              const SizedBox(height: 16),
              CartSummary(cart: cart),
              const SizedBox(height: 18),
              SizedBox(
                height: 58,
                child: FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: green,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(26),
                    ),
                  ),
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const CheckoutScreen()),
                  ),
                  child: const Text(
                    'Proceed to Checkout',
                    style: TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class MerchantCartGroup extends StatelessWidget {
  const MerchantCartGroup({
    super.key,
    required this.seller,
    required this.items,
  });

  final String seller;
  final List<CartItem> items;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(26),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.storefront, color: green, size: 18),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  seller,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
              const Text(
                'Verified',
                style: TextStyle(
                  color: green,
                  fontWeight: FontWeight.w800,
                  fontSize: 12,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ...items.map((item) => CartLine(item: item)),
        ],
      ),
    );
  }
}

class CartLine extends StatelessWidget {
  const CartLine({super.key, required this.item});

  final CartItem item;

  @override
  Widget build(BuildContext context) {
    final cart = context.read<CartController>();
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(18),
            child: SizedBox(
              width: 82,
              height: 82,
              child: ProductImage(url: item.product.imageUrl),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.product.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 4),
                Text(
                  'Delivery estimate shown at checkout',
                  style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                ),
                const SizedBox(height: 8),
                Text(
                  money.format(item.product.price),
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    color: textDark,
                  ),
                ),
              ],
            ),
          ),
          Column(
            children: [
              QtyControl(
                quantity: item.quantity,
                onChanged: (value) => cart.setQuantity(item.product.id, value),
                small: true,
              ),
              IconButton(
                onPressed: () => cart.setQuantity(item.product.id, 0),
                icon: const Icon(
                  Icons.delete_outline,
                  color: Color(0xFF9AA39D),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class PromoCodeBox extends StatelessWidget {
  const PromoCodeBox({super.key, required this.total, required this.onApply});

  final double total;
  final VoidCallback onApply;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.fromLTRB(14, 8, 8, 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.local_offer_outlined,
            color: Color(0xFF9AA39D),
            size: 18,
          ),
          const SizedBox(width: 8),
          const Expanded(
            child: Text(
              'Enter Promo Code',
              style: TextStyle(color: Color(0xFF8D9892)),
            ),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: green,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20),
              ),
            ),
            onPressed: onApply,
            child: const Text('Apply'),
          ),
        ],
      ),
    );
  }
}

class CartSummary extends StatelessWidget {
  const CartSummary({super.key, required this.cart, this.shipping});

  final CartController cart;
  final double? shipping;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SummaryRow(label: 'Subtotal', value: money.format(cart.subtotal)),
        SummaryRow(
          label: 'Shipping & Tax',
          value: money.format(shipping ?? cart.shippingEstimate),
        ),
        const Divider(height: 28),
        SummaryRow(
          label: 'Estimated Total',
          value: money.format(
            shipping == null
                ? cart.estimatedTotal
                : cart.totalWithShipping(shipping!),
          ),
          bold: true,
        ),
      ],
    );
  }
}

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final formKey = GlobalKey<FormState>();
  final fields = <String, TextEditingController>{
    'first_name': TextEditingController(),
    'last_name': TextEditingController(),
    'email': TextEditingController(),
    'phone': TextEditingController(),
    'address_line1': TextEditingController(),
    'address_line2': TextEditingController(),
    'city': TextEditingController(),
    'state': TextEditingController(),
    'postcode': TextEditingController(),
    'country_code': TextEditingController(text: 'US'),
  };
  int step = 0;
  bool loading = false;
  AddressBookItem? selectedAddress;

  double _shippingFor(CartController cart) {
    final country = fields['country_code']!.text.trim().toUpperCase();
    final state = fields['state']!.text.trim().toUpperCase();
    if (cart.subtotal <= 0) {
      return 0;
    }
    if (country == 'US') {
      if (cart.subtotal >= 100) {
        return 0;
      }
      return ['CA', 'TX', 'FL', 'NY', 'GA', 'IL'].contains(state)
          ? 8.99
          : 10.99;
    }
    if (['NG', 'GH', 'KE', 'ZA'].contains(country)) {
      return 24.99;
    }
    return 34.99;
  }

  void _useAddress(AddressBookItem address) {
    selectedAddress = address;
    for (final entry in address.raw.entries) {
      fields[entry.key]?.text = entry.value;
    }
    if ((fields['country_code']!.text).isEmpty) {
      fields['country_code']!.text = 'US';
    }
    setState(() {});
  }

  @override
  void dispose() {
    for (final controller in fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _checkout() async {
    if (!formKey.currentState!.validate()) {
      setState(() => step = 0);
      return;
    }
    setState(() => loading = true);
    try {
      final cart = context.read<CartController>();
      final shipping = _shippingFor(cart);
      final url = await context
          .read<ApiClient>()
          .createCheckoutSession(cart.items, {
            ...fields.map((key, value) => MapEntry(key, value.text.trim())),
            'shipping_estimate': shipping.toStringAsFixed(2),
          }, token: context.read<AuthController>().token);
      if (!await launchUrl(
        Uri.parse(url),
        mode: LaunchMode.externalApplication,
      )) {
        throw Exception('Could not open checkout.');
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartController>();
    final auth = context.watch<AuthController>();
    final shipping = _shippingFor(cart);
    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: Form(
        key: formKey,
        child: Column(
          children: [
            const CheckoutAuthPrompt(),
            Expanded(
              child: Stepper(
                currentStep: step,
                onStepTapped: (value) => setState(() => step = value),
                controlsBuilder: (_, details) => const SizedBox.shrink(),
                steps: [
                  Step(
                    title: const Text('Delivery Address'),
                    isActive: step >= 0,
                    content: Column(
                      children: [
                        ...fields.entries
                            .where(
                              (entry) =>
                                  entry.key != 'country_code' &&
                                  entry.key != 'state',
                            )
                            .map(
                              (entry) => Padding(
                                padding: const EdgeInsets.only(bottom: 10),
                                child: CheckoutField(
                                  controller: entry.value,
                                  label: checkoutLabel(entry.key),
                                  required: entry.key != 'address_line2',
                                  onChanged: (_) => setState(() {}),
                                ),
                              ),
                            ),
                        if (auth.addresses.isNotEmpty) ...[
                          const SizedBox(height: 4),
                          DropdownButtonFormField<AddressBookItem>(
                            initialValue: selectedAddress,
                            decoration: const InputDecoration(
                              labelText: 'Use saved address',
                              filled: true,
                              fillColor: Colors.white,
                              border: OutlineInputBorder(
                                borderSide: BorderSide.none,
                              ),
                            ),
                            items: [
                              for (final address in auth.addresses)
                                DropdownMenuItem(
                                  value: address,
                                  child: Text(
                                    '${address.name} - ${address.summary}',
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                            ],
                            onChanged: (address) {
                              if (address != null) {
                                _useAddress(address);
                              }
                            },
                          ),
                          const SizedBox(height: 10),
                        ],
                        Row(
                          children: [
                            Expanded(
                              child: DropdownButtonFormField<String>(
                                initialValue:
                                    countryOptions.contains(
                                      fields['country_code']!.text,
                                    )
                                    ? fields['country_code']!.text
                                    : 'US',
                                decoration: const InputDecoration(
                                  labelText: 'Country',
                                  filled: true,
                                  fillColor: Colors.white,
                                  border: OutlineInputBorder(
                                    borderSide: BorderSide.none,
                                  ),
                                ),
                                items: [
                                  for (final country in countryOptions)
                                    DropdownMenuItem(
                                      value: country,
                                      child: Text(countryName(country)),
                                    ),
                                ],
                                onChanged: (value) {
                                  if (value == null) return;
                                  fields['country_code']!.text = value;
                                  if (value != 'US') {
                                    fields['state']!.clear();
                                  }
                                  setState(() {});
                                },
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: fields['country_code']!.text == 'US'
                                  ? DropdownButtonFormField<String>(
                                      initialValue:
                                          usStates.contains(
                                            fields['state']!.text,
                                          )
                                          ? fields['state']!.text
                                          : null,
                                      decoration: const InputDecoration(
                                        labelText: 'State',
                                        filled: true,
                                        fillColor: Colors.white,
                                        border: OutlineInputBorder(
                                          borderSide: BorderSide.none,
                                        ),
                                      ),
                                      items: [
                                        for (final state in usStates)
                                          DropdownMenuItem(
                                            value: state,
                                            child: Text(state),
                                          ),
                                      ],
                                      onChanged: (value) {
                                        fields['state']!.text = value ?? '';
                                        setState(() {});
                                      },
                                    )
                                  : CheckoutField(
                                      controller: fields['state']!,
                                      label: 'State / Region',
                                      onChanged: (_) => setState(() {}),
                                    ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  Step(
                    title: const Text('Delivery Method'),
                    isActive: step >= 1,
                    content: Column(
                      children: [
                        DeliveryOption(
                          title: 'Standard delivery',
                          subtitle: 'Based on selected delivery address',
                          price: money.format(shipping),
                        ),
                        DeliveryOption(
                          title: 'Pickup location',
                          subtitle: 'Where available by seller',
                          price: 'Dynamic',
                        ),
                      ],
                    ),
                  ),
                  Step(
                    title: const Text('Coupon / Voucher'),
                    isActive: step >= 2,
                    content: PromoCodeBox(
                      total: cart.totalWithShipping(shipping),
                      onApply: () => showPromoSheet(
                        context,
                        cart.totalWithShipping(shipping),
                      ),
                    ),
                  ),
                  Step(
                    title: const Text('Payment'),
                    isActive: step >= 3,
                    content: const Column(
                      children: [
                        PaymentOption(
                          icon: Icons.credit_card,
                          title: 'Debit / Credit card',
                        ),
                        PaymentOption(
                          icon: Icons.account_balance,
                          title: 'Bank transfer',
                        ),
                        PaymentOption(
                          icon: Icons.phone_iphone,
                          title: 'Mobile Money / USSD',
                        ),
                        PaymentOption(
                          icon: Icons.wallet,
                          title: 'Apple Pay / Google Pay where available',
                        ),
                      ],
                    ),
                  ),
                  Step(
                    title: const Text('Order Review'),
                    isActive: step >= 4,
                    content: Column(
                      children: [
                        ...cart.items.map(
                          (item) => SummaryRow(
                            label: '${item.quantity} x ${item.product.name}',
                            value: money.format(
                              item.product.price * item.quantity,
                            ),
                          ),
                        ),
                        const Divider(height: 24),
                        CartSummary(cart: cart, shipping: shipping),
                        const SizedBox(height: 16),
                        SizedBox(
                          width: double.infinity,
                          child: FilledButton(
                            onPressed: loading ? null : _checkout,
                            child: loading
                                ? const CircularProgressIndicator()
                                : const Text(
                                    'Place Order',
                                    style: TextStyle(
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              if (step > 0)
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => setState(() => step--),
                    child: const Text('Back'),
                  ),
                ),
              if (step > 0) const SizedBox(width: 10),
              Expanded(
                flex: 2,
                child: FilledButton(
                  onPressed: step == 4
                      ? _checkout
                      : () => setState(() => step++),
                  child: Text(
                    step == 4 ? 'Place Order' : 'Continue',
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  Future<List<BuyerOrderSummary>>? _ordersFuture;
  String _loadedToken = '';

  Future<List<BuyerOrderSummary>> _loadOrders(ApiClient api, String token) {
    _loadedToken = token;
    return _ordersFuture = api.orders(token);
  }

  @override
  Widget build(BuildContext context) {
    final api = context.read<ApiClient>();
    final auth = context.watch<AuthController>();
    const tabs = [
      'All',
      'Pending',
      'Processing',
      'Shipped',
      'Delivered',
      'Cancelled',
      'Returned',
    ];

    if (!auth.isLoggedIn) {
      return Scaffold(
        appBar: AppBar(title: const Text('Orders')),
        body: Center(
          child: EmptyState(
            icon: Icons.receipt_long_outlined,
            title: 'Login to view orders',
            body:
                'Your real orders, payment status, and tracking updates appear here after you sign in.',
            actions: [
              FilledButton(
                onPressed: () => showAuthSheet(context, isRegister: false),
                child: const Text('Login'),
              ),
              OutlinedButton(
                onPressed: () => showAuthSheet(context, isRegister: true),
                child: const Text('Register'),
              ),
            ],
          ),
        ),
      );
    }

    if (_ordersFuture == null || _loadedToken != auth.token) {
      _ordersFuture = _loadOrders(api, auth.token);
    }

    return DefaultTabController(
      length: tabs.length,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Orders'),
          bottom: TabBar(
            isScrollable: true,
            tabs: [for (final tab in tabs) Tab(text: tab)],
          ),
        ),
        body: FutureBuilder<List<BuyerOrderSummary>>(
          future: _ordersFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return ErrorState(
                message: snapshot.error.toString(),
                onRetry: () => setState(
                  () => _ordersFuture = _loadOrders(api, auth.token),
                ),
              );
            }

            final orders = snapshot.data ?? [];
            return TabBarView(
              children: [
                for (final tab in tabs)
                  _OrderListTab(
                    tab: tab,
                    orders: orders
                        .where((order) => order.matchesStatus(tab))
                        .toList(),
                    onRefresh: () async {
                      setState(() {
                        _ordersFuture = _loadOrders(api, auth.token);
                      });
                      await _ordersFuture;
                    },
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _OrderListTab extends StatelessWidget {
  const _OrderListTab({
    required this.tab,
    required this.orders,
    required this.onRefresh,
  });

  final String tab;
  final List<BuyerOrderSummary> orders;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    if (orders.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          padding: const EdgeInsets.all(18),
          children: [
            EmptyState(
              icon: Icons.receipt_long_outlined,
              title: tab == 'All' ? 'No orders yet' : 'No $tab orders',
              body:
                  'Orders placed through Seller Africa will appear here with saved payment and tracking details.',
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.builder(
        padding: const EdgeInsets.all(18),
        itemCount: orders.length,
        itemBuilder: (context, index) => OrderCard(order: orders[index]),
      ),
    );
  }
}

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  bool showRegister = false;

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    return Scaffold(
      appBar: AppBar(title: const Text('Account')),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          Container(
            padding: const EdgeInsets.all(22),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(28),
            ),
            child: Row(
              children: [
                const AppMark(size: 62),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        auth.displayName,
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      Text(
                        auth.isLoggedIn
                            ? auth.email
                            : 'Sign in to track orders, save addresses, and manage wishlist.',
                        style: const TextStyle(color: Color(0xFF7B877F)),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          if (!auth.isLoggedIn) ...[
            const SizedBox(height: 16),
            AuthPanel(
              isRegister: showRegister,
              onToggle: () => setState(() => showRegister = !showRegister),
            ),
          ],
          const SizedBox(height: 16),
          AccountTile(
            icon: Icons.favorite_border,
            title: 'Wishlist',
            onTap: () => showWishlist(context),
          ),
          AccountTile(
            icon: Icons.location_on_outlined,
            title: 'Saved Addresses',
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const SavedAddressesScreen()),
            ),
          ),
          AccountTile(
            icon: Icons.support_agent,
            title: 'Support & Disputes',
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const ChatSupportScreen()),
            ),
          ),
          const AccountTile(
            icon: Icons.replay_outlined,
            title: 'Returns & Refunds',
          ),
          AccountTile(
            icon: Icons.chat_bubble_outline,
            title: 'Messages / Chat',
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const ChatSupportScreen()),
            ),
          ),
          AccountTile(
            icon: Icons.settings_outlined,
            title: 'Settings',
            onTap: () => Navigator.of(
              context,
            ).push(MaterialPageRoute(builder: (_) => const SettingsScreen())),
          ),
          if (auth.isLoggedIn)
            AccountTile(
              icon: Icons.logout,
              title: 'Log Out',
              onTap: () => context.read<AuthController>().logout(),
            ),
        ],
      ),
    );
  }
}

class AuthPanel extends StatefulWidget {
  const AuthPanel({
    super.key,
    required this.isRegister,
    required this.onToggle,
  });

  final bool isRegister;
  final VoidCallback onToggle;

  @override
  State<AuthPanel> createState() => _AuthPanelState();
}

class _AuthPanelState extends State<AuthPanel> {
  final formKey = GlobalKey<FormState>();
  final firstName = TextEditingController();
  final lastName = TextEditingController();
  final email = TextEditingController();
  final phone = TextEditingController();
  final password = TextEditingController();
  bool loading = false;

  @override
  void dispose() {
    firstName.dispose();
    lastName.dispose();
    email.dispose();
    phone.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    if (!formKey.currentState!.validate()) return;
    setState(() => loading = true);
    try {
      final api = context.read<ApiClient>();
      final auth = context.read<AuthController>();
      if (widget.isRegister) {
        final message = await auth.register(api, {
          'first_name': firstName.text.trim(),
          'last_name': lastName.text.trim(),
          'display_name': '${firstName.text.trim()} ${lastName.text.trim()}'
              .trim(),
          'email': email.text.trim(),
          'phone': phone.text.trim(),
          'password': password.text,
        });
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text(message)));
          widget.onToggle();
        }
      } else {
        await auth.login(api, email.text.trim(), password.text);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Signed in successfully.')),
          );
          Navigator.of(context).maybePop();
        }
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final title = widget.isRegister ? 'Create your account' : 'Welcome back';
    final subtitle = widget.isRegister
        ? 'Save addresses and keep your orders connected to your email.'
        : 'Login once and stay signed in for 30 days on this device.';
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 22),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(30),
      ),
      child: Form(
        key: formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                const AppMark(size: 46),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          color: deepGreen,
                          fontSize: 23,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          color: Color(0xFF6E7A72),
                          height: 1.25,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(4),
              decoration: BoxDecoration(
                color: pageBg,
                borderRadius: BorderRadius.circular(18),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: AuthModeButton(
                      label: 'Login',
                      selected: !widget.isRegister,
                      onTap: loading || !widget.isRegister
                          ? null
                          : widget.onToggle,
                    ),
                  ),
                  Expanded(
                    child: AuthModeButton(
                      label: 'Register',
                      selected: widget.isRegister,
                      onTap: loading || widget.isRegister
                          ? null
                          : widget.onToggle,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            if (widget.isRegister) ...[
              CheckoutField(controller: firstName, label: 'First name'),
              const SizedBox(height: 10),
              CheckoutField(controller: lastName, label: 'Last name'),
              const SizedBox(height: 10),
              CheckoutField(controller: phone, label: 'Phone'),
              const SizedBox(height: 10),
            ],
            CheckoutField(controller: email, label: 'Email'),
            const SizedBox(height: 10),
            TextFormField(
              controller: password,
              obscureText: true,
              validator: (value) => (value ?? '').length < 8
                  ? 'Password must be at least 8 characters.'
                  : null,
              decoration: InputDecoration(
                labelText: 'Password',
                filled: true,
                fillColor: pageBg,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(18),
                  borderSide: BorderSide.none,
                ),
              ),
            ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: loading ? null : submit,
                child: loading
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text(widget.isRegister ? 'Create account' : 'Login'),
              ),
            ),
            const SizedBox(height: 8),
            Center(
              child: TextButton(
                onPressed: loading ? null : widget.onToggle,
                child: Text(
                  widget.isRegister
                      ? 'Already have an account? Login'
                      : 'New here? Create an account',
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class AuthModeButton extends StatelessWidget {
  const AuthModeButton({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: selected ? Colors.white : Colors.transparent,
          borderRadius: BorderRadius.circular(14),
          boxShadow: selected
              ? [
                  BoxShadow(
                    color: deepGreen.withValues(alpha: .08),
                    blurRadius: 12,
                    offset: const Offset(0, 6),
                  ),
                ]
              : null,
        ),
        child: Text(
          label,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: selected ? deepGreen : const Color(0xFF6E7A72),
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
    );
  }
}

class SavedAddressesScreen extends StatefulWidget {
  const SavedAddressesScreen({super.key});

  @override
  State<SavedAddressesScreen> createState() => _SavedAddressesScreenState();
}

class _SavedAddressesScreenState extends State<SavedAddressesScreen> {
  final formKey = GlobalKey<FormState>();
  final fields = {
    'first_name': TextEditingController(),
    'last_name': TextEditingController(),
    'phone': TextEditingController(),
    'email': TextEditingController(),
    'address_line1': TextEditingController(),
    'address_line2': TextEditingController(),
    'city': TextEditingController(),
    'state': TextEditingController(),
    'postcode': TextEditingController(),
  };
  bool loading = false;

  @override
  void dispose() {
    for (final controller in fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> save() async {
    if (!formKey.currentState!.validate()) return;
    setState(() => loading = true);
    try {
      await context.read<AuthController>().saveAddress(
        context.read<ApiClient>(),
        fields.map((key, value) => MapEntry(key, value.text.trim())),
      );
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Address saved.')));
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    return Scaffold(
      appBar: AppBar(title: const Text('Saved Addresses')),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          if (!auth.isLoggedIn)
            const EmptyState(
              icon: Icons.lock_outline,
              title: 'Login required',
              body:
                  'Sign in or register from Account to save delivery addresses.',
            )
          else ...[
            ...auth.addresses.map((address) => AddressCard(address: address)),
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(26),
              ),
              child: Form(
                key: formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Add Address',
                      style: TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 12),
                    ...fields.entries.map(
                      (entry) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: CheckoutField(
                          controller: entry.value,
                          label: checkoutLabel(entry.key),
                          required: entry.key != 'address_line2',
                        ),
                      ),
                    ),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        onPressed: loading ? null : save,
                        child: const Text('Save Address'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class AddressCard extends StatelessWidget {
  const AddressCard({super.key, required this.address});

  final AddressBookItem address;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
      ),
      child: ListTile(
        leading: const Icon(Icons.location_on_outlined, color: green),
        title: Text(
          address.name,
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        subtitle: Text(address.summary),
      ),
    );
  }
}

class ChatSupportScreen extends StatelessWidget {
  const ChatSupportScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final messages = [
      ['Seller Africa', 'Hi, how can we help with your order or store today?'],
      ['Quick action', 'Is this product available?'],
      ['Quick action', 'Can you deliver to my area?'],
      ['Quick action', 'I need help with payment.'],
    ];
    return Scaffold(
      appBar: AppBar(title: const Text('Chat & Support')),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          ...messages.map(
            (message) => Container(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    message[0],
                    style: const TextStyle(
                      color: green,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(message[1]),
                ],
              ),
            ),
          ),
          TextField(
            minLines: 2,
            maxLines: 4,
            decoration: InputDecoration(
              hintText: 'Type your message',
              suffixIcon: IconButton(
                onPressed: () {},
                icon: const Icon(Icons.send),
              ),
              filled: true,
              fillColor: Colors.white,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(22),
                borderSide: BorderSide.none,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  bool push = true;
  bool email = true;
  bool location = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          SwitchListTile(
            value: push,
            onChanged: (value) => setState(() => push = value),
            title: const Text('Push notifications'),
          ),
          SwitchListTile(
            value: email,
            onChanged: (value) => setState(() => email = value),
            title: const Text('Email updates'),
          ),
          SwitchListTile(
            value: location,
            onChanged: (value) => setState(() => location = value),
            title: const Text('Use location for delivery suggestions'),
          ),
          const AccountTile(
            icon: Icons.privacy_tip_outlined,
            title: 'Privacy Policy',
          ),
          const AccountTile(
            icon: Icons.description_outlined,
            title: 'Terms & Conditions',
          ),
          const AccountTile(icon: Icons.help_outline, title: 'Help Centre'),
        ],
      ),
    );
  }
}

class WishlistSheet extends StatelessWidget {
  const WishlistSheet({super.key});

  @override
  Widget build(BuildContext context) {
    final wishlist = context.watch<WishlistController>();
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: .72,
      builder: (context, controller) => Container(
        padding: const EdgeInsets.all(18),
        decoration: const BoxDecoration(
          color: pageBg,
          borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
        ),
        child: ListView(
          controller: controller,
          children: [
            const Center(
              child: SizedBox(width: 48, child: Divider(thickness: 4)),
            ),
            const Text(
              'Wishlist',
              style: TextStyle(fontSize: 26, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),
            if (wishlist.items.isEmpty)
              const EmptyState(
                icon: Icons.favorite_border,
                title: 'No saved products yet',
                body: 'Tap the heart on products to save them here.',
              )
            else
              ...wishlist.items.map(
                (product) =>
                    CartLine(item: CartItem(product: product, quantity: 1)),
              ),
          ],
        ),
      ),
    );
  }
}

class AppMark extends StatelessWidget {
  const AppMark({super.key, required this.size});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: deepGreen,
        borderRadius: BorderRadius.circular(size * .28),
      ),
      child: Icon(Icons.public, color: sun, size: size * .55),
    );
  }
}

class ProductImage extends StatelessWidget {
  const ProductImage({super.key, required this.url});

  final String url;

  @override
  Widget build(BuildContext context) {
    if (url.isEmpty) {
      return Container(
        color: const Color(0xFFEAF0EA),
        child: const Icon(Icons.image_outlined, color: Color(0xFF9AA39D)),
      );
    }
    return CachedNetworkImage(
      imageUrl: url,
      fit: BoxFit.cover,
      placeholder: (_, _) => Container(color: const Color(0xFFEAF0EA)),
      errorWidget: (_, _, _) => Container(
        color: const Color(0xFFEAF0EA),
        child: const Icon(Icons.broken_image_outlined),
      ),
    );
  }
}

class HeroImage extends StatelessWidget {
  const HeroImage({super.key, required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final gallery = product.gallery.isEmpty
        ? [product.imageUrl]
        : product.gallery;
    return Column(
      children: [
        AspectRatio(
          aspectRatio: 1.1,
          child: PageView(
            children: gallery
                .map(
                  (url) => Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 26),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(28),
                      child: ProductImage(url: url),
                    ),
                  ),
                )
                .toList(),
          ),
        ),
        const SizedBox(height: 10),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(
            gallery.length.clamp(1, 5),
            (index) => Container(
              width: index == 0 ? 18 : 7,
              height: 7,
              margin: const EdgeInsets.symmetric(horizontal: 3),
              decoration: BoxDecoration(
                color: index == 0 ? green : softLine,
                borderRadius: BorderRadius.circular(999),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class WishlistButton extends StatelessWidget {
  const WishlistButton({super.key, required this.product, this.large = false});

  final Product product;
  final bool large;

  @override
  Widget build(BuildContext context) {
    return Consumer<WishlistController>(
      builder: (_, wishlist, _) {
        final saved = wishlist.has(product.id);
        return IconButton.filledTonal(
          constraints: BoxConstraints.tightFor(
            width: large ? 44 : 36,
            height: large ? 44 : 36,
          ),
          padding: EdgeInsets.zero,
          style: IconButton.styleFrom(
            backgroundColor: Colors.white.withValues(alpha: .92),
          ),
          onPressed: () => wishlist.toggle(product),
          icon: Icon(
            saved ? Icons.favorite : Icons.favorite_border,
            color: saved ? Colors.redAccent : textDark,
            size: large ? 22 : 18,
          ),
        );
      },
    );
  }
}

class QtyControl extends StatelessWidget {
  const QtyControl({
    super.key,
    required this.quantity,
    required this.onChanged,
    this.small = false,
  });

  final int quantity;
  final ValueChanged<int> onChanged;
  final bool small;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton.outlined(
          constraints: BoxConstraints.tightFor(
            width: small ? 30 : 36,
            height: small ? 30 : 36,
          ),
          padding: EdgeInsets.zero,
          onPressed: () => onChanged(max(1, quantity - 1)),
          icon: const Icon(Icons.remove, size: 16),
        ),
        Padding(
          padding: EdgeInsets.symmetric(horizontal: small ? 6 : 10),
          child: Text(
            '$quantity',
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
        ),
        IconButton.outlined(
          constraints: BoxConstraints.tightFor(
            width: small ? 30 : 36,
            height: small ? 30 : 36,
          ),
          padding: EdgeInsets.zero,
          style: IconButton.styleFrom(
            side: const BorderSide(color: orange),
            foregroundColor: orange,
          ),
          onPressed: () => onChanged(quantity + 1),
          icon: const Icon(Icons.add, size: 16),
        ),
      ],
    );
  }
}

class DetailAccordion extends StatelessWidget {
  const DetailAccordion({super.key, required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return ExpansionTile(
      tilePadding: EdgeInsets.zero,
      childrenPadding: const EdgeInsets.only(bottom: 14),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
      children: [
        Text(
          body.isEmpty ? 'Details will be available soon.' : body,
          style: const TextStyle(height: 1.45, color: Color(0xFF5C6861)),
        ),
      ],
    );
  }
}

class ProductInfoPanel extends StatelessWidget {
  const ProductInfoPanel({super.key, required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final weight = product.weight > 0
        ? '${product.weight.toStringAsFixed(product.weight % 1 == 0 ? 0 : 2)} lb'
        : 'Not specified';
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: pageBg,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Product Information',
            style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
          ),
          const SizedBox(height: 12),
          InfoLine(label: 'Weight', value: weight),
          InfoLine(label: 'Stock', value: _humanize(product.stockStatus)),
          if (product.sku.isNotEmpty)
            InfoLine(label: 'SKU', value: product.sku),
        ],
      ),
    );
  }
}

class RateSellerPanel extends StatelessWidget {
  const RateSellerPanel({
    super.key,
    required this.product,
    required this.onRate,
  });

  final Product product;
  final VoidCallback onRate;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: softLine),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: const Color(0xFFFFF5D8),
              borderRadius: BorderRadius.circular(16),
            ),
            child: const Icon(Icons.star, color: Color(0xFFF4B400)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Rate this seller',
                  style: TextStyle(fontWeight: FontWeight.w900),
                ),
                Text(
                  '${product.rating.toStringAsFixed(1)} rating from ${product.reviewCount} reviews',
                  style: const TextStyle(
                    color: Color(0xFF6C7871),
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
          OutlinedButton(onPressed: onRate, child: const Text('Rate')),
        ],
      ),
    );
  }
}

class InfoLine extends StatelessWidget {
  const InfoLine({super.key, required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(color: Color(0xFF6C7871)),
            ),
          ),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
        ],
      ),
    );
  }
}

class RateSellerSheet extends StatefulWidget {
  const RateSellerSheet({super.key, required this.product});

  final Product product;

  @override
  State<RateSellerSheet> createState() => _RateSellerSheetState();
}

class _RateSellerSheetState extends State<RateSellerSheet> {
  final titleController = TextEditingController();
  final bodyController = TextEditingController();
  int rating = 5;
  bool submitting = false;

  @override
  void dispose() {
    titleController.dispose();
    bodyController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => submitting = true);
    try {
      final message = await context.read<ApiClient>().submitReview(
        context.read<AuthController>().token,
        widget.product.id,
        rating,
        titleController.text,
        bodyController.text,
      );
      if (!mounted) {
        return;
      }
      Navigator.of(context).pop();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() => submitting = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        18,
        18,
        18,
        MediaQuery.of(context).viewInsets.bottom + 18,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Rate this seller',
            style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          Text(
            widget.product.vendorName ?? 'Seller Africa vendor',
            style: const TextStyle(color: Color(0xFF6C7871)),
          ),
          const SizedBox(height: 16),
          Row(
            children: List.generate(
              5,
              (index) => IconButton(
                onPressed: () => setState(() => rating = index + 1),
                icon: Icon(
                  index < rating ? Icons.star : Icons.star_border,
                  color: const Color(0xFFF4B400),
                  size: 32,
                ),
              ),
            ),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: titleController,
            decoration: const InputDecoration(
              labelText: 'Review title',
              filled: true,
              fillColor: pageBg,
              border: OutlineInputBorder(borderSide: BorderSide.none),
            ),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: bodyController,
            minLines: 3,
            maxLines: 5,
            decoration: const InputDecoration(
              labelText: 'Tell us about your experience',
              filled: true,
              fillColor: pageBg,
              border: OutlineInputBorder(borderSide: BorderSide.none),
            ),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: submitting ? null : _submit,
              child: submitting
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Submit Rating'),
            ),
          ),
        ],
      ),
    );
  }
}

class SellerInfoCard extends StatelessWidget {
  const SellerInfoCard({super.key, required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: pageBg,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Row(
        children: [
          const CircleAvatar(
            backgroundColor: green,
            child: Icon(Icons.storefront, color: Colors.white),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  product.vendorName ?? 'Seller Africa vendor',
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const Text(
                  'Verified seller • Fast response',
                  style: TextStyle(color: Color(0xFF6C7871), fontSize: 12),
                ),
              ],
            ),
          ),
          OutlinedButton(onPressed: () {}, child: const Text('Visit Store')),
        ],
      ),
    );
  }
}

class RoundIcon extends StatelessWidget {
  const RoundIcon({super.key, required this.icon, this.onTap});

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: onTap,
      child: Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
        ),
        child: Icon(icon, color: textDark),
      ),
    );
  }
}

class SectionHeader extends StatelessWidget {
  const SectionHeader({
    super.key,
    required this.title,
    required this.action,
    this.onTap,
  });

  final String title;
  final String action;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w900,
              color: textDark,
            ),
          ),
        ),
        InkWell(
          onTap: onTap,
          child: Text(
            action,
            style: const TextStyle(color: green, fontWeight: FontWeight.w900),
          ),
        ),
      ],
    );
  }
}

class DealBadge extends StatelessWidget {
  const DealBadge({super.key, required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: sun,
        borderRadius: BorderRadius.circular(7),
      ),
      child: Text(
        text,
        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900),
      ),
    );
  }
}

class SummaryRow extends StatelessWidget {
  const SummaryRow({
    super.key,
    required this.label,
    required this.value,
    this.bold = false,
  });

  final String label;
  final String value;
  final bool bold;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: bold ? textDark : const Color(0xFF7B877F),
                fontWeight: bold ? FontWeight.w900 : FontWeight.w600,
              ),
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontWeight: bold ? FontWeight.w900 : FontWeight.w700,
              color: textDark,
            ),
          ),
        ],
      ),
    );
  }
}

class CheckoutField extends StatelessWidget {
  const CheckoutField({
    super.key,
    required this.controller,
    required this.label,
    this.required = true,
    this.onChanged,
  });

  final TextEditingController controller;
  final String label;
  final bool required;
  final ValueChanged<String>? onChanged;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      onChanged: onChanged,
      validator: (value) => required && (value ?? '').trim().isEmpty
          ? '$label is required.'
          : null,
      decoration: InputDecoration(
        labelText: label,
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide.none,
        ),
      ),
    );
  }
}

class DeliveryOption extends StatelessWidget {
  const DeliveryOption({
    super.key,
    required this.title,
    required this.subtitle,
    required this.price,
  });

  final String title;
  final String subtitle;
  final String price;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: const Icon(Icons.local_shipping_outlined, color: green),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
      subtitle: Text(subtitle),
      trailing: Text(
        price,
        style: const TextStyle(fontWeight: FontWeight.w900),
      ),
    );
  }
}

class CheckoutAuthPrompt extends StatelessWidget {
  const CheckoutAuthPrompt({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    if (auth.isLoggedIn) {
      return Container(
        width: double.infinity,
        margin: const EdgeInsets.fromLTRB(18, 8, 18, 0),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: green.withValues(alpha: .10),
          borderRadius: BorderRadius.circular(18),
        ),
        child: Text(
          'Checking out as ${auth.displayName}',
          style: const TextStyle(color: deepGreen, fontWeight: FontWeight.w900),
        ),
      );
    }

    return Container(
      margin: const EdgeInsets.fromLTRB(18, 8, 18, 0),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Login or continue as guest',
            style: TextStyle(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 4),
          const Text(
            'You can pay without authentication. Login or register only if you want saved addresses and order history.',
            style: TextStyle(color: Color(0xFF6F7B75), fontSize: 12),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => showAuthSheet(context, isRegister: false),
                  child: const Text('Login'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton.tonal(
                  onPressed: () => showAuthSheet(context, isRegister: true),
                  child: const Text('Register'),
                ),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Text(
                  'Guest checkout allowed',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: green,
                    fontWeight: FontWeight.w900,
                    fontSize: 12,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class PaymentOption extends StatelessWidget {
  const PaymentOption({super.key, required this.icon, required this.title});

  final IconData icon;
  final String title;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon, color: green),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
    );
  }
}

class OrderCard extends StatelessWidget {
  const OrderCard({super.key, required this.order});

  final BuyerOrderSummary order;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  order.orderNumber,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
              StatusPill(text: order.statusLabel),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            [
              order.paymentStatusLabel,
              order.fulfillmentStatusLabel,
              order.placedAtLabel,
            ].where((item) => item.isNotEmpty).join(' • '),
            style: const TextStyle(color: Color(0xFF7B877F)),
          ),
          const SizedBox(height: 10),
          Text(
            money.format(order.total),
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              OutlinedButton.icon(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => OrderTrackingScreen(orderId: order.id),
                  ),
                ),
                icon: const Icon(Icons.local_shipping_outlined, size: 18),
                label: const Text('Track'),
              ),
              const SizedBox(width: 8),
              OutlinedButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const ChatSupportScreen()),
                ),
                child: const Text('Contact Support'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class OrderTrackingScreen extends StatefulWidget {
  const OrderTrackingScreen({super.key, required this.orderId});

  final int orderId;

  @override
  State<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends State<OrderTrackingScreen> {
  late Future<BuyerOrderDetail> _orderFuture;

  @override
  void initState() {
    super.initState();
    final auth = context.read<AuthController>();
    _orderFuture = context.read<ApiClient>().order(auth.token, widget.orderId);
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    if (!auth.isLoggedIn) {
      return Scaffold(
        appBar: AppBar(title: const Text('Track order')),
        body: Center(
          child: EmptyState(
            icon: Icons.lock_outline,
            title: 'Login required',
            body: 'Sign in to view saved tracking details for this order.',
            actions: [
              FilledButton(
                onPressed: () => showAuthSheet(context, isRegister: false),
                child: const Text('Login'),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Track order')),
      body: FutureBuilder<BuyerOrderDetail>(
        future: _orderFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ErrorState(
              message: snapshot.error.toString(),
              onRetry: () => setState(
                () => _orderFuture = context.read<ApiClient>().order(
                  auth.token,
                  widget.orderId,
                ),
              ),
            );
          }

          final order = snapshot.data!;
          final shipment = order.shipments.isNotEmpty
              ? order.shipments.first
              : null;
          return RefreshIndicator(
            onRefresh: () async {
              setState(() {
                _orderFuture = context.read<ApiClient>().order(
                  auth.token,
                  widget.orderId,
                );
              });
              await _orderFuture;
            },
            child: ListView(
              padding: const EdgeInsets.all(18),
              children: [
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(24),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              order.orderNumber,
                              style: const TextStyle(
                                fontSize: 22,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          StatusPill(text: order.statusLabel),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        '${order.paymentStatusLabel} • ${order.placedAtLabel}',
                        style: const TextStyle(color: Color(0xFF7B877F)),
                      ),
                      const SizedBox(height: 14),
                      Text(
                        money.format(order.total),
                        style: const TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(24),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Tracking',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 14),
                      for (final step in order.trackingSteps)
                        TrackingStepTile(step: step),
                      if (shipment != null) ...[
                        const Divider(height: 26),
                        if (shipment.carrier.isNotEmpty)
                          TrackingMeta(
                            label: 'Carrier',
                            value: shipment.carrier,
                          ),
                        if (shipment.trackingNumber.isNotEmpty)
                          TrackingMeta(
                            label: 'Tracking number',
                            value: shipment.trackingNumber,
                          ),
                        if (shipment.trackingUrl.isNotEmpty)
                          FilledButton.icon(
                            onPressed: () => launchUrl(
                              Uri.parse(shipment.trackingUrl),
                              mode: LaunchMode.externalApplication,
                            ),
                            icon: const Icon(Icons.open_in_new),
                            label: const Text('Open tracking link'),
                          ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(24),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Items',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 12),
                      for (final item in order.items)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Row(
                            children: [
                              Expanded(
                                child: Text(
                                  '${item.quantityText} x ${item.name}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                              Text(money.format(item.total)),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class TrackingStepTile extends StatelessWidget {
  const TrackingStepTile({super.key, required this.step});

  final TrackingStep step;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            step.complete ? Icons.check_circle : Icons.radio_button_unchecked,
            color: step.complete ? green : const Color(0xFF9AA59F),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  step.title,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                if (step.subtitle.isNotEmpty)
                  Text(
                    step.subtitle,
                    style: const TextStyle(
                      color: Color(0xFF7B877F),
                      fontSize: 12,
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class TrackingMeta extends StatelessWidget {
  const TrackingMeta({super.key, required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(color: Color(0xFF7B877F)),
            ),
          ),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
        ],
      ),
    );
  }
}

class StatusPill extends StatelessWidget {
  const StatusPill({super.key, required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: green.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(99),
      ),
      child: Text(
        text,
        style: const TextStyle(
          color: green,
          fontWeight: FontWeight.w900,
          fontSize: 12,
        ),
      ),
    );
  }
}

class AccountTile extends StatelessWidget {
  const AccountTile({
    super.key,
    required this.icon,
    required this.title,
    this.onTap,
  });

  final IconData icon;
  final String title;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
      ),
      child: ListTile(
        leading: Icon(icon, color: green),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.icon,
    required this.title,
    required this.body,
    this.actions = const [],
  });

  final IconData icon;
  final String title;
  final String body;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: green, size: 48),
          const SizedBox(height: 12),
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          Text(
            body,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Color(0xFF7B877F), height: 1.4),
          ),
          if (actions.isNotEmpty) ...[
            const SizedBox(height: 16),
            Wrap(
              alignment: WrapAlignment.center,
              spacing: 10,
              runSpacing: 10,
              children: actions,
            ),
          ],
        ],
      ),
    );
  }
}

class ErrorState extends StatelessWidget {
  const ErrorState({super.key, required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.wifi_off_outlined, color: green, size: 48),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 14),
            FilledButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}

class CircleBlob extends StatelessWidget {
  const CircleBlob({super.key, required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: color, shape: BoxShape.circle),
    );
  }
}

class _AvatarDot extends StatelessWidget {
  const _AvatarDot({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 42,
      height: 42,
      margin: const EdgeInsets.only(right: 4),
      decoration: BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.black, width: 2),
      ),
      child: Center(
        child: Text(label, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
    );
  }
}

class AuthController extends ChangeNotifier {
  static const _sessionKey = 'auth_session_v1';
  static const _sessionSavedAtKey = 'auth_session_saved_at_v1';
  static const _sessionDuration = Duration(days: 30);

  AuthSession? session;
  List<AddressBookItem> addresses = [];

  bool get isLoggedIn => session?.token.isNotEmpty == true;
  String get token => session?.token ?? '';
  String get displayName => session?.displayName ?? 'Guest shopper';
  String get email => session?.email ?? '';

  Future<void> restore(ApiClient api) async {
    final prefs = await SharedPreferences.getInstance();
    final savedAt = prefs.getInt(_sessionSavedAtKey) ?? 0;
    final payload = prefs.getString(_sessionKey);
    final expired =
        savedAt <= 0 ||
        DateTime.now().difference(
              DateTime.fromMillisecondsSinceEpoch(savedAt),
            ) >
            _sessionDuration;

    if (payload == null || expired) {
      await _clearPersistedSession(prefs);
      return;
    }

    try {
      session = AuthSession.fromStorage(
        jsonDecode(payload) as Map<String, dynamic>,
      );
      addresses = await api.addresses(token);
    } catch (_) {
      session = null;
      addresses = [];
      await _clearPersistedSession(prefs);
    }
    notifyListeners();
  }

  Future<void> login(ApiClient api, String email, String password) async {
    session = await api.login(email, password);
    addresses = await api.addresses(token);
    await _persistSession();
    notifyListeners();
  }

  Future<String> register(ApiClient api, Map<String, String> values) async {
    return api.register(values);
  }

  Future<void> saveAddress(ApiClient api, Map<String, String> values) async {
    if (!isLoggedIn) {
      throw Exception('Sign in to save an address.');
    }
    addresses = await api.saveAddress(token, values);
    notifyListeners();
  }

  Future<void> logout() async {
    session = null;
    addresses = [];
    await _clearPersistedSession(await SharedPreferences.getInstance());
    notifyListeners();
  }

  Future<void> _persistSession() async {
    final current = session;
    if (current == null) {
      return;
    }
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_sessionKey, jsonEncode(current.toStorage()));
    await prefs.setInt(
      _sessionSavedAtKey,
      DateTime.now().millisecondsSinceEpoch,
    );
  }

  Future<void> _clearPersistedSession(SharedPreferences prefs) async {
    await prefs.remove(_sessionKey);
    await prefs.remove(_sessionSavedAtKey);
  }
}

class LocationController extends ChangeNotifier {
  static const _cityKey = 'delivery_city_v1';
  static const _stateKey = 'delivery_state_v1';
  static const _countryKey = 'delivery_country_v1';
  static const _latKey = 'delivery_lat_v1';
  static const _lngKey = 'delivery_lng_v1';

  String city = '';
  String state = '';
  String country = 'US';
  double? latitude;
  double? longitude;

  bool get hasLocation => city.trim().isNotEmpty || latitude != null;
  String get displayName => hasLocation
      ? [city, state].where((item) => item.trim().isNotEmpty).join(', ')
      : 'Add location';

  LatLng get mapCenter => LatLng(latitude ?? 33.7490, longitude ?? -84.3880);

  Future<void> restore() async {
    final prefs = await SharedPreferences.getInstance();
    city = prefs.getString(_cityKey) ?? '';
    state = prefs.getString(_stateKey) ?? '';
    country = prefs.getString(_countryKey) ?? 'US';
    latitude = prefs.getDouble(_latKey);
    longitude = prefs.getDouble(_lngKey);
    notifyListeners();
  }

  Future<void> save({
    required String city,
    required String state,
    required String country,
    double? latitude,
    double? longitude,
  }) async {
    this.city = city.trim();
    this.state = state.trim();
    this.country = country.trim().isEmpty ? 'US' : country.trim();
    this.latitude = latitude ?? this.latitude;
    this.longitude = longitude ?? this.longitude;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_cityKey, this.city);
    await prefs.setString(_stateKey, this.state);
    await prefs.setString(_countryKey, this.country);
    if (this.latitude != null) {
      await prefs.setDouble(_latKey, this.latitude!);
    }
    if (this.longitude != null) {
      await prefs.setDouble(_lngKey, this.longitude!);
    }
    notifyListeners();
  }

  Future<void> useCurrentLocation() async {
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw Exception(
        'Location permission is required to find farmers near you.',
      );
    }
    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.medium,
      ),
    );
    await save(
      city: city.isEmpty ? 'Current location' : city,
      state: state,
      country: country,
      latitude: position.latitude,
      longitude: position.longitude,
    );
  }
}

class CartController extends ChangeNotifier {
  final Map<int, CartItem> _items = {};

  List<CartItem> get items => _items.values.toList();
  int get count =>
      _items.values.fold(0, (total, item) => total + item.quantity);
  double get subtotal => _items.values.fold(
    0,
    (total, item) => total + item.product.price * item.quantity,
  );
  double get shippingEstimate => subtotal > 0 ? 10 : 0;
  double get estimatedTotal => subtotal + shippingEstimate;
  double totalWithShipping(double shipping) => subtotal + shipping;

  Map<String, List<CartItem>> get itemsBySeller {
    final grouped = <String, List<CartItem>>{};
    for (final item in items) {
      grouped
          .putIfAbsent(
            item.product.vendorName ?? 'Seller Africa vendor',
            () => [],
          )
          .add(item);
    }
    return grouped;
  }

  void add(Product product, {int quantity = 1}) {
    final current = _items[product.id];
    _items[product.id] = CartItem(
      product: product,
      quantity: (current?.quantity ?? 0) + quantity,
    );
    notifyListeners();
  }

  void setQuantity(int productId, int quantity) {
    if (quantity <= 0) {
      _items.remove(productId);
    } else {
      final item = _items[productId];
      if (item != null) {
        _items[productId] = CartItem(product: item.product, quantity: quantity);
      }
    }
    notifyListeners();
  }

  void clear() {
    _items.clear();
    notifyListeners();
  }
}

class WishlistController extends ChangeNotifier {
  final Map<int, Product> _items = {};

  List<Product> get items => _items.values.toList();

  bool has(int productId) => _items.containsKey(productId);

  void toggle(Product product) {
    if (has(product.id)) {
      _items.remove(product.id);
    } else {
      _items[product.id] = product;
    }
    notifyListeners();
  }
}

class ApiClient {
  ApiClient(this.baseUrl);

  final String baseUrl;

  Future<HomePayload> home() async =>
      HomePayload.fromJson(await _get('catalog/home'));

  Future<List<CategoryItem>> categories() async {
    final data = await _get('catalog/categories');
    return (data['categories'] as List? ?? [])
        .map((item) => CategoryItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<List<Vendor>> vendors({int limit = 50}) async {
    final uri = Uri.parse(
      '$baseUrl/catalog/vendors',
    ).replace(queryParameters: {'limit': limit.toString()});
    final data = await _getUri(uri, 'catalog/vendors');
    return (data['vendors'] as List? ?? [])
        .whereType<Map>()
        .map((item) => Vendor.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }

  Future<List<Product>> vendorProducts(int vendorId) async {
    final data = await _get('catalog/vendors/$vendorId/products');
    return (data['products'] as List? ?? [])
        .whereType<Map>()
        .map((item) => Product.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }

  Future<List<Product>> products({
    String query = '',
    String category = '',
    String sort = 'latest',
  }) async {
    final resolvedSort = sort == 'recommended' ? 'latest' : sort;
    final uri = Uri.parse('$baseUrl/catalog/products').replace(
      queryParameters: {
        if (query.trim().isNotEmpty) 'q': query.trim(),
        if (category.trim().isNotEmpty) 'category': category.trim(),
        'sort': resolvedSort,
        'limit': '50',
      },
    );
    final data = await _getUri(uri, 'catalog/products');
    return (data['products'] as List? ?? [])
        .map((item) => Product.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<Product> product(int id) async => Product.fromJson(
    (await _get('catalog/products/$id'))['product'] as Map<String, dynamic>,
  );

  Future<AuthSession> login(String email, String password) async {
    final response = await _postJson('auth/login', {
      'email': email,
      'password': password,
      'device_name': 'Seller Africa Flutter App',
    });
    final data = _decode(response);
    return AuthSession.fromJson(data);
  }

  Future<String> register(Map<String, String> values) async {
    final response = await _postJson('auth/register', values);
    final data = _decode(response);
    return data['message']?.toString() ??
        'Account created. Sign in to continue.';
  }

  Future<List<AddressBookItem>> addresses(String token) async {
    final data = await _sendAuthed(
      Uri.parse('$baseUrl/buyer/addresses'),
      token,
    );
    return (data['addresses'] as List? ?? [])
        .map((item) => AddressBookItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<List<AddressBookItem>> saveAddress(
    String token,
    Map<String, String> values,
  ) async {
    final response = await _postJson('buyer/addresses', {
      ...values,
      'type': 'shipping',
      'country_code': values['country_code'] ?? 'US',
      'is_default': '1',
    }, token: token);
    final data = _decode(response);
    return (data['addresses'] as List? ?? [])
        .map((item) => AddressBookItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<List<BuyerOrderSummary>> orders(String token) async {
    final data = await _sendAuthed(
      Uri.parse('$baseUrl/buyer/orders'),
      token,
      route: 'buyer/orders',
    );
    return (data['orders'] as List? ?? [])
        .whereType<Map>()
        .map(
          (item) => BuyerOrderSummary.fromJson(Map<String, dynamic>.from(item)),
        )
        .toList();
  }

  Future<BuyerOrderDetail> order(String token, int orderId) async {
    final data = await _sendAuthed(
      Uri.parse('$baseUrl/buyer/orders/$orderId'),
      token,
      route: 'buyer/orders/$orderId',
    );
    return BuyerOrderDetail.fromJson(data['order'] as Map<String, dynamic>);
  }

  Future<String> submitReview(
    String token,
    int productId,
    int rating,
    String title,
    String body,
  ) async {
    final response = await _postJson('buyer/reviews', {
      'product_id': productId,
      'rating': rating,
      'title': title.trim().isEmpty ? 'Seller review' : title.trim(),
      'body': body.trim(),
    }, token: token);
    final data = _decode(response);
    return data['message']?.toString() ?? 'Review submitted for approval.';
  }

  Future<String> createCheckoutSession(
    List<CartItem> items,
    Map<String, String> customer, {
    String? token,
  }) async {
    final body = jsonEncode({
      'cart_items': items
          .map(
            (item) => {
              'product_id': item.product.id,
              'quantity': item.quantity,
            },
          )
          .toList(),
      'customer': customer,
    });
    final response = await _postWithFallback(
      'checkout/session',
      body,
      token: token,
    );
    final data = _decode(response);
    return (data['checkout_url'] ?? data['redirect_url']) as String;
  }

  Future<Map<String, dynamic>> _get(String path) =>
      _getUri(Uri.parse('$baseUrl/$path'), path);

  Future<Map<String, dynamic>> _getUri(Uri uri, String route) async {
    try {
      return await _send(uri);
    } catch (_) {
      return _send(_mobilePhpUri(route, uri.queryParameters));
    }
  }

  Future<Map<String, dynamic>> _send(Uri uri) async {
    late http.Response response;
    try {
      response = await http
          .get(uri, headers: const {'Accept': 'application/json'})
          .timeout(const Duration(seconds: 18));
    } catch (_) {
      throw Exception(
        'We could not connect to Seller Africa. Check your internet connection and try again.',
      );
    }
    return _decode(response);
  }

  Future<Map<String, dynamic>> _sendAuthed(
    Uri uri,
    String token, {
    String? route,
  }) async {
    late http.Response response;
    final headers = {
      'Accept': 'application/json',
      'Authorization': 'Bearer $token',
    };
    try {
      response = await http
          .get(uri, headers: headers)
          .timeout(const Duration(seconds: 18));
      if (route != null && response.statusCode == 404) {
        response = await http
            .get(_mobilePhpUri(route, uri.queryParameters), headers: headers)
            .timeout(const Duration(seconds: 18));
      }
    } catch (_) {
      if (route == null) {
        throw Exception(
          'We could not connect to Seller Africa. Check your internet connection and try again.',
        );
      }
      response = await http
          .get(_mobilePhpUri(route, uri.queryParameters), headers: headers)
          .timeout(const Duration(seconds: 18));
    }
    return _decode(response);
  }

  Future<http.Response> _postJson(
    String route,
    Map<String, dynamic> body, {
    String? token,
  }) async {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
    final encodedBody = jsonEncode(body);
    try {
      final response = await http
          .post(
            Uri.parse('$baseUrl/$route'),
            headers: headers,
            body: encodedBody,
          )
          .timeout(const Duration(seconds: 18));
      if (response.statusCode != 404) {
        return response;
      }
    } catch (_) {
      // Try the direct mobile.php endpoint below.
    }
    return http
        .post(_mobilePhpUri(route), headers: headers, body: encodedBody)
        .timeout(const Duration(seconds: 18));
  }

  Future<http.Response> _postWithFallback(
    String route,
    String body, {
    String? token,
  }) async {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
    try {
      return await http
          .post(Uri.parse('$baseUrl/$route'), headers: headers, body: body)
          .timeout(const Duration(seconds: 18));
    } catch (_) {
      return http
          .post(_mobilePhpUri(route), headers: headers, body: body)
          .timeout(const Duration(seconds: 18));
    }
  }

  Uri _mobilePhpUri(String route, [Map<String, String> query = const {}]) {
    final base = Uri.parse(baseUrl);
    return base.replace(
      path: '/api/mobile.php',
      queryParameters: {'route': route, ...query},
    );
  }

  Map<String, dynamic> _decode(http.Response response) {
    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 400 || data['ok'] != true) {
      throw Exception(data['message'] ?? 'Request failed.');
    }
    return data;
  }
}

class HomePayload {
  HomePayload({
    required this.banners,
    required this.products,
    required this.latest,
    required this.categories,
    required this.vendors,
  });

  final List<AppBanner> banners;
  final List<Product> products;
  final List<Product> latest;
  final List<CategoryItem> categories;
  final List<Vendor> vendors;

  factory HomePayload.fromJson(Map<String, dynamic> json) {
    final featured = (json['featured'] as List? ?? [])
        .map((item) => Product.fromJson(item as Map<String, dynamic>))
        .toList();
    final latest = (json['latest'] as List? ?? [])
        .map((item) => Product.fromJson(item as Map<String, dynamic>))
        .toList();
    return HomePayload(
      banners: (json['banners'] as List? ?? [])
          .map((item) => AppBanner.fromJson(item as Map<String, dynamic>))
          .toList(),
      products: featured.isEmpty ? latest : [...featured, ...latest],
      latest: latest,
      categories: (json['categories'] as List? ?? [])
          .map((item) => CategoryItem.fromJson(item as Map<String, dynamic>))
          .toList(),
      vendors: (json['vendors'] as List? ?? [])
          .map((item) => Vendor.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }
}

class AppBanner {
  AppBanner({
    required this.title,
    required this.subtitle,
    required this.imageUrl,
    required this.targetUrl,
  });

  final String title;
  final String subtitle;
  final String imageUrl;
  final String targetUrl;

  factory AppBanner.fromJson(Map<String, dynamic> json) => AppBanner(
    title: json['title']?.toString() ?? '',
    subtitle: json['subtitle']?.toString() ?? '',
    imageUrl: json['image_url']?.toString() ?? '',
    targetUrl: json['target_url']?.toString() ?? '',
  );
}

class AuthSession {
  AuthSession({
    required this.token,
    required this.email,
    required this.displayName,
  });

  final String token;
  final String email;
  final String displayName;

  factory AuthSession.fromJson(Map<String, dynamic> json) {
    final user = json['user'] is Map<String, dynamic>
        ? json['user'] as Map<String, dynamic>
        : <String, dynamic>{};
    return AuthSession(
      token: json['access_token']?.toString() ?? '',
      email: user['email']?.toString() ?? '',
      displayName:
          user['display_name']?.toString() ??
          user['email']?.toString() ??
          'Customer',
    );
  }

  factory AuthSession.fromStorage(Map<String, dynamic> json) => AuthSession(
    token: json['token']?.toString() ?? '',
    email: json['email']?.toString() ?? '',
    displayName: json['display_name']?.toString() ?? 'Customer',
  );

  Map<String, dynamic> toStorage() => {
    'token': token,
    'email': email,
    'display_name': displayName,
  };
}

class AddressBookItem {
  AddressBookItem({
    required this.name,
    required this.summary,
    required this.raw,
  });

  final String name;
  final String summary;
  final Map<String, String> raw;

  factory AddressBookItem.fromJson(Map<String, dynamic> json) {
    final first = json['first_name']?.toString() ?? '';
    final last = json['last_name']?.toString() ?? '';
    final name = '$first $last'.trim();
    final parts =
        [
              json['address_line1'],
              json['address_line2'],
              json['city'],
              json['state'],
              json['postcode'],
              json['country_code'],
            ]
            .map((item) => item?.toString().trim() ?? '')
            .where((item) => item.isNotEmpty)
            .toList();
    return AddressBookItem(
      name: name.isNotEmpty ? name : 'Saved address',
      summary: parts.join(', '),
      raw: {
        for (final key in [
          'first_name',
          'last_name',
          'email',
          'phone',
          'address_line1',
          'address_line2',
          'city',
          'state',
          'postcode',
          'country_code',
        ])
          key: json[key]?.toString() ?? '',
      },
    );
  }
}

class BuyerOrderSummary {
  BuyerOrderSummary({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.paymentStatus,
    required this.fulfillmentStatus,
    required this.total,
    required this.placedAt,
  });

  final int id;
  final String orderNumber;
  final String status;
  final String paymentStatus;
  final String fulfillmentStatus;
  final double total;
  final DateTime? placedAt;

  String get statusLabel => _humanize(status.isNotEmpty ? status : 'pending');
  String get paymentStatusLabel =>
      _humanize(paymentStatus.isNotEmpty ? paymentStatus : 'payment pending');
  String get fulfillmentStatusLabel => _humanize(
    fulfillmentStatus.isNotEmpty ? fulfillmentStatus : 'fulfillment pending',
  );
  String get placedAtLabel =>
      placedAt == null ? '' : DateFormat('MMM d, yyyy').format(placedAt!);

  bool matchesStatus(String tab) {
    if (tab == 'All') {
      return true;
    }
    final needle = tab.toLowerCase();
    final haystack = '$status $fulfillmentStatus'.toLowerCase();
    if (needle == 'returned') {
      return haystack.contains('return') || haystack.contains('refund');
    }
    if (needle == 'processing') {
      return haystack.contains('processing') ||
          haystack.contains('accepted') ||
          haystack.contains('preparing') ||
          haystack.contains('ready');
    }
    return haystack.contains(needle);
  }

  factory BuyerOrderSummary.fromJson(Map<String, dynamic> json) {
    final id = _intValue(json['id']);
    return BuyerOrderSummary(
      id: id,
      orderNumber: json['order_number']?.toString().trim().isNotEmpty == true
          ? json['order_number'].toString()
          : 'Order #$id',
      status: json['status']?.toString() ?? '',
      paymentStatus: json['payment_status']?.toString() ?? '',
      fulfillmentStatus: json['fulfillment_status']?.toString() ?? '',
      total: _doubleValue(json['grand_total'] ?? json['total']),
      placedAt: _dateValue(json['placed_at'] ?? json['created_at']),
    );
  }
}

class BuyerOrderDetail extends BuyerOrderSummary {
  BuyerOrderDetail({
    required super.id,
    required super.orderNumber,
    required super.status,
    required super.paymentStatus,
    required super.fulfillmentStatus,
    required super.total,
    required super.placedAt,
    required this.items,
    required this.shipments,
  });

  final List<OrderLine> items;
  final List<ShipmentInfo> shipments;

  List<TrackingStep> get trackingSteps {
    final orderState =
        '$status $paymentStatus $fulfillmentStatus '
                '${shipments.map((shipment) => shipment.status).join(' ')}'
            .toLowerCase();
    final hasShipment = shipments.isNotEmpty;
    final paid =
        orderState.contains('paid') ||
        orderState.contains('completed') ||
        orderState.contains('captured');
    final preparing =
        orderState.contains('accepted') ||
        orderState.contains('processing') ||
        orderState.contains('preparing') ||
        orderState.contains('ready');
    final shipped =
        hasShipment ||
        orderState.contains('shipped') ||
        orderState.contains('in_transit');
    final delivered =
        orderState.contains('delivered') ||
        orderState.contains('fulfilled') ||
        orderState.contains('complete');

    return [
      TrackingStep(
        title: 'Order placed',
        subtitle: placedAtLabel,
        complete: true,
      ),
      TrackingStep(
        title: 'Payment confirmed',
        subtitle: paymentStatusLabel,
        complete: paid,
      ),
      TrackingStep(
        title: 'Seller processing',
        subtitle: fulfillmentStatusLabel,
        complete: preparing || shipped || delivered,
      ),
      TrackingStep(
        title: 'Shipped',
        subtitle: hasShipment
            ? shipments.first.trackingNumber
            : 'Tracking appears here once added.',
        complete: shipped || delivered,
      ),
      TrackingStep(
        title: 'Delivered',
        subtitle: delivered
            ? 'Delivered to customer'
            : 'Waiting for delivery confirmation.',
        complete: delivered,
      ),
    ];
  }

  factory BuyerOrderDetail.fromJson(Map<String, dynamic> json) {
    final summary = BuyerOrderSummary.fromJson(json);
    return BuyerOrderDetail(
      id: summary.id,
      orderNumber: summary.orderNumber,
      status: summary.status,
      paymentStatus: summary.paymentStatus,
      fulfillmentStatus: summary.fulfillmentStatus,
      total: summary.total,
      placedAt: summary.placedAt,
      items: (json['items'] as List? ?? [])
          .whereType<Map>()
          .map((item) => OrderLine.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
      shipments: (json['shipments'] as List? ?? [])
          .whereType<Map>()
          .map((item) => ShipmentInfo.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
    );
  }
}

class OrderLine {
  OrderLine({required this.name, required this.quantity, required this.total});

  final String name;
  final double quantity;
  final double total;

  String get quantityText => quantity == quantity.roundToDouble()
      ? quantity.toInt().toString()
      : quantity.toString();

  factory OrderLine.fromJson(Map<String, dynamic> json) => OrderLine(
    name: (json['product_name'] ?? json['name'] ?? json['title'] ?? 'Product')
        .toString(),
    quantity: _doubleValue(json['quantity'] ?? json['qty'] ?? 1),
    total: _doubleValue(
      json['line_total'] ?? json['total'] ?? json['subtotal'] ?? 0,
    ),
  );
}

class ShipmentInfo {
  ShipmentInfo({
    required this.status,
    required this.carrier,
    required this.trackingNumber,
    required this.trackingUrl,
  });

  final String status;
  final String carrier;
  final String trackingNumber;
  final String trackingUrl;

  factory ShipmentInfo.fromJson(Map<String, dynamic> json) => ShipmentInfo(
    status: json['status']?.toString() ?? '',
    carrier: (json['carrier'] ?? json['shipping_carrier'] ?? '').toString(),
    trackingNumber: (json['tracking_number'] ?? '').toString(),
    trackingUrl: (json['tracking_url'] ?? '').toString(),
  );
}

class TrackingStep {
  TrackingStep({
    required this.title,
    required this.subtitle,
    required this.complete,
  });

  final String title;
  final String subtitle;
  final bool complete;
}

String _humanize(String value) {
  final words = value
      .replaceAll('_', ' ')
      .replaceAll('-', ' ')
      .trim()
      .split(RegExp(r'\s+'))
      .where((word) => word.isNotEmpty)
      .toList();
  if (words.isEmpty) {
    return '';
  }
  return words
      .map(
        (word) => '${word[0].toUpperCase()}${word.substring(1).toLowerCase()}',
      )
      .join(' ');
}

int _intValue(dynamic value) {
  if (value is num) {
    return value.toInt();
  }
  return int.tryParse(value?.toString() ?? '') ?? 0;
}

double _doubleValue(dynamic value) {
  if (value is num) {
    return value.toDouble();
  }
  return double.tryParse(value?.toString() ?? '') ?? 0;
}

DateTime? _dateValue(dynamic value) {
  final text = value?.toString() ?? '';
  if (text.isEmpty) {
    return null;
  }
  return DateTime.tryParse(text);
}

LatLng farmPoint(Vendor farm, LatLng center, int index) {
  if (farm.latitude != 0 && farm.longitude != 0) {
    return LatLng(farm.latitude, farm.longitude);
  }
  final angle = (index * 47) * pi / 180;
  final radius = .08 + (index % 6) * .035;
  return LatLng(
    center.latitude + sin(angle) * radius,
    center.longitude + cos(angle) * radius,
  );
}

class CategoryItem {
  CategoryItem({
    required this.name,
    required this.slug,
    required this.productCount,
    required this.imageUrl,
  });

  final String name;
  final String slug;
  final int productCount;
  final String imageUrl;

  factory CategoryItem.fromJson(Map<String, dynamic> json) => CategoryItem(
    name: json['name']?.toString() ?? '',
    slug: json['slug']?.toString() ?? '',
    productCount: (json['product_count'] as num?)?.toInt() ?? 0,
    imageUrl: json['image_url']?.toString() ?? json['image']?.toString() ?? '',
  );
}

class Product {
  Product({
    required this.id,
    required this.name,
    required this.sku,
    required this.price,
    required this.oldPrice,
    required this.weight,
    required this.imageUrl,
    required this.shortDescription,
    required this.description,
    required this.rating,
    required this.reviewCount,
    required this.stockStatus,
    required this.gallery,
    this.vendorName,
  });

  final int id;
  final String name;
  final String sku;
  final double price;
  final double oldPrice;
  final double weight;
  final String imageUrl;
  final String shortDescription;
  final String description;
  final double rating;
  final int reviewCount;
  final String stockStatus;
  final List<String> gallery;
  final String? vendorName;

  int get discountPercent => oldPrice <= price || oldPrice <= 0
      ? 0
      : (((oldPrice - price) / oldPrice) * 100).round();

  factory Product.fromJson(Map<String, dynamic> json) {
    final galleryJson = json['gallery'] as List? ?? [];
    return Product(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name']?.toString() ?? '',
      sku: json['sku']?.toString() ?? '',
      price: (json['price'] as num?)?.toDouble() ?? 0,
      oldPrice:
          (json['regular_price'] as num?)?.toDouble() ??
          (json['price'] as num?)?.toDouble() ??
          0,
      weight: _doubleValue(json['weight']),
      imageUrl: json['image_url']?.toString() ?? '',
      shortDescription: json['short_description']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      rating: (json['average_rating'] as num?)?.toDouble() ?? 4.8,
      reviewCount: (json['review_count'] as num?)?.toInt() ?? 0,
      stockStatus: json['stock_status']?.toString() ?? 'in_stock',
      gallery: galleryJson
          .map((item) {
            if (item is Map<String, dynamic>) {
              return item['url']?.toString() ?? '';
            }
            return item.toString();
          })
          .where((url) => url.isNotEmpty)
          .toList(),
      vendorName: json['vendor_name']?.toString(),
    );
  }
}

class Vendor {
  Vendor({
    required this.id,
    required this.name,
    required this.logoUrl,
    required this.bannerUrl,
    required this.description,
    required this.productCount,
    required this.latitude,
    required this.longitude,
  });

  final int id;
  final String name;
  final String logoUrl;
  final String bannerUrl;
  final String description;
  final int productCount;
  final double latitude;
  final double longitude;

  factory Vendor.fromJson(Map<String, dynamic> json) => Vendor(
    id: _intValue(json['id']),
    name: json['store_name']?.toString() ?? 'Seller Africa vendor',
    logoUrl: json['logo_url']?.toString() ?? '',
    bannerUrl: json['banner_url']?.toString() ?? '',
    description: json['description']?.toString() ?? '',
    productCount: (json['product_count'] as num?)?.toInt() ?? 0,
    latitude: _doubleValue(json['latitude'] ?? json['lat']),
    longitude: _doubleValue(json['longitude'] ?? json['lng']),
  );
}

class CartItem {
  CartItem({required this.product, required this.quantity});

  final Product product;
  final int quantity;
}

List<Product> discounted(List<Product> products) =>
    products.where((product) => product.oldPrice > product.price).toList();

const countryOptions = [
  'US',
  'NG',
  'GH',
  'KE',
  'ZA',
  'GB',
  'CA',
  'JM',
  'TT',
  'BB',
  'HT',
  'DO',
  'FR',
  'DE',
  'AE',
  'AU',
];

const usStates = [
  'AL',
  'AK',
  'AZ',
  'AR',
  'CA',
  'CO',
  'CT',
  'DE',
  'FL',
  'GA',
  'HI',
  'IA',
  'ID',
  'IL',
  'IN',
  'KS',
  'KY',
  'LA',
  'MA',
  'MD',
  'ME',
  'MI',
  'MN',
  'MO',
  'MS',
  'MT',
  'NC',
  'ND',
  'NE',
  'NH',
  'NJ',
  'NM',
  'NV',
  'NY',
  'OH',
  'OK',
  'OR',
  'PA',
  'RI',
  'SC',
  'SD',
  'TN',
  'TX',
  'UT',
  'VA',
  'VT',
  'WA',
  'WI',
  'WV',
  'WY',
];

String countryName(String code) => switch (code) {
  'US' => 'United States',
  'NG' => 'Nigeria',
  'GH' => 'Ghana',
  'KE' => 'Kenya',
  'ZA' => 'South Africa',
  'GB' => 'United Kingdom',
  'CA' => 'Canada',
  'JM' => 'Jamaica',
  'TT' => 'Trinidad & Tobago',
  'BB' => 'Barbados',
  'HT' => 'Haiti',
  'DO' => 'Dominican Republic',
  'FR' => 'France',
  'DE' => 'Germany',
  'AE' => 'United Arab Emirates',
  'AU' => 'Australia',
  _ => code,
};

void precacheHomeImages(BuildContext context, HomePayload home) {
  final urls = <String>[
    ...home.banners.map((item) => item.imageUrl),
    ...home.categories.map((item) => item.imageUrl),
    ...home.products.map((item) => item.imageUrl),
    ...home.latest.map((item) => item.imageUrl),
    ...home.vendors.map((item) => item.logoUrl),
  ].where((url) => url.trim().isNotEmpty).toSet();

  for (final url in urls) {
    if (!_queuedImageCacheUrls.add(url)) {
      continue;
    }
    Future.microtask(() {
      if (!context.mounted) {
        return;
      }
      precacheImage(CachedNetworkImageProvider(url), context);
    });
  }
}

void precacheProductImages(BuildContext context, Product product) {
  final urls = <String>{
    product.imageUrl,
    ...product.gallery,
  }.where((url) => url.trim().isNotEmpty).toSet();
  for (final url in urls) {
    if (!_queuedImageCacheUrls.add(url)) {
      continue;
    }
    Future.microtask(() {
      if (!context.mounted) {
        return;
      }
      precacheImage(CachedNetworkImageProvider(url), context);
    });
  }
}

void openSearch(BuildContext context, {CategoryItem? category}) {
  Navigator.of(context).push(
    MaterialPageRoute(builder: (_) => SearchScreen(initialCategory: category)),
  );
}

void showWishlist(BuildContext context) {
  showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    builder: (_) => const WishlistSheet(),
  );
}

void showLocationSheet(BuildContext context) {
  showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    builder: (_) => const LocationSheet(),
  );
}

class LocationSheet extends StatefulWidget {
  const LocationSheet({super.key});

  @override
  State<LocationSheet> createState() => _LocationSheetState();
}

class _LocationSheetState extends State<LocationSheet> {
  late final TextEditingController cityController;
  late final TextEditingController stateController;
  late String country;
  bool locating = false;

  @override
  void initState() {
    super.initState();
    final location = context.read<LocationController>();
    cityController = TextEditingController(text: location.city);
    stateController = TextEditingController(text: location.state);
    country = countryOptions.contains(location.country)
        ? location.country
        : 'US';
  }

  @override
  void dispose() {
    cityController.dispose();
    stateController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    await context.read<LocationController>().save(
      city: cityController.text,
      state: stateController.text,
      country: country,
    );
    if (mounted) {
      Navigator.of(context).pop();
    }
  }

  Future<void> _useCurrentLocation() async {
    setState(() => locating = true);
    try {
      await context.read<LocationController>().useCurrentLocation();
      if (mounted) {
        Navigator.of(context).pop();
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => locating = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        18,
        18,
        18,
        MediaQuery.of(context).viewInsets.bottom + 18,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Set your location',
            style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 14),
          CheckoutField(controller: cityController, label: 'City'),
          const SizedBox(height: 10),
          country == 'US'
              ? DropdownButtonFormField<String>(
                  initialValue: usStates.contains(stateController.text)
                      ? stateController.text
                      : null,
                  decoration: const InputDecoration(
                    labelText: 'State',
                    filled: true,
                    fillColor: Colors.white,
                    border: OutlineInputBorder(borderSide: BorderSide.none),
                  ),
                  items: [
                    for (final state in usStates)
                      DropdownMenuItem(value: state, child: Text(state)),
                  ],
                  onChanged: (value) => stateController.text = value ?? '',
                )
              : CheckoutField(
                  controller: stateController,
                  label: 'State / Region',
                ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: country,
            decoration: const InputDecoration(
              labelText: 'Country',
              filled: true,
              fillColor: Colors.white,
              border: OutlineInputBorder(borderSide: BorderSide.none),
            ),
            items: [
              for (final code in countryOptions)
                DropdownMenuItem(value: code, child: Text(countryName(code))),
            ],
            onChanged: (value) {
              if (value == null) return;
              setState(() {
                country = value;
                if (country != 'US') {
                  stateController.clear();
                }
              });
            },
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: locating ? null : _useCurrentLocation,
                  icon: locating
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.my_location),
                  label: const Text('Use GPS'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton(
                  onPressed: _save,
                  child: const Text('Save Location'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

void showAuthSheet(BuildContext context, {required bool isRegister}) {
  var registerMode = isRegister;
  showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (_) => StatefulBuilder(
      builder: (sheetContext, setSheetState) {
        return Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(sheetContext).viewInsets.bottom,
          ),
          child: Container(
            padding: const EdgeInsets.all(18),
            decoration: const BoxDecoration(
              color: pageBg,
              borderRadius: BorderRadius.vertical(top: Radius.circular(34)),
            ),
            child: AuthPanel(
              isRegister: registerMode,
              onToggle: () => setSheetState(() {
                registerMode = !registerMode;
              }),
            ),
          ),
        );
      },
    ),
  );
}

void showPromoSheet(BuildContext context, double total) {
  showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    builder: (_) => Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: Container(
        padding: const EdgeInsets.fromLTRB(24, 20, 24, 28),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(34)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Align(
              alignment: Alignment.centerRight,
              child: IconButton(
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close),
              ),
            ),
            Container(
              width: 78,
              height: 78,
              decoration: const BoxDecoration(
                color: orange,
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.local_offer,
                color: Colors.white,
                size: 38,
              ),
            ),
            const SizedBox(height: 14),
            const Text(
              'Promo Code',
              style: TextStyle(fontSize: 26, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 18),
            TextField(
              textAlign: TextAlign.center,
              decoration: InputDecoration(
                hintText: 'SELLERAFRICA10',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(28),
                ),
              ),
            ),
            const SizedBox(height: 12),
            Text(
              'Your total bill is ${money.format(total)}',
              style: const TextStyle(color: Color(0xFF7B877F)),
            ),
            const SizedBox(height: 18),
            SizedBox(
              width: double.infinity,
              height: 56,
              child: FilledButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Apply'),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

void showFilters(BuildContext context) {
  showModalBottomSheet(
    context: context,
    builder: (_) => ListView(
      padding: const EdgeInsets.all(22),
      children: const [
        Text(
          'Filters',
          style: TextStyle(fontSize: 26, fontWeight: FontWeight.w900),
        ),
        SizedBox(height: 12),
        ListTile(
          leading: Icon(Icons.category_outlined),
          title: Text('Category / Subcategory'),
        ),
        ListTile(leading: Icon(Icons.attach_money), title: Text('Price')),
        ListTile(leading: Icon(Icons.star_border), title: Text('Rating')),
        ListTile(
          leading: Icon(Icons.verified_outlined),
          title: Text('Verified seller'),
        ),
        ListTile(
          leading: Icon(Icons.location_on_outlined),
          title: Text('Location'),
        ),
        ListTile(
          leading: Icon(Icons.local_shipping_outlined),
          title: Text('Free delivery / delivery speed'),
        ),
        ListTile(
          leading: Icon(Icons.inventory_2_outlined),
          title: Text('In stock / availability'),
        ),
      ],
    ),
  );
}

String checkoutLabel(String key) => switch (key) {
  'first_name' => 'First name',
  'last_name' => 'Last name',
  'email' => 'Email',
  'phone' => 'Phone',
  'address_line1' => 'Street address',
  'address_line2' => 'Apartment, suite, or landmark',
  'city' => 'City',
  'state' => 'State',
  'postcode' => 'Postcode',
  _ => key,
};

IconData categoryIcon(String name) {
  final lower = name.toLowerCase();
  if (lower.contains('phone') || lower.contains('electronics')) {
    return Icons.phone_iphone;
  }
  if (lower.contains('fashion') || lower.contains('cloth')) {
    return Icons.checkroom;
  }
  if (lower.contains('beauty') || lower.contains('skin')) return Icons.spa;
  if (lower.contains('home') || lower.contains('kitchen')) return Icons.kitchen;
  if (lower.contains('grocery') || lower.contains('food')) {
    return Icons.local_grocery_store;
  }
  if (lower.contains('agric') || lower.contains('farm')) {
    return Icons.agriculture;
  }
  if (lower.contains('baby')) return Icons.child_care;
  if (lower.contains('health')) return Icons.health_and_safety;
  if (lower.contains('sport')) return Icons.sports_soccer;
  if (lower.contains('book')) return Icons.menu_book;
  return Icons.category;
}
