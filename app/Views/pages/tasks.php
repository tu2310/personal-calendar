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
$csrfToken = $data['csrf_token'] ?? Auth::csrfToken();
$todayYmd = date('Y-m-d');

$repeatLabels = [
    'daily'   => 'Hằng ngày',
    'weekly'  => 'Hằng tuần',
    'monthly' => 'Hằng tháng',
];
?>
<section class="welcome-row">
    <div>
        <div class="eyebrow"><span class="eyebrow-dot"></span>Từ hôm nay trở đi</div>
        <h1>Tasks</h1>
        <p class="page-subtitle">Các đầu việc từ hôm nay trở đi và việc quá hạn chưa hoàn thành</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary-soft" id="btnOpenAddTaskModal">
            <i class="fa-solid fa-plus"></i><span>Thêm đầu việc</span>
        </button>
    </div>
</section>

<!-- CẢNH BÁO VIỆC QUÁ HẠN -->
<div id="taskOverdueBanner" class="task-overdue-strip mb-3" style="<?= empty($stats['overdue']) ? 'display: none !important;' : '' ?>">
    <div class="d-flex align-items-center gap-2 min-w-0">
        <span class="today-overdue-dot"></span>
        <span class="task-overdue-strip-text">
            Đang có <strong id="taskOverdueBannerNum"><?= (int)($stats['overdue'] ?? 0) ?></strong> đầu việc quá hạn chưa hoàn thành được ghim ở đầu danh sách
        </span>
    </div>
    <button type="button" class="btn btn-sm btn-light flex-shrink-0" onclick="document.querySelector('.task-status-tab[data-status=\'overdue\']')?.click()">
        Lọc quá hạn
    </button>
</div>

