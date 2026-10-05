<?php
$brand = app_branding();
$distributionLogin = ($_GET['as'] ?? '') === 'distributor' || str_contains((string)($_SESSION['intended_url'] ?? ''), '/distributor');
$notices = consume_toasts();
foreach (($errors ?? []) as $error) $notices[] = ['type' => 'error', 'message' => $error];
$shown = [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,follow">
  <title>Sign in | <?= e($brand['name'] ?? 'Seller Africa') ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/distributor.css?v=2')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/login.css?v=4')) ?>">
</head>
<body class="login-body">
<?php require __DIR__ . '/../partials/public-account-header.php'; ?>
<main class="login-shell">
  <aside class="login-aside">
    <p class="login-eyebrow">Seller Africa</p>
    <h2>A world of possibility.<br><em>One place to belong.</em></h2>
    <p>Discover great products, support independent brands, and grow your business.</p>
    <img src="<?= e(asset('images/distribution-retail-hero.png')) ?>" alt="African food brands on a retail shelf">
    <p class="login-aside-footer">African brands. Global opportunities.</p>
  </aside>
  <section class="login-content" aria-labelledby="login-title">
    <p class="login-eyebrow"><?= !empty($pendingMfa) ? 'Secure sign in' : 'Welcome back' ?></p>
    <h1 id="login-title"><?= !empty($pendingMfa) ? 'A little extra<br><em>peace of mind.</em>' : 'Good to<br><em>see you again.</em>' ?></h1>
    <p class="login-intro"><?= !empty($pendingMfa) ? 'Enter your six-digit security code to continue.' : 'Sign in to your Seller Africa account.' ?></p>
    <?php foreach ($notices as $notice):
      $message = (string)($notice['message'] ?? '');
      if ($message === '' || isset($shown[$message])) continue;
      $shown[$message] = true;
      $isError = ($notice['type'] ?? '') === 'error';
    ?>
      <div class="login-notice <?= $isError ? 'is-error' : '' ?>" role="<?= $isError ? 'alert' : 'status' ?>"><?= e($message) ?></div>
    <?php endforeach; ?>
    <form method="post" class="login-form" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <input type="hidden" name="intent" value="<?= !empty($pendingMfa) ? 'mfa' : 'password' ?>">
      <?php if (!empty($pendingMfa)): ?>
        <div class="login-field"><label for="mfa_code">Security code</label><input id="mfa_code" name="mfa_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="one-time-code"></div>
      <?php else: ?>
        <div class="login-field"><label for="email">Email or username</label><input id="email" name="email" required autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= e(is_string($_POST['email'] ?? null) ? $_POST['email'] : '') ?>"></div>
        <div class="login-field"><label for="password">Password</label><div class="login-password"><input id="password" type="password" name="password" required autocomplete="current-password"><button type="button" data-sa-password-toggle aria-controls="password" aria-pressed="false" aria-label="Show password">Show</button></div><a class="login-forgot" href="<?= e(app_url('forgot-password')) ?>">Forgot password?</a></div>
        <?php if (!empty($captchaRequired) && !empty($captchaChallenge)): ?>
          <div class="login-field"><label for="captcha_answer">Security check: <?= e((string)$captchaChallenge['question']) ?></label><input id="captcha_answer" name="captcha_answer" inputmode="numeric" required></div>
        <?php endif; ?>
      <?php endif; ?>
      <button class="login-submit" type="submit"><?= !empty($pendingMfa) ? 'Verify code' : 'Sign in' ?></button>
    </form>
    <?php if (empty($pendingMfa)): ?>
      <p class="login-signup">New to Seller Africa? <a href="<?= e(app_url($distributionLogin ? 'distributor/register' : 'buyer/register')) ?>">Create an account</a></p>
      <div class="login-business"><span>Bring your business to Seller Africa</span><a href="<?= e(app_url('vendor/register')) ?>">Become a vendor</a><a href="<?= e(app_url('distributor/register')) ?>">Apply for distribution</a><a href="<?= e(app_url('affiliate/join')) ?>">Become an affiliate</a></div>
    <?php endif; ?>
    <p class="login-help">Need a hand? <a href="<?= e(app_url('contact')) ?>">Contact us</a></p>
  </section>
</main>
<footer class="login-footer">Seller Africa <span> / </span> African and Caribbean Marketplace</footer>
<script src="<?= e(asset('js/login.js?v=3')) ?>" defer></script>
<?php if (function_exists('app_render_chat_widget')) app_render_chat_widget(); ?>
</body>
</html>
