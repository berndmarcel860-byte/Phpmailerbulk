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

});