<!-- THANH TIẾN ĐỘ & BỘ LỌC GỌN -->
<div class="panel mb-3 p-3">
    <div class="task-compact-summary mb-3 pb-3 border-bottom">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div class="today-progress-copy">
                Hoàn thành <strong id="taskStatCompletedCount"><?= (int)$stats['completed'] ?></strong>/<span id="taskStatTotalCount"><?= (int)$stats['total'] ?></span> đầu việc
                <span class="text-muted ms-1">(<span id="taskStatPercentBadge"><?= (int)$stats['progress_percent'] ?>%</span>)</span>
            </div>
            <div class="task-summary-pills">
                <span>Chờ xử lý: <b id="statPendingNum"><?= (int)$stats['pending'] ?></b><span class="d-none" id="taskStatPendingCount"><?= (int)$stats['pending'] ?></span></span>
                <span>Quá hạn: <b class="text-danger" id="statOverdueNum"><?= (int)$stats['overdue'] ?></b></span>
                <span>Khẩn cấp: <b id="statUrgentNum"><?= (int)$stats['urgent'] ?></b></span>
                <span class="d-none" id="statCompletedNum"><?= (int)$stats['completed'] ?></span>
            </div>
        </div>
        <div class="today-progress-track">
            <div class="today-progress-fill"
                 id="taskStatProgressBar"
                 role="progressbar"
                 style="width: <?= (int)$stats['progress_percent'] ?>%;"
                 aria-valuenow="<?= (int)$stats['progress_percent'] ?>"
                 aria-valuemin="0"
                 aria-valuemax="100"></div>
        </div>
    </div>

    <div class="row g-2 align-items-center">
        <div class="col-12 col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-transparent border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                <input type="text" class="form-control border-start-0" id="taskSearchInput" placeholder="Tìm theo tên đầu việc" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" aria-label="Tìm theo tên đầu việc">
            </div>
        </div>

        <div class="col-6 col-md-2">
            <select class="form-select form-select-sm" id="taskPriorityFilter" aria-label="Lọc mức ưu tiên">
                <option value="all">Mọi ưu tiên</option>
                <option value="urgent" <?= ($_GET['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Khẩn cấp</option>
                <option value="high" <?= ($_GET['priority'] ?? '') === 'high' ? 'selected' : '' ?>>Cao</option>
                <option value="medium" <?= ($_GET['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Trung bình</option>
                <option value="low" <?= ($_GET['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Thấp</option>
            </select>
        </div>

        <div class="col-6 col-md-3">
            <select class="form-select form-select-sm" id="taskCategoryFilter" aria-label="Lọc danh mục">
                <option value="">Mọi danh mục</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= ($_GET['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-md-3">
            <?php $sortCurrent = $_GET['sort'] ?? 'due_asc'; ?>
            <select class="form-select form-select-sm" id="taskSortSelect" aria-label="Sắp xếp công việc">
                <option value="due_asc" <?= $sortCurrent === 'due_asc' ? 'selected' : '' ?>>Ngày thực hiện: Gần nhất</option>
                <option value="due_desc" <?= $sortCurrent === 'due_desc' ? 'selected' : '' ?>>Ngày thực hiện: Xa nhất</option>
                <option value="priority_desc" <?= $sortCurrent === 'priority_desc' ? 'selected' : '' ?>>Ưu tiên: Cao đến Thấp</option>
                <option value="created_desc" <?= $sortCurrent === 'created_desc' ? 'selected' : '' ?>>Ngày tạo: Mới nhất</option>
                <option value="created_asc" <?= $sortCurrent === 'created_asc' ? 'selected' : '' ?>>Ngày tạo: Cũ nhất</option>
            </select>
        </div>
    </div>

    <!-- Filter Tabs trạng thái -->
    <?php $initStatusTab = $_GET['status'] ?? 'all'; ?>
    <div class="task-filter-tabs mt-3 pt-2 border-top" id="taskStatusFilters">
        <button type="button" class="btn btn-sm btn-light task-status-tab <?= $initStatusTab === 'all' ? 'active' : '' ?>" data-status="all">
            Tất cả <span class="badge bg-secondary-subtle text-dark ms-1" id="tabCountAll"><?= (int)$stats['total'] ?></span>
        </button>
        <button type="button" class="btn btn-sm btn-light task-status-tab <?= $initStatusTab === 'pending' ? 'active' : '' ?>" data-status="pending">
            Chưa xong <span class="badge bg-warning-subtle text-warning ms-1" id="tabCountPending"><?= (int)$stats['pending'] ?></span>
        </button>
        <button type="button" class="btn btn-sm btn-light task-status-tab <?= $initStatusTab === 'completed' ? 'active' : '' ?>" data-status="completed">
            Đã xong <span class="badge bg-success-subtle text-success ms-1" id="tabCountCompleted"><?= (int)$stats['completed'] ?></span>
        </button>
        <button type="button" class="btn btn-sm btn-light task-status-tab <?= $initStatusTab === 'overdue' ? 'active' : '' ?>" data-status="overdue">
            Quá hạn <span class="badge bg-danger-subtle text-danger ms-1" id="tabCountOverdue"><?= (int)$stats['overdue'] ?></span>
        </button>
    </div>
</div>

<!-- DANH SÁCH ĐẦU VIỆC -->
<div class="panel p-0 overflow-hidden position-relative">
    <div id="taskLoadingOverlay" class="task-loading-overlay" style="display: none;">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div class="task-list" id="taskListContainer" data-initial-status="<?= htmlspecialchars($initStatusTab) ?>">
        <?php if (empty($tasks)): ?>
            <div class="empty-calendar py-5 text-center">
                <div class="empty-icon"><i class="fa-regular fa-circle-check"></i></div>
                <h2>Chưa có đầu việc nào</h2>
                <p>Thêm đầu việc cho hôm nay hoặc những ngày kế tiếp để theo dõi tập trung tại một nơi.</p>
                <button type="button" class="btn btn-primary-soft" onclick="document.getElementById('btnOpenAddTaskModal').click()">
                    <i class="fa-solid fa-plus me-1"></i>Tạo đầu việc mới
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($tasks as $task):
                $dueDate = trim((string)($task['due_date'] ?? ''));
                $endDate = trim((string)($task['end_date'] ?? ''));
                $effectiveDueDate = $endDate !== '' ? $endDate : $dueDate;
                $hasMultiDay = ($endDate !== '' && $dueDate !== '' && $endDate > $dueDate);

                $isDone = (($task['status'] ?? '') === 'completed' || !empty($task['is_completed']));
                $rawOverdue = ($effectiveDueDate !== '' && $effectiveDueDate < $todayYmd);
                $isOverdue = ($rawOverdue && !$isDone);
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

                $startTimeRaw = trim((string)($task['start_time'] ?? ''));
                $endTimeRaw = trim((string)($task['end_time'] ?? ''));
                $startHm = $startTimeRaw !== '' ? substr($startTimeRaw, 0, 5) : '';
                $endHm = $endTimeRaw !== '' ? substr($endTimeRaw, 0, 5) : '';
                $timeRangeText = '';
                if ($startHm !== '' && $endHm !== '') {
                    $timeRangeText = $startHm . ' – ' . $endHm;
                } elseif ($startHm !== '') {
                    $timeRangeText = $startHm;
                }

                $repeatType = (string)($task['repeat_type'] ?? 'none');
            ?>
            <div class="task-row tasks-page-row <?= $isDone ? 'is-done' : '' ?> <?= $isOverdue ? 'is-overdue' : '' ?> <?= $hasMultiDay ? 'is-multiday' : '' ?>"
                 data-id="<?= (int)$task['id'] ?>"
                 data-is-task="1"
                 data-is-overdue="<?= $rawOverdue ? '1' : '0' ?>"
                 data-status="<?= $isDone ? 'completed' : 'pending' ?>"
                 data-priority="<?= htmlspecialchars($p) ?>"
                 data-category-id="<?= htmlspecialchars((string)($task['category_id'] ?? '')) ?>"
                 data-due-date="<?= htmlspecialchars($dueDate) ?>"
                 data-end-date="<?= htmlspecialchars($endDate) ?>"
                 data-created-at="<?= htmlspecialchars((string)($task['created_at'] ?? '')) ?>">

                <?php if ($hasMultiDay): ?>
                    <span class="today-multiday-pin" aria-hidden="true"></span>
                <?php endif; ?>

                <label class="task-check-wrap" aria-label="Đánh dấu hoàn thành <?= htmlspecialchars($task['title']) ?>">
                    <input type="checkbox" class="task-check" data-id="<?= (int)$task['id'] ?>" data-is-task="1" <?= $isDone ? 'checked' : '' ?>>
                    <span class="custom-check"><i class="fa-solid fa-check"></i></span>
                </label>

                <div class="task-copy">
                    <div class="today-title-line">
                        <strong><?= htmlspecialchars($task['title']) ?></strong>
                        <?php if ($isOverdue): ?>
                            <span class="today-pill is-overdue task-overdue-badge">Quá hạn</span>
                        <?php endif; ?>
                        <?php if ($hasMultiDay): ?>
                            <span class="today-pill is-multiday"><i class="fa-solid fa-thumbtack"></i>Nhiều ngày</span>
                        <?php endif; ?>
                        <?php if (isset($repeatLabels[$repeatType])): ?>
                            <span class="today-pill is-repeat"><i class="fa-solid fa-repeat"></i><?= $repeatLabels[$repeatType] ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($task['description'])): ?>
                        <p class="task-desc-text text-muted mb-1"><?= htmlspecialchars($task['description']) ?></p>
                    <?php endif; ?>

                    <div class="task-meta d-flex flex-wrap align-items-center gap-2 mt-1">
                        <?php if ($timeRangeText !== ''): ?>
                            <span class="today-time-mono">
                                <i class="fa-regular fa-clock"></i><?= htmlspecialchars($timeRangeText) ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($dueDate !== ''):
                            $dueTs = strtotime($dueDate);
                            $effectiveDueTs = strtotime($effectiveDueDate);
                            $todayTs = strtotime($todayYmd);
                            $daysDiff = (int)round(($effectiveDueTs - $todayTs) / 86400);
                            $dateText = date('d/m/Y', $dueTs);
                            if ($hasMultiDay) {
                                $dateText = date('d/m', $dueTs) . ' → ' . date('d/m/Y', strtotime($endDate));
                            }
                        ?>
                            <span class="badge <?= $isOverdue ? 'bg-danger text-white' : 'bg-light text-muted border' ?> task-due-badge" style="font-size: 11px;">
                                <i class="fa-regular fa-calendar me-1"></i><?= htmlspecialchars($dateText) ?>
                                <?php if ($isOverdue && $daysDiff < 0): ?>
                                    (Quá hạn <?= abs($daysDiff) ?> ngày)
                                <?php elseif ($daysDiff === 0 && !$isDone): ?>
                                    (Hôm nay)
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($task['category_name'])):
                            $cCol = $task['category_color'] ?: '#c2410c';
                        ?>
                            <span class="today-meta-cat">
                                <span class="today-cat-dot" style="background-color: <?= htmlspecialchars($cCol) ?>;"></span>
                                <?= htmlspecialchars($task['category_name']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="task-row-actions">
                    <span class="priority <?= $pClass ?>"><?= $pText ?></span>
                    <button type="button" class="btn btn-sm btn-light btn-task-edit-v2" data-id="<?= (int)$task['id'] ?>" title="Chỉnh sửa đầu việc" aria-label="Chỉnh sửa đầu việc">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-light btn-task-delete-v2 text-danger" data-id="<?= (int)$task['id'] ?>" title="Xóa đầu việc" aria-label="Xóa đầu việc">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL THÊM / CHỈNH SỬA ĐẦU VIỆC -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="taskModalLabel">
                    <i class="fa-regular fa-circle-check me-2 text-primary-accent"></i><span>Thêm đầu việc</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="taskForm">
                <div class="modal-body p-3 p-sm-4">
                    <input type="hidden" id="taskModalId" name="id" value="">
                    <input type="hidden" id="taskModalCsrf" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="mb-3">
                        <label for="taskModalTitle" class="form-label">Tên đầu việc <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="taskModalTitle" name="title" placeholder="VD: Gửi báo cáo tuần, Họp rà soát thiết kế" required maxlength="255">
                    </div>

                    <!-- Khối Ngày thực hiện & Khung giờ gọn cho 1 ngày -->
                    <div class="schedule-compact-box mb-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-sm-5">
                                <label for="taskModalDueDate" class="form-label">Ngày thực hiện</label>
                                <input type="date" class="form-control" id="taskModalDueDate" name="due_date" value="<?= $todayYmd ?>">
                            </div>
                            <div class="col-12 col-sm-7">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="taskModalStartTime" class="form-label mb-0">Khung giờ</label>
                                    <button type="button" class="btn-inline-subtle" id="btnClearTaskTime">Chưa gắn giờ</button>
                                </div>
                                <div class="time-range-inline">
                                    <input type="time" class="form-control" id="taskModalStartTime" name="start_time" aria-label="Giờ bắt đầu">
                                    <span class="time-range-sep">–</span>
                                    <input type="time" class="form-control" id="taskModalEndTime" name="end_time" aria-label="Giờ kết thúc">
                                </div>
                            </div>
                        </div>

                        <div class="mt-2 pt-1">
                            <button type="button" class="btn-expand-multiday" id="btnToggleTaskEndDate">
                                <i class="fa-solid fa-plus me-1"></i>Thêm ngày kết thúc (việc nhiều ngày)
                            </button>
                        </div>

                        <div id="taskEndDateCollapse" class="multiday-collapse d-none mt-2 pt-2 border-top">
                            <label for="taskModalEndDate" class="form-label mb-1">Ngày kết thúc</label>
                            <input type="date" class="form-control" id="taskModalEndDate" name="end_date">
                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Việc kéo dài nhiều ngày sẽ hiển thị dạng thanh ghim gọn ở đầu ngày.</small>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="taskModalRepeat" class="form-label">Lặp lại</label>
                            <select class="form-select" id="taskModalRepeat" name="repeat_type">
                                <option value="none" selected>Không lặp</option>
                                <option value="daily">Hằng ngày</option>
                                <option value="weekly">Hằng tuần</option>
                                <option value="monthly">Hằng tháng</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="taskModalCategory" class="form-label">Danh mục</label>
                            <select class="form-select" id="taskModalCategory" name="category_id">
                                <option value="">Chưa phân loại</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="taskModalPriority" class="form-label">Mức ưu tiên</label>
                            <select class="form-select" id="taskModalPriority" name="priority">
                                <option value="low">Thấp</option>
                                <option value="medium" selected>Trung bình</option>
                                <option value="high">Cao</option>
                                <option value="urgent">Khẩn cấp</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="taskModalStatus" class="form-label">Trạng thái</label>
                            <select class="form-select" id="taskModalStatus" name="status">
                                <option value="pending" selected>Chưa hoàn thành</option>
                                <option value="completed">Đã hoàn thành</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label for="taskModalDescription" class="form-label">Ghi chú</label>
                        <textarea class="form-control" id="taskModalDescription" name="description" rows="2" placeholder="Các bước cần lưu ý hoặc liên kết tài liệu"></textarea>
                    </div>
                </div>

                <div class="modal-footer justify-content-between p-3">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnDeleteTaskModal" style="display: none;">
                            <i class="fa-solid fa-trash-can me-1"></i>Xóa
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-primary-soft btn-sm" id="btnSaveTaskModal">
                            <i class="fa-solid fa-check me-1"></i>Lưu đầu việc
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
