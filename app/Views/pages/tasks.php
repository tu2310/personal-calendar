<?php
$tasks = $data['tasks'] ?? [];
$categories = $data['categories'] ?? [];
$stats = $data['stats'] ?? [
    'total'            => count($tasks),
    'completed'        => 0,
    'pending'          => 0,
    'urgent'           => 0,
    'high'             => 0,
    'overdue'          => 0,
    'progress_percent' => 0
];
$csrfToken = $data['csrf_token'] ?? '';
?>
<section class="welcome-row">
    <div>
        <div class="eyebrow">Danh sách công việc</div>
        <h1>Quản lý công việc</h1>
        <p class="page-subtitle">Theo dõi hạn chót, mức độ ưu tiên và tiến độ hoàn thành</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary-soft" id="btnOpenAddTaskModal">
            <i class="fa-solid fa-plus"></i><span>Thêm công việc</span>
        </button>
    </div>
</section>

<!-- CẢNH BÁO DEADLINE QUÁ HẠN -->
<div id="taskOverdueBanner" class="alert alert-danger d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 border-danger-subtle" style="<?= empty($stats['overdue']) ? 'display: none !important;' : '' ?>">
    <div class="d-flex align-items-center gap-3">
        <div class="rounded-2 p-2 bg-danger text-white d-flex align-items-center justify-content-center" style="width: 2.25rem; height: 2.25rem;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div>
            <strong class="text-danger d-block">Có <span id="taskOverdueBannerNum"><?= $stats['overdue'] ?? 0 ?></span> công việc đã quá hạn</strong>
            <small class="text-muted">Các công việc này đã quá hạn hoàn thành, cần ưu tiên xử lý trước</small>
        </div>
    </div>
    <button type="button" class="btn btn-sm btn-danger px-3" onclick="document.querySelector('.task-status-tab[data-status=\'overdue\']')?.click()">
        Xem việc quá hạn
    </button>
</div>

<!-- THẺ TIẾN ĐỘ VÀ THỐNG KÊ CÔNG VIỆC -->
<div class="panel mb-4 p-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-5 border-end-lg">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-medium" style="font-size: 0.875rem;">Tiến độ hoàn thành</span>
                <span class="fw-medium" style="font-family: var(--font-mono); font-size: 1rem; color: var(--primary);" id="taskStatPercentBadge"><?= $stats['progress_percent'] ?>%</span>
            </div>
            <div class="progress" style="height: 8px; border-radius: 4px; background: var(--surface-2);">
                <div class="progress-bar" 
                     id="taskStatProgressBar"
                     role="progressbar" 
                     style="width: <?= $stats['progress_percent'] ?>%; background: var(--primary);" 
                     aria-valuenow="<?= $stats['progress_percent'] ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100"></div>
            </div>
            <div class="d-flex justify-content-between mt-2 text-muted" style="font-size: 0.75rem; font-variant-numeric: tabular-nums;">
                <span>Đã làm: <b class="text-success" id="taskStatCompletedCount"><?= $stats['completed'] ?></b></span>
                <span>Còn lại: <b class="text-warning" id="taskStatPendingCount"><?= $stats['pending'] ?></b></span>
                <span>Tổng: <b id="taskStatTotalCount"><?= $stats['total'] ?></b></span>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="row g-2 text-center">
                <div class="col-3">
                    <div class="p-2 rounded border" style="background: var(--surface-2);">
                        <div class="fs-4 fw-medium text-success" style="font-family: var(--font-mono);" id="statCompletedNum"><?= $stats['completed'] ?></div>
                        <small class="text-muted" style="font-size: 0.75rem;">Hoàn thành</small>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 rounded border" style="background: var(--surface-2);">
                        <div class="fs-4 fw-medium text-warning" style="font-family: var(--font-mono);" id="statPendingNum"><?= $stats['pending'] ?></div>
                        <small class="text-muted" style="font-size: 0.75rem;">Chờ xử lý</small>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 rounded border" style="background: var(--surface-2);">
                        <div class="fs-4 fw-medium text-danger" style="font-family: var(--font-mono);" id="statOverdueNum"><?= $stats['overdue'] ?></div>
                        <small class="text-muted" style="font-size: 0.75rem;">Quá hạn</small>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 rounded border" style="background: var(--surface-2);">
                        <div class="fs-4 fw-medium text-danger" style="font-family: var(--font-mono);" id="statUrgentNum"><?= $stats['urgent'] ?></div>
                        <small class="text-muted" style="font-size: 0.75rem;">Khẩn cấp</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- THANH CÔNG CỤ TÌM KIẾM, LỌC VÀ SẮP XẾP -->
