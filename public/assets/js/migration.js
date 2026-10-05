(function ($) {
    const config = window.SellerAfricaMigration || {};
    const root = document.querySelector('[data-migration-console]');
    if (!root || !$) return;

    let runId = config.runId || null;
    let running = false;
    let lastStepKey = null;
    let lastFailureMessage = null;
    const chunkSize = 1024 * 1024 * 2;

    const toast = (type, message) => {
        if (window.SellerAfricaToast) {
            window.SellerAfricaToast({ type, message });
        }
    };

    const post = (url, data, options) => $.ajax({
        url,
        method: 'POST',
        data,
        processData: options?.processData ?? true,
        contentType: options?.contentType ?? 'application/x-www-form-urlencoded; charset=UTF-8',
        dataType: 'json'
    });

    function statusText(value) {
        return String(value || 'pending').replace(/_/g, ' ');
    }

    function renderSummary(summary) {
        if (!summary) return;

        const run = summary.run || {};
        const steps = summary.steps || [];
        const logs = summary.logs || [];
        runId = run.id || runId;

        $('[data-run-label]').text(run.source_label || 'Migration run');
        $('[data-run-status]').text(statusText(run.status));
        $('[data-progress-copy]').text(run.status ? `Run ${statusText(run.status)} · batch size ${run.batch_size || 100}` : 'No run created yet.');
        $('[data-start-run], [data-pause-run]').prop('disabled', !runId);

        const currentStep = steps.find((step) => step.step_key === run.current_step);
        if (currentStep && currentStep.step_key !== lastStepKey) {
            if (lastStepKey !== null) {
                toast('info', `Now migrating: ${currentStep.label}`);
            }
            lastStepKey = currentStep.step_key;
        }

        const latestError = logs.find((log) => log.level === 'error');
        if (run.status === 'failed' && latestError && latestError.message !== lastFailureMessage) {
            lastFailureMessage = latestError.message;
            toast('error', latestError.message);
        }

        const finished = steps.filter((step) => ['completed', 'skipped'].includes(step.status)).length;
        const percent = steps.length ? Math.round((finished / steps.length) * 100) : 0;
        $('[data-progress-bar]').css('width', `${percent}%`);

        const stepMarkup = steps.length ? steps.map((step) => `
            <div class="step-row" data-step-status="${step.status}">
                <strong>${escapeHtml(step.label)}</strong>
                <span>${escapeHtml(statusText(step.status))} · ${Number(step.processed_count || 0).toLocaleString()} rows</span>
            </div>
        `).join('') : '<div class="step-row"><strong>Waiting for setup</strong><span>Create a run to load migration steps.</span></div>';
        $('[data-step-list]').html(stepMarkup);

        const logMarkup = logs.length ? logs.map((log) => `
            <div class="log-row log-${log.level}">
                <strong>${escapeHtml(log.level)}</strong>
                <span>${escapeHtml(log.message)}</span>
                <small>${escapeHtml(log.created_at)}</small>
            </div>
        `).join('') : '<div class="log-row"><strong>Ready</strong><span>No migration events yet.</span></div>';
        $('[data-migration-log]').html(logMarkup);

        if (['completed', 'failed', 'paused'].includes(run.status)) {
            running = false;
        }
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[char]);
    }

    function applySourceMode() {
        const mode = $('input[name="source_type"]:checked').val();
        $('[data-source-mode]').addClass('is-hidden');
        $(`[data-source-mode="${mode}"]`).removeClass('is-hidden');
        if (mode === 'sql_dump') {
            const manualPath = $('#manual_source_path').val();
            if (manualPath) $('#migration-source-path').val(manualPath);
        }
    }

    function processLoop() {
        if (!running || !runId) return;

        post(config.endpoints.step, { csrf_token: config.csrf, run_id: runId })
            .done((response) => {
                if (!response.ok) {
                    running = false;
                    toast('error', response.message || 'Migration batch failed.');
                    return;
                }

                renderSummary(response.summary);
                const status = response.summary?.run?.status;
                if (running && !['completed', 'failed', 'paused'].includes(status)) {
                    window.setTimeout(processLoop, 650);
                } else if (status === 'completed') {
                    toast('success', 'Migration completed.');
                } else if (status === 'failed') {
                    running = false;
                }
            })
            .fail((xhr) => {
                running = false;
                toast('error', xhr.responseJSON?.message || 'Migration request failed.');
            });
    }

    $('#migration-create-form').on('submit', function (event) {
        event.preventDefault();
        applySourceMode();

        post(config.endpoints.create, $(this).serialize())
            .done((response) => {
                if (!response.ok) {
                    toast('error', response.message || 'Unable to create migration run.');
                    return;
                }
                renderSummary(response.summary);
                toast('success', response.message || 'Migration run created.');
            })
            .fail((xhr) => toast('error', xhr.responseJSON?.message || 'Unable to create migration run.'));
    });

    $('[data-start-run]').on('click', function () {
        if (!runId) return;
        running = true;
        toast('info', 'Migration batches started.');
        processLoop();
    });

    $('[data-pause-run]').on('click', function () {
        if (!runId) return;
        running = false;
        post(config.endpoints.pause, { csrf_token: config.csrf, run_id: runId })
            .done((response) => {
                renderSummary(response.summary);
                toast('warning', response.message || 'Migration paused.');
            })
            .fail((xhr) => toast('error', xhr.responseJSON?.message || 'Unable to pause migration.'));
    });

    $('[data-upload-dump]').on('click', function () {
        const file = document.getElementById('sql-dump-file')?.files?.[0];
        if (!file) {
            toast('warning', 'Choose a SQL dump first.');
            return;
        }

        const totalChunks = Math.ceil(file.size / chunkSize);
        const uploadId = `dump-${Date.now()}-${Math.random().toString(16).slice(2)}`;
        let index = 0;

        const uploadNext = () => {
            const start = index * chunkSize;
            const end = Math.min(file.size, start + chunkSize);
            const data = new FormData();
            data.append('csrf_token', config.csrf);
            data.append('upload_id', uploadId);
            data.append('chunk_index', String(index));
            data.append('total_chunks', String(totalChunks));
            data.append('original_name', file.name);
            data.append('chunk', file.slice(start, end), file.name);

            $('[data-upload-status]').text(`Uploading chunk ${index + 1} of ${totalChunks}`);

            post(config.endpoints.upload, data, { processData: false, contentType: false })
                .done((response) => {
                    if (!response.ok) {
                        toast('error', response.message || 'Upload failed.');
                        return;
                    }

                    if (response.complete) {
                        $('#migration-source-path').val(response.source_path || '');
                        $('[data-upload-status]').text(response.source_path || 'Upload complete.');
                        toast('success', 'SQL dump uploaded.');
                        return;
                    }

                    index += 1;
                    uploadNext();
                })
                .fail((xhr) => toast('error', xhr.responseJSON?.message || 'Upload failed.'));
        };

        uploadNext();
    });

    $('input[name="source_type"]').on('change', applySourceMode);
    $('#manual_source_path').on('input', function () {
        $('#migration-source-path').val(this.value);
    });

    const requestedSteps = new Set(['files', 'shipping', 'content_posts', 'product_shipping_details']);
    $('[data-select-requested-steps]').on('click', function () {
        $('[data-migration-step-choice]').each(function () {
            this.checked = requestedSteps.has(this.value);
        });
        toast('info', 'Selected shipping, blogs, blog images, and product shipping details.');
    });

    $('[data-select-all-steps]').on('click', function () {
        $('[data-migration-step-choice]').prop('checked', true);
    });

    $('[data-clear-steps]').on('click', function () {
        $('[data-migration-step-choice]').prop('checked', false);
    });

    applySourceMode();
})(window.jQuery);
