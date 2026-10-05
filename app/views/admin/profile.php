<?php
$adminProfile = $adminProfile ?? [];
?>

<section class="settings-shell users-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>Admin Profile</h2>
                <p>Manage your account details and password.</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="Admin profile sections">
            <a class="active" href="<?= e(app_url('dashboard/admin-profile')) ?>">Profile</a>
            <a href="<?= e(app_url('dashboard/security-settings')) ?>">Security Settings</a>
            <a href="<?= e(app_url('logout')) ?>">Sign Out</a>
        </nav>
    </aside>

    <div class="settings-content">
        <section class="panel detail-panel">
            <div class="panel-header">
                <div>
                    <h2><?= e((string)($adminProfile['display_name'] ?: $adminProfile['username'] ?: 'Admin')) ?></h2>
                    <p><?= e((string)($adminProfile['email'] ?? '')) ?></p>
                </div>
                <span class="settings-badge"><?= e((string)($adminProfile['status'] ?? 'active')) ?></span>
            </div>

            <form class="settings-form admin-detail-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="save_admin_profile">
                <input type="hidden" name="intent" value="save_profile">
                <div class="settings-grid">
                    <label class="settings-field"><span>First Name</span><input type="text" name="first_name" value="<?= e($adminProfile['first_name'] ?? '') ?>"></label>
                    <label class="settings-field"><span>Last Name</span><input type="text" name="last_name" value="<?= e($adminProfile['last_name'] ?? '') ?>"></label>
                    <label class="settings-field"><span>Display Name</span><input type="text" name="display_name" value="<?= e($adminProfile['display_name'] ?? '') ?>"></label>
                    <label class="settings-field"><span>Username</span><input type="text" name="username" value="<?= e($adminProfile['username'] ?? '') ?>"></label>
                    <label class="settings-field"><span>Phone</span><input type="text" name="phone" value="<?= e($adminProfile['phone'] ?? '') ?>"></label>
                    <label class="settings-field"><span>Email</span><input type="email" value="<?= e($adminProfile['email'] ?? '') ?>" disabled></label>
                </div>
                <div class="form-actions"><button class="btn primary" type="submit">Save Profile</button></div>
            </form>
        </section>

        <section class="panel detail-panel">
            <div class="panel-header">
                <div>
                    <h2>Password</h2>
                    <p>Change your own dashboard password.</p>
                </div>
            </div>
            <form class="settings-form admin-detail-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="save_admin_profile">
                <input type="hidden" name="intent" value="change_password">
                <div class="settings-grid">
                    <label class="settings-field"><span>Current Password</span><input type="password" name="current_password" required autocomplete="current-password"></label>
                    <label class="settings-field"><span>New Password</span><input type="password" name="new_password" required minlength="5" autocomplete="new-password"></label>
                    <label class="settings-field"><span>Confirm Password</span><input type="password" name="new_password_confirmation" required minlength="5" autocomplete="new-password"></label>
                </div>
                <div class="form-actions"><button class="btn primary" type="submit">Change Password</button></div>
            </form>
        </section>
    </div>
</section>
