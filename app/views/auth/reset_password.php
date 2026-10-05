<?php require VIEW_PATH . '/partials/header.php'; ?>
<?php $brand = app_branding(); ?>

<main class="auth-shell">
    <section class="auth-panel auth-panel-login" aria-labelledby="reset-title">
        <div class="auth-form-wrap">
            <div class="auth-brand-row">
                <img src="<?= e($brand['logo']) ?>" alt="<?= e($brand['name']) ?>">
                <a href="<?= e(app_url('login')) ?>">Sign In</a>
            </div>

            <p class="eyebrow">Security Upgrade</p>
            <h1 id="reset-title">Choose a new password</h1>
            <p class="help-text">Use at least 5 characters.</p>

            <?php if ($resetUser): ?>
            <form method="post" class="form-grid" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="field full">
                    <label for="password">New password</label>
                    <input id="password" type="password" name="password" required minlength="5" autocomplete="new-password">
                </div>

                <div class="field full">
                    <label for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required minlength="5" autocomplete="new-password">
                </div>

                <div class="field full">
                    <div class="button-row">
                        <button class="btn" type="submit">Reset Password</button>
                        <span class="auth-note">1 hour link</span>
                    </div>
                </div>
            </form>
            <?php else: ?>
                <p class="auth-footnote">This link has expired. <a href="<?= e(app_url('forgot-password')) ?>">Request a new reset link</a>.</p>
            <?php endif; ?>
        </div>

        <div class="auth-aside">
            <div class="auth-proof">
                <strong>Production security</strong>
                <span>After reset, you can sign in normally with your new password.</span>
            </div>
        </div>
    </section>
</main>

<?php require VIEW_PATH . '/partials/footer.php'; ?>
