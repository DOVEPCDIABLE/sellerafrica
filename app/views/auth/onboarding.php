<?php require VIEW_PATH . '/partials/header.php'; ?>
<?php $brand = app_branding(); ?>

<main class="auth-shell">
    <section class="auth-panel" aria-labelledby="onboarding-title">
        <div class="auth-form-wrap">
            <div class="auth-brand-row">
                <img src="<?= e($brand['logo']) ?>" alt="<?= e($brand['name']) ?>">
                <a href="<?= e(app_url('login.php')) ?>">Sign In</a>
            </div>

            <p class="eyebrow">First Run Setup</p>
            <h1 id="onboarding-title">Create your super admin</h1>
            <p class="help-text">Set up the owner account for dashboard access, audit trails, vendors, payments, shipping, affiliates, and worker monitoring.</p>

            <form method="post" class="form-grid" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

                <div class="field">
                    <label for="first_name">First name</label>
                    <input id="first_name" name="first_name" required value="<?= e($_POST['first_name'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="last_name">Last name</label>
                    <input id="last_name" name="last_name" required value="<?= e($_POST['last_name'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="username">Username</label>
                    <input id="username" name="username" required value="<?= e($_POST['username'] ?? '') ?>">
                </div>

                <div class="field full">
                    <label for="phone">Phone</label>
                    <input id="phone" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required minlength="5">
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required minlength="5">
                </div>

                <div class="field full">
                    <div class="button-row">
                        <button class="btn" type="submit">Create Account</button>
                        <span class="auth-note">Audited setup</span>
                    </div>
                    <p class="auth-footnote">Password is hashed before storage. Creation and onboarding completion are written to audit logs.</p>
                </div>
            </form>
        </div>

        <div class="auth-aside">
            <div class="auth-art">
                <img class="auth-art-main" src="<?= e(app_url('template/assets/img/hero/hero-1-2.png')) ?>" alt="">
                <img class="auth-art-shape shape-one" src="<?= e(app_url('template/assets/img/shape/shape-1-2.png')) ?>" alt="">
                <img class="auth-art-shape shape-two" src="<?= e(app_url('template/assets/img/hero/shape-2-2.png')) ?>" alt="">
            </div>
            <div class="auth-proof">
                <strong><?= e($brand['name']) ?> Admin</strong>
                <span>Minimal setup for a clean migration workspace.</span>
            </div>
        </div>
    </section>
</main>

<?php require VIEW_PATH . '/partials/footer.php'; ?>