<div class="panel mb-3 p-3">
    <div class="row g-2 align-items-center">
        <!-- Tìm kiếm theo tên -->
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-transparent border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                <input type="text" class="form-control border-start-0" id="taskSearchInput" placeholder="Tìm công việc theo tên" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
        </div>

        <!-- Lọc theo mức độ ưu tiên -->
        <div class="col-sm-6 col-md-2">
            <select class="form-select form-select-sm" id="taskPriorityFilter">
                <option value="all">Mọi mức ưu tiên</option>
                <option value="urgent" <?= ($_GET['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Khẩn cấp</option>
                <option value="high" <?= ($_GET['priority'] ?? '') === 'high' ? 'selected' : '' ?>>Cao</option>
                <option value="medium" <?= ($_GET['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Trung bình</option>
                <option value="low" <?= ($_GET['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Thấp</option>
            </select>
        </div>

        <!-- Lọc theo danh mục -->
        <div class="col-sm-6 col-md-3">
            <select class="form-select form-select-sm" id="taskCategoryFilter">
                <option value="">-- Mọi danh mục --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($_GET['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Sắp xếp -->
        <div class="col-md-3">
            <select class="form-select form-select-sm" id="taskSortSelect">
                <option value="due_asc" <?= ($_GET['sort'] ?? '') === 'due_asc' ? 'selected' : '' ?>>⏳ Hạn hoàn thành: Gần nhất</option>
                <option value="due_desc" <?= ($_GET['sort'] ?? '') === 'due_desc' ? 'selected' : '' ?>>⌛ Hạn hoàn thành: Xa nhất</option>
                <option value="priority_desc" <?= ($_GET['sort'] ?? '') === 'priority_desc' ? 'selected' : '' ?>>⚡ Ưu tiên: Cao đến Thấp</option>
                <option value="created_desc" <?= ($_GET['sort'] ?? 'created_desc') === 'created_desc' ? 'selected' : '' ?>>📅 Ngày tạo: Mới nhất</option>
                <option value="created_asc" <?= ($_GET['sort'] ?? '') === 'created_asc' ? 'selected' : '' ?>>📅 Ngày tạo: Cũ nhất</option>
            </select>
        </div>
    </div>

    <!-- Filter Tabs trạng thái -->
    <div class="d-flex flex-wrap gap-2 mt-3 pt-2 border-top" id="taskStatusFilters">
        <button type="button" class="btn btn-sm btn-light active task-status-tab" data-status="all" style="border-radius: 8px; font-weight: 600;">
            Tất cả <span class="badge bg-secondary-subtle text-dark ms-1" id="tabCountAll"><?= $stats['total'] ?></span>
        </button>
        <button type="button" class="btn btn-sm btn-light task-status-tab" data-status="pending" style="border-radius: 8px; font-weight: 600;">
            Chưa hoàn thành <span class="badge bg-warning-subtle text-warning ms-1" id="tabCountPending"><?= $stats['pending'] ?></span>
        </button>
        <button type="button" class="btn btn-sm btn-light task-status-tab" data-status="completed" style="border-radius: 8px; font-weight: 600;">
            Đã hoàn thành <span class="badge bg-success-subtle text-success ms-1" id="tabCountCompleted"><?= $stats['completed'] ?></span>
        </button>
        <button type="button" class="btn btn-sm btn-light task-status-tab" data-status="overdue" style="border-radius: 8px; font-weight: 600;">
            Quá hạn <span class="badge bg-danger-subtle text-danger ms-1" id="tabCountOverdue"><?= $stats['overdue'] ?></span>
        </button>
    </div>
</div>

<!-- DANH SÁCH CÔNG VIỆC -->
<div class="panel p-0 overflow-hidden position-relative">
    <div id="taskLoadingOverlay" class="task-loading-overlay" style="display: none;">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div class="task-list px-3 py-2" id="taskListContainer">
        <?php if (empty($tasks)): ?>
            <div class="empty-calendar py-5 text-center">
                <div class="empty-icon"><i class="fa-solid fa-list-check"></i></div>
                <h2>Chưa có công việc nào</h2>
                <p>Hãy bắt đầu bằng cách thêm công việc mới hoặc thử thay đổi bộ lọc tìm kiếm.</p>
                <button class="btn btn-primary-soft" onclick="document.getElementById('btnOpenAddTaskModal').click()">
                    <i class="fa-solid fa-plus me-2"></i>Tạo công việc đầu tiên
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($tasks as $task): 
                $isDone = ($task['status'] === 'completed' || $task['is_completed'] == 1);
                $isOverdue = (!empty($task['due_date']) && !$isDone && $task['due_date'] < date('Y-m-d'));
                $p = $task['priority'] ?? 'medium';
                
                $pClass = 'medium';
                $pText = 'Trung bình';
                if ($p === 'urgent') {
                    $pClass = 'urgent';
                    $pText = 'Khẩn cấp';
                } elseif ($p === 'high') {
                    $pClass = 'high';
                    $pText = 'Cao';
                } elseif ($p === 'low') {
                    $pClass = 'low';
                    $pText = 'Thấp';
                }
            ?>
            <div class="task-row align-items-center justify-content-between py-3 px-2 border-bottom <?= $isDone ? 'is-done' : '' ?> <?= $isOverdue ? 'is-overdue' : '' ?>" 
                 data-id="<?= $task['id'] ?>" 
                 data-status="<?= $isDone ? 'completed' : 'pending' ?>" 
                 data-priority="<?= $p ?>"
                 data-category-id="<?= $task['category_id'] ?? '' ?>"
                 data-due-date="<?= $task['due_date'] ?? '' ?>"
                 data-created-at="<?= $task['created_at'] ?>">
                
                <label class="d-flex align-items-start gap-3 m-0 flex-grow-1 cursor-pointer">
                    <input type="checkbox" class="task-check mt-1" data-id="<?= $task['id'] ?>" <?= $isDone ? 'checked' : '' ?>>
                    <span class="custom-check"><i class="fa-solid fa-check"></i></span>
                    <div class="task-copy">
                        <strong class="fs-6 d-block"><?= htmlspecialchars($task['title']) ?></strong>
                        
                        <?php if (!empty($task['description'])): ?>
                            <p class="task-desc-text text-muted mb-1" style="font-size: 12px;"><?= htmlspecialchars($task['description']) ?></p>
                        <?php endif; ?>

                        <div class="task-meta d-flex flex-wrap align-items-center gap-2 mt-1">
                            <!-- Hạn hoàn thành & Quá hạn -->
                            <?php if (!empty($task['due_date'])): 
                                $dueTs = strtotime($task['due_date']);
                                $todayTs = strtotime(date('Y-m-d'));
                                $daysDiff = (int)round(($dueTs - $todayTs) / 86400);
                            ?>
                                <span class="badge <?= $isOverdue ? 'bg-danger text-white' : 'bg-light text-muted border' ?> task-due-badge" style="font-size: 11px;">
                                    <i class="fa-regular fa-clock me-1"></i>Hạn: <?= date('d/m/Y', $dueTs) ?>
                                    <?php if ($isOverdue): ?>
                                        (Quá hạn <?= abs($daysDiff) ?> ngày)
                                    <?php elseif ($daysDiff === 0 && !$isDone): ?>
                                        (Hôm nay)
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>

                            <!-- Danh mục -->
                            <?php if (!empty($task['category_name'])): ?>
                                <span class="badge" style="background-color: <?= $task['category_color'] ?: '#6658e8' ?>20; color: <?= $task['category_color'] ?: '#6658e8' ?>; font-size: 11px;">
                                    <i class="fa-solid <?= !empty($task['category_icon']) ? htmlspecialchars($task['category_icon']) : 'fa-folder' ?> me-1"></i>
                                    <?= htmlspecialchars($task['category_name']) ?>
                                </span>
                            <?php endif; ?>

                            <!-- Ngày tạo -->
                            <small class="text-muted" style="font-size: 10.5px;">
                                <i class="fa-regular fa-calendar-plus me-1"></i>Tạo: <?= date('d/m/Y', strtotime($task['created_at'])) ?>
                            </small>
                        </div>
                    </div>
                </label>

                <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-3">
                    <span class="priority <?= $pClass ?>">
                        <?php if ($p === 'urgent'): ?><i class="fa-solid fa-bolt me-1"></i><?php endif; ?>
                        <?= $pText ?>
                    </span>
                    <button class="btn btn-sm btn-light btn-task-edit-v2" data-id="<?= $task['id'] ?>" title="Chỉnh sửa công việc">
                        <i class="fa-solid fa-pen-to-square text-primary"></i>
                    </button>
                    <button class="btn btn-sm btn-light btn-task-delete-v2 text-danger" data-id="<?= $task['id'] ?>" title="Xóa công việc">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL THÊM / CHỈNH SỬA CÔNG VIỆC -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="taskModalLabel">
                    <i class="fa-solid fa-list-check me-2 text-primary"></i><span>Thêm công việc mới</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="taskForm">
                <div class="modal-body p-4">
                    <input type="hidden" id="taskModalId" name="id" value="">
                    <input type="hidden" id="taskModalCsrf" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="mb-3">
                        <label for="taskModalTitle" class="form-label fw-bold">Tên công việc <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="taskModalTitle" name="title" placeholder="VD: Soạn thảo báo cáo tiến độ tuần, Thiết kế giao diện Dashboard..." required maxlength="255">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="taskModalCategory" class="form-label fw-bold">Danh mục</label>
                            <select class="form-select" id="taskModalCategory" name="category_id">
                                <option value="">-- Chưa phân loại --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="taskModalDueDate" class="form-label fw-bold">Hạn hoàn thành (Deadline)</label>
                            <input type="date" class="form-control" id="taskModalDueDate" name="due_date">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="taskModalPriority" class="form-label fw-bold">Mức độ ưu tiên</label>
                            <select class="form-select" id="taskModalPriority" name="priority">
                                <option value="low">🟢 Thấp</option>
                                <option value="medium" selected>🟡 Trung bình</option>
                                <option value="high">🔺 Cao</option>
                                <option value="urgent">🔥 Khẩn cấp</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="taskModalStatus" class="form-label fw-bold">Trạng thái công việc</label>
                            <select class="form-select" id="taskModalStatus" name="status">
                                <option value="pending" selected>Chưa hoàn thành</option>
                                <option value="completed">Đã hoàn thành</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label for="taskModalDescription" class="form-label fw-bold">Ghi chú / Mô tả chi tiết</label>
                        <textarea class="form-control" id="taskModalDescription" name="description" rows="3" placeholder="Các bước thực hiện, liên kết tài liệu hoặc lưu ý cần nhớ..."></textarea>
                    </div>
                </div>

                <div class="modal-footer justify-content-between p-3">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnDeleteTaskModal" style="display: none;">
                            <i class="fa-solid fa-trash-can me-1"></i> Xóa công việc
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-primary-soft btn-sm" id="btnSaveTaskModal">
                            <i class="fa-solid fa-check me-1"></i> Lưu công việc
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
