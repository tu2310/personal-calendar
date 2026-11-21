<?php
$trashItems = $data['trash'] ?? [];
$csrfToken = $data['csrf_token'] ?? Auth::csrfToken();
?>
<section class="welcome-row">
    <div>
        <div class="eyebrow">Lưu trữ tạm</div>
        <h1>Thùng rác</h1>
        <p class="page-subtitle">Khôi phục công việc đã xóa hoặc dọn sạch vĩnh viễn</p>
    </div>
    <?php if (!empty($trashItems)): ?>
    <button type="button" class="btn btn-sm btn-outline-danger" id="btnEmptyTrash">
        <i class="fa-solid fa-trash-can me-1"></i>Dọn sạch thùng rác
    </button>
    <?php endif; ?>
</section>

<input type="hidden" id="trashCsrf" value="<?= htmlspecialchars($csrfToken) ?>">

<div class="panel p-0 overflow-hidden">
    <?php if (empty($trashItems)): ?>
        <div class="empty-calendar" style="min-height: 16rem;">
            <div class="empty-icon"><i class="fa-regular fa-trash-can"></i></div>
            <h2>Thùng rác đang trống</h2>
            <p>Các công việc bạn xóa sẽ nằm tạm ở đây để có thể khôi phục khi cần.</p>
            <a href="index.php?page=tasks" class="btn btn-light">Quay lại công việc</a>
        </div>
    <?php else: ?>
        <div class="task-list" id="trashList">
            <?php foreach ($trashItems as $item):
                $itemType = $item['item_type'] ?? 'task';
                $typeLabel = $itemType === 'event' ? 'Sự kiện' : 'Công việc';
            ?>
            <div class="task-row px-3" data-trash-id="<?= (int)$item['id'] ?>" data-trash-type="<?= $itemType ?>" style="cursor: default;">
                <div class="task-copy">
                    <strong><?= htmlspecialchars($item['title']) ?></strong>
                    <small>
                        <span class="event-tag me-1"><?= $typeLabel ?></span>
                        <i class="fa-regular fa-clock"></i>
                        Đã xóa: <?= !empty($item['deleted_at']) ? date('H:i d/m/Y', strtotime($item['deleted_at'])) : 'Vừa xong' ?>
                        <?php if (!empty($item['category_name'])): ?>
                            · <?= htmlspecialchars($item['category_name']) ?>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-light btn-restore-task" data-id="<?= (int)$item['id'] ?>" data-type="<?= $itemType ?>">
                        <i class="fa-solid fa-rotate-left me-1"></i>Khôi phục
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-force-delete-task" data-id="<?= (int)$item['id'] ?>" data-type="<?= $itemType ?>" title="Xóa vĩnh viễn">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrf = document.getElementById('trashCsrf')?.value || '';

    async function postTrash(page, payload) {
        const res = await fetch(`index.php?page=${page}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...payload, csrf_token: csrf })
        });
        return res.json();
    }

    document.querySelectorAll('.btn-restore-task').forEach(btn => {
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            const data = await postTrash('api-task-restore', { id: Number(btn.dataset.id), type: btn.dataset.type || 'task' });
            if (data.success) {
                window.Toast?.success(data.message || 'Đã khôi phục');
                btn.closest('.task-row')?.remove();
                if (!document.querySelector('#trashList .task-row')) location.reload();
            } else {
                btn.disabled = false;
                window.Toast?.error(data.message || 'Không thể khôi phục');
            }
        });
    });

    async function askConfirm(title, text, confirmText) {
        if (window.Swal) {
            const res = await Swal.fire({
                title,
                text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#71717a',
                confirmButtonText: confirmText,
                cancelButtonText: 'Hủy'
            });
            return res.isConfirmed;
        }
        return window.confirm(`${title}\n${text}`);
    }

    document.querySelectorAll('.btn-force-delete-task').forEach(btn => {
        btn.addEventListener('click', async () => {
            const ok = await askConfirm('Xóa vĩnh viễn?', 'Mục này sẽ bị xóa hẳn và không thể khôi phục.', 'Xóa vĩnh viễn');
            if (!ok) return;
            btn.disabled = true;
            const data = await postTrash('api-task-force-delete', { id: Number(btn.dataset.id), type: btn.dataset.type || 'task' });
            if (data.success) {
                window.Toast?.success(data.message || 'Đã xóa vĩnh viễn');
                btn.closest('.task-row')?.remove();
                if (!document.querySelector('#trashList .task-row')) location.reload();
            } else {
                btn.disabled = false;
                window.Toast?.error(data.message || 'Không thể xóa');
            }
        });
    });

    document.getElementById('btnEmptyTrash')?.addEventListener('click', async (e) => {
        const ok = await askConfirm('Dọn sạch thùng rác?', 'Toàn bộ mục trong thùng rác sẽ bị xóa vĩnh viễn.', 'Dọn sạch');
        if (!ok) return;
        e.currentTarget.disabled = true;
        const data = await postTrash('api-trash-empty', {});
        if (data.success) {
            window.Toast?.success(data.message || 'Đã dọn sạch thùng rác');
            location.reload();
        } else {
            e.currentTarget.disabled = false;
            window.Toast?.error(data.message || 'Không thể dọn thùng rác');
        }
    });
});
</script>
