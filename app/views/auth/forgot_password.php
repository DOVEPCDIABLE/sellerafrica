<?php require VIEW_PATH . '/partials/header.php'; ?>
<?php $brand = app_branding(); ?>

<main class="auth-shell">
    <section class="auth-panel auth-panel-login" aria-labelledby="forgot-title">
        <div class="auth-form-wrap">
            <div class="auth-brand-row">
                <img src="<?= e($brand['logo']) ?>" alt="<?= e($brand['name']) ?>">
                <a href="<?= e(app_url('login')) ?>">Sign In</a>
            </div>

            <p class="eyebrow">Account Recovery</p>
            <h1 id="forgot-title">Reset your password</h1>
            <p class="help-text">Enter your account email and we will send a secure reset link.</p>

            <form method="post" class="form-grid" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <div class="field full">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <?php if (!empty($captchaRequired) && !empty($captchaChallenge)): ?>
                    <div class="field full">
                        <label for="captcha_answer">Security check: <?= e((string)$captchaChallenge['question']) ?></label>
                        <input id="captcha_answer" name="captcha_answer" inputmode="numeric" required>
                    </div>
                <?php endif; ?>
                <div class="field full">
                    <div class="button-row">
                        <button class="btn" type="submit">Send Reset Link</button>
                        <span class="auth-note">Secure reset</span>
                    </div>
                </div>
            </form>
        </div>

        <div class="auth-aside">
            <div class="auth-proof">
                <strong>Protected sign in</strong>
                <span>Legacy accounts must reset their password before accessing production.</span>
            </div>
        </div>
    </section>
</main>

<?php require VIEW_PATH . '/partials/footer.php'; ?>
