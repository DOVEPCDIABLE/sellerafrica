<?php
$summary = $migrationSummary ?? null;
$run = $summary['run'] ?? null;
$steps = $summary['steps'] ?? [];
$logs = $summary['logs'] ?? [];
?>

<section class="migration-console" data-migration-console>
    <div class="migration-hero panel">
        <div>
            <p class="eyebrow">WordPress to Seller Africa</p>
            <h2>Data Migration</h2>
            <p class="help-text">Move users, roles, vendors, products, files, shipping, orders, payments, vendor splits and affiliates in small AJAX batches. Use a recent update window for live catch-up runs.</p>
        </div>
        <div class="migration-run-card">
            <span>Latest Run</span>
            <strong data-run-label><?= e($run['source_label'] ?? 'No run yet') ?></strong>
            <small data-run-status><?= e($run['status'] ?? 'Waiting') ?></small>
        </div>
    </div>

    <div class="migration-layout">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2>Source Setup</h2>
                    <p>Use a staging database for the actual migration. Uploading/registering the dump keeps the file traceable, then import it into MySQL and run from that source database.</p>
                </div>
            </div>

            <form class="migration-form" id="migration-create-form">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="source_path" id="migration-source-path" value="">

                <div class="segmented">
                    <label><input type="radio" name="source_type" value="source_database" checked> <span>Source Database</span></label>
                    <label><input type="radio" name="source_type" value="sql_dump"> <span>SQL Dump Record</span></label>
                </div>

                <div class="form-grid migration-source-db" data-source-mode="source_database">
                    <div class="field">
                        <label for="source_host">Host</label>
                        <input id="source_host" name="source_host" value="127.0.0.1">
                    </div>
                    <div class="field">
                        <label for="source_port">Port</label>
                        <input id="source_port" name="source_port" value="3306" inputmode="numeric">
                    </div>
                    <div class="field">
                        <label for="source_database">Database</label>
                        <input id="source_database" name="source_database" placeholder="seller_africa_previous">
                    </div>
                    <div class="field">
                        <label for="source_username">Username</label>
                        <input id="source_username" name="source_username" value="root">
                    </div>
                    <div class="field">
                        <label for="source_password">Password</label>
                        <input id="source_password" name="source_password" type="password" autocomplete="off">
                    </div>
                    <div class="field">
                        <label for="table_prefix">Table Prefix</label>
                        <input id="table_prefix" name="table_prefix" value="hqgs_">
                    </div>
                    <div class="field">
                        <label for="batch_size">Batch Size</label>
                        <input id="batch_size" name="batch_size" value="100" inputmode="numeric">
                    </div>
                    <div class="field">
                        <label for="updated_since_days">Live Catch-Up Window</label>
                        <input id="updated_since_days" name="updated_since_days" value="" placeholder="21" inputmode="numeric">
                    </div>
                    <div class="field">
                        <label for="updated_since">Updated Since Date</label>
                        <input id="updated_since" name="updated_since" type="date">
                    </div>
                    <div class="field">
                        <label for="source_label">Label</label>
                        <input id="source_label" name="source_label" placeholder="Previous Seller Africa">
                    </div>
                    <div class="field span-2">
                        <span>Catch-Up Notes</span>
                        <span class="help-text">Leave both date fields blank to migrate all records for the selected steps. Use a day window only for live catch-up runs.</span>
                    </div>
                    <label class="field">
                        <span>Skip Existing Users</span>
                        <span class="checkbox-line"><input type="checkbox" name="skip_existing_users" value="1" checked> Do not overwrite users that already exist; only link them for migration.</span>
                    </label>
                    <label class="field">
                        <span>Vendor Descriptions</span>
                        <span class="checkbox-line"><input type="checkbox" name="fill_missing_vendor_descriptions" value="1" checked> Fill empty vendor descriptions from Dokan vendor biography.</span>
                    </label>
                </div>

                <div class="panel migration-step-picker">
                    <div class="panel-header">
                        <div>
                            <h2>Select What To Migrate</h2>
                            <p>Only checked items will run. For this request, shipping, blog/news posts, blog images, and product shipping details are selected.</p>
                        </div>
                        <div class="button-row">
                            <button class="btn secondary compact-btn" type="button" data-select-requested-steps>Shipping + Blogs</button>
                            <button class="btn secondary compact-btn" type="button" data-select-all-steps>Select All</button>
                            <button class="btn secondary compact-btn" type="button" data-clear-steps>Clear</button>
                        </div>
                    </div>
                    <div class="form-grid">
                        <?php
                        $migrationStepChoices = [
                            'files' => ['Media files / blog images', 'Import WordPress attachments so blog featured images can be linked.', true],
                            'shipping' => ['Shipping zones, methods and rates', 'Import all WooCommerce shipping details.', true],
                            'content_posts' => ['WordPress Blog & News', 'Import WordPress posts with details and featured images.', true],
                            'product_shipping_details' => ['Product shipping details', 'Update product weight, dimensions and shipping classes only.', true],
                            'users' => ['Users', 'Optional. Passwords are never overwritten by migration.', false],
                            'terms' => ['Categories, brands and tags', 'Optional taxonomy import.', false],
                            'vendors' => ['Vendors', 'Optional vendor profiles.', false],
                            'products' => ['Full products', 'Optional full product data import.', false],
                            'product_terms' => ['Product relationships', 'Optional categories, tags and shipping classes.', false],
                            'orders' => ['Orders', 'Optional order records.', false],
                            'order_items' => ['Order items', 'Optional order line items.', false],
                            'vendor_splits' => ['Vendor splits', 'Optional Dokan vendor order splits.', false],
                            'affiliates' => ['Affiliates', 'Optional affiliate accounts.', false],
                            'affiliate_activity' => ['Affiliate activity', 'Optional affiliate visits, referrals and payouts.', false],
                        ];
                        ?>
                        <?php foreach ($migrationStepChoices as $stepKey => [$label, $help, $checked]): ?>
                            <label class="field">
                                <span><?= e($label) ?></span>
                                <span class="checkbox-line"><input type="checkbox" name="selected_steps[]" value="<?= e($stepKey) ?>" data-migration-step-choice <?= $checked ? 'checked' : '' ?>> <?= e($help) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="migration-source-dump is-hidden" data-source-mode="sql_dump">
                    <div class="upload-box">
                        <input type="file" id="sql-dump-file" accept=".sql,.txt">
                        <div>
                            <strong>Upload SQL dump in chunks</strong>
                            <span data-upload-status>No dump selected.</span>
                        </div>
                        <button class="btn secondary" type="button" data-upload-dump>Upload Dump</button>
                    </div>
                    <div class="field">
                        <label for="manual_source_path">Existing Dump Path</label>
                        <input id="manual_source_path" placeholder="/Applications/XAMPP/xamppfiles/htdocs/seller_africa/sellar africa previous app.sql">
                    </div>
                </div>

                <div class="button-row">
                    <button class="btn" type="submit">Create Migration Run</button>
                    <button class="btn secondary" type="button" data-start-run <?= $run ? '' : 'disabled' ?>>Start / Resume</button>
                    <button class="btn secondary" type="button" data-pause-run <?= $run ? '' : 'disabled' ?>>Pause</button>
                </div>
            </form>
        </div>

        <aside class="panel migration-progress">
            <div class="panel-header">
                <div>
                    <h2>Progress</h2>
                    <p data-progress-copy><?= $run ? e(ucfirst((string)$run['status'])) : 'No run created yet.' ?></p>
                </div>
            </div>
            <div class="progress-track"><span data-progress-bar style="width: 0%"></span></div>
            <div class="step-list" data-step-list>
                <?php if ($steps === []): ?>
                    <div class="step-row"><strong>Waiting for setup</strong><span>Create a run to load migration steps.</span></div>
                <?php endif; ?>
                <?php foreach ($steps as $step): ?>
                    <div class="step-row" data-step-status="<?= e($step['status']) ?>">
                        <strong><?= e($step['label']) ?></strong>
                        <span><?= e($step['status']) ?> · <?= number_format((int)$step['processed_count']) ?> rows</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <h2>Migration Log</h2>
                <p>Newest batch events and skipped optional plugin tables.</p>
            </div>
        </div>
        <div class="migration-log" data-migration-log>
            <?php if ($logs === []): ?>
                <div class="log-row"><strong>Ready</strong><span>No migration events yet.</span></div>
            <?php endif; ?>
            <?php foreach ($logs as $log): ?>
                <div class="log-row log-<?= e($log['level']) ?>">
                    <strong><?= e($log['level']) ?></strong>
                    <span><?= e($log['message']) ?></span>
                    <small><?= e($log['created_at']) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        window.SellerAfricaMigration = {
            csrf: <?= json_encode(getCsrfToken()) ?>,
            runId: <?= json_encode($run ? (int)$run['id'] : null) ?>,
            endpoints: {
                create: <?= json_encode(app_url('api/migration/create.php')) ?>,
                status: <?= json_encode(app_url('api/migration/status.php')) ?>,
                step: <?= json_encode(app_url('api/migration/step.php')) ?>,
                pause: <?= json_encode(app_url('api/migration/pause.php')) ?>,
                upload: <?= json_encode(app_url('api/migration/upload.php')) ?>
            }
        };
    </script>
    <script src="<?= e(app_url('template/assets/js/jquery-min.js')) ?>"></script>
    <script src="<?= e(app_url('assets/js/migration.js')) ?>"></script>
</section>
