/* PhpMailerBulk - Admin JavaScript */

document.addEventListener('DOMContentLoaded', function () {

    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert.alert-success, .alert.alert-info').forEach(function (el) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            bsAlert.close();
        }, 5000);
    });

    // Confirm delete actions
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.dataset.confirm || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });

    // Toggle password visibility
    document.querySelectorAll('.btn-toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            if (input) {
                input.type = input.type === 'password' ? 'text' : 'password';
                btn.querySelector('i').classList.toggle('bi-eye');
                btn.querySelector('i').classList.toggle('bi-eye-slash');
            }
        });
    });

    // Campaign send progress polling
    var progressBar = document.getElementById('campaign-progress-bar');
    if (progressBar) {
        var campaignId = progressBar.dataset.campaign;
        pollCampaignProgress(campaignId);
    }

    function pollCampaignProgress(campaignId) {
        fetch('send.php?action=progress&id=' + campaignId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var bar = document.getElementById('campaign-progress-bar');
                if (!bar) return;
                var pct = data.total > 0 ? Math.round((data.sent / data.total) * 100) : 0;
                bar.style.width = pct + '%';
                bar.textContent = pct + '%';
                document.getElementById('progress-sent').textContent  = data.sent;
                document.getElementById('progress-total').textContent = data.total;
                document.getElementById('progress-failed').textContent = data.failed;
                if (data.status === 'running') {
                    setTimeout(function () { pollCampaignProgress(campaignId); }, 3000);
                } else {
                    document.getElementById('progress-status').textContent = data.status;
                    document.getElementById('btn-refresh').style.display = 'inline-block';
                }
            })
            .catch(function () {
                setTimeout(function () { pollCampaignProgress(campaignId); }, 5000);
            });
    }

    // Preview HTML template
    var previewBtn = document.getElementById('btn-preview-template');
    if (previewBtn) {
        previewBtn.addEventListener('click', function () {
            var html = document.getElementById('html_body').value;
            var win = window.open('', '_blank');
            win.document.write(html);
            win.document.close();
        });
    }

    // CSV column auto-detect feedback
    var csvInput = document.getElementById('csv_file');
    if (csvInput) {
        csvInput.addEventListener('change', function () {
            document.getElementById('csv-hint').style.display = 'block';
        });
    }

    // ── AI Template Generator ────────────────────────────────────────────────
    var btnAiGenerate = document.getElementById('btn-ai-generate');
    if (btnAiGenerate) {
        btnAiGenerate.addEventListener('click', function () {
            var language = document.getElementById('ai_language').value;
            var scenario = document.getElementById('ai_scenario').value;
            var tone     = document.getElementById('ai_tone').value;
            var company  = document.getElementById('ai_company').value;
            var website  = document.getElementById('ai_website').value;
            var extra    = document.getElementById('ai_extra').value;

            var statusEl = document.getElementById('ai-status');
            var errorEl  = document.getElementById('ai-error');
            var btnText  = document.getElementById('ai-btn-text');
            var htmlArea = document.getElementById('html_body');
            var subjectEl = document.getElementById('tpl_subject');

            // Reset UI
            errorEl.style.display  = 'none';
            statusEl.style.display = 'flex';
            btnAiGenerate.disabled  = true;
            btnText.textContent     = 'Generating…';

            var scenarioLabels = {
                initial:  'Generating initial outreach…',
                followup: 'Generating follow-up email…',
                final:    'Generating final notice…',
                success:  'Generating success notification…',
            };
            document.getElementById('ai-status-text').textContent =
                (scenarioLabels[scenario] || 'Generating template…') +
                ' This may take 10–20 seconds.';

            var body = new URLSearchParams({
                language: language,
                scenario: scenario,
                tone:     tone,
                company:  company,
                website:  website,
                extra:    extra,
            });

            fetch('ai_generate.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    body.toString(),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                statusEl.style.display = 'none';
                btnAiGenerate.disabled  = false;
                btnText.textContent     = 'Generate with AI';

                if (!data.ok) {
                    errorEl.textContent    = data.error || 'Unknown error from AI.';
                    errorEl.style.display  = 'block';
                    return;
                }

                // Inject generated HTML
                if (htmlArea && data.html) {
                    htmlArea.value = data.html;
                }

                // Inject subject if field is empty or user confirms overwrite
                if (subjectEl && data.subject) {
                    var preview = data.subject.length > 100
                        ? data.subject.substring(0, 100) + '…'
                        : data.subject;
                    if (!subjectEl.value.trim() || confirm('Replace the current subject line with the AI-generated one?\n\n"' + preview + '"')) {
                        subjectEl.value = data.subject;
                    }
                }

                // Flash success feedback
                btnText.textContent = '✓ Template Generated!';
                setTimeout(function () { btnText.textContent = 'Generate with AI'; }, 3000);
            })
            .catch(function (err) {
                statusEl.style.display = 'none';
                btnAiGenerate.disabled  = false;
                btnText.textContent     = 'Generate with AI';
                errorEl.textContent    = 'Network error: ' + err.message;
                errorEl.style.display  = 'block';
            });
        });
    }

});
