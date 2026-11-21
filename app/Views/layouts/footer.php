        </div>
        <footer class="page-footer">
            <span>© <?= date('Y') ?> Personal Calendar</span>
            <span>Lịch cá nhân &amp; quản lý công việc</span>
        </footer>
    </main>
</div>
<?php require_once __DIR__ . '/../partials/event_modals.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales/vi.global.min.js"></script>
<?php
$jsToastVer = @file_exists('public/assets/js/toast.js') ? filemtime('public/assets/js/toast.js') : time();
$jsAppVer = @file_exists('public/assets/js/app.js') ? filemtime('public/assets/js/app.js') : time();
?>
<script src="public/assets/js/toast.js?v=<?= $jsToastVer ?>"></script>
<script src="public/assets/js/app.js?v=<?= $jsAppVer ?>"></script>
<?php
$flashToast = Auth::getFlash('toast');
$flashSuccess = Auth::getFlash('success');
$flashError = Auth::getFlash('error');
if ($flashToast):
?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.Toast) {
            window.Toast.show(<?= json_encode($flashToast, JSON_UNESCAPED_UNICODE) ?>);
        }
    });
</script>
<?php elseif ($flashSuccess): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.Toast) {
            window.Toast.success(<?= json_encode($flashSuccess, JSON_UNESCAPED_UNICODE) ?>);
        }
    });
</script>
<?php elseif ($flashError): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.Toast) {
            window.Toast.error(<?= json_encode($flashError, JSON_UNESCAPED_UNICODE) ?>);
        }
    });
</script>
<?php endif; ?>
</body>
</html>
