<section class="welcome-row">
    <div>
        <div class="eyebrow"><span class="eyebrow-dot"></span><?= (new IntlDateFormatter('vi_VN', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'EEEE, dd/MM/yyyy'))->format(new DateTime()) ?></div>
        <h1>Tổng quan hôm nay</h1>
        <p class="page-subtitle">Lịch trình, đầu việc ưu tiên và nhịp làm việc trong ngày</p>
    </div>
    <a class="btn btn-primary-soft" href="index.php?page=calendar&action=new"><i class="fa-solid fa-plus"></i><span>Thêm lịch mới</span></a>
</section>

<?php if (!empty($data['taskStats']['overdue']) && $data['taskStats']['overdue'] > 0): ?>
<div class="alert alert-warning d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 border-warning-subtle" style="background: var(--warning-soft);">
    <div class="d-flex align-items-center gap-3">
        <div class="rounded-2 d-flex align-items-center justify-content-center" style="width: 2.25rem; height: 2.25rem; background: var(--warning); color: #fff;">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div>
            <strong class="d-block" style="color: var(--text);">Có <?= $data['taskStats']['overdue'] ?> công việc đã quá hạn</strong>
            <small class="text-muted">Ưu tiên xử lý các nhiệm vụ tồn đọng trước khi sang lịch mới</small>
        </div>
    </div>
    <a href="index.php?page=tasks&status=overdue" class="btn btn-sm btn-light px-3">
        Xem việc quá hạn
    </a>
</div>
<?php endif; ?>

<section class="stats-grid">
    <?php 
    $dashCardKeys = ['today', 'upcoming', 'pending', 'completed'];
    foreach ($data['stats'] as $idx => $stat): 
        $k = $dashCardKeys[$idx] ?? ('stat_' . $idx);
    ?>
    <article class="stat-card stat-card-<?= $k ?>">
        <div class="stat-top">
            <span class="stat-label-top"><?= $stat['label'] ?></span>
            <span class="stat-icon <?= $stat['tone'] ?>"><i class="fa-solid <?= $stat['icon'] ?>"></i></span>
        </div>
        <div class="stat-value" id="dashCard_<?= $k ?>_val"><?= $stat['value'] ?></div>
        <div class="stat-change <?= $stat['tone'] ?>" id="dashCard_<?= $k ?>_change"><?= $stat['change'] ?></div>
    </article>
    <?php endforeach; ?>
</section>

<section class="dashboard-grid">
    <article class="panel agenda-panel">
        <div class="panel-heading">
            <div><h2>Lịch trình trong ngày</h2><p>Các hoạt động được lên kế hoạch hôm nay</p></div>
            <a href="index.php?page=calendar" class="text-link">Xem tất cả <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="agenda-list">
            <?php if (empty($data['events'])): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fa-regular fa-calendar-check fs-3 mb-2 d-block opacity-50"></i>
                    <p class="mb-0">Hôm nay bạn chưa có lịch trình nào.</p>
                    <a href="index.php?page=calendar" class="btn btn-sm btn-primary-soft mt-3">Lên lịch ngay</a>
                </div>
            <?php else: ?>
                <?php foreach ($data['events'] as $event): ?>
                <div class="agenda-item">
                    <div class="agenda-time"><?= $event['time'] ?></div>
                    <div class="agenda-marker <?= $event['color'] ?>"></div>
                    <div class="agenda-detail">
                        <strong><?= htmlspecialchars($event['title']) ?></strong>
                        <small><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($event['place']) ?></small>
                    </div>
                    <span class="event-tag <?= $event['color'] ?>"><?= htmlspecialchars($event['tag']) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </article>

    <article class="panel chart-panel">
        <div class="panel-heading">
            <div><h2>Thống kê hoạt động</h2><p>Hiệu suất sự kiện trong tuần này</p></div>
            <span class="select-chip">Tuần này</span>
        </div>
        <div class="chart-summary">
            <strong><?= $data['weekly']['total'] ?? 0 ?></strong>
            <span><i class="fa-solid fa-chart-simple"></i> Tổng số lịch trong tuần</span>
        </div>
        <div class="chart-wrap">
            <canvas id="weeklyChart" 
                    data-labels='<?= json_encode($data['weekly']['labels'] ?? []) ?>'
                    data-completed='<?= json_encode($data['weekly']['completed'] ?? []) ?>'
                    data-pending='<?= json_encode($data['weekly']['pending'] ?? []) ?>'></canvas>
        </div>
        <div class="chart-legend">
            <span><i class="legend-dot completed"></i> Hoàn thành</span>
            <span><i class="legend-dot pending"></i> Chưa hoàn thành</span>
        </div>
    </article>
</section>

<section class="dashboard-grid lower-grid">
    <article class="panel task-panel">
        <input type="hidden" id="dashboardCsrf" name="csrf_token" value="<?= htmlspecialchars($data['csrf_token'] ?? Auth::csrfToken()) ?>">
        <div class="panel-heading">
            <div><h2>Lịch trình & Việc cần làm</h2><p>Đừng để những việc quan trọng bị bỏ lỡ</p></div>
            <a href="index.php?page=tasks" class="text-link">Tất cả <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="task-list">
            <?php if (empty($data['tasks'])): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-list-check fs-3 mb-2 d-block opacity-50"></i>
                    <p class="mb-0">Chưa có công việc nào cần làm.</p>
                </div>
            <?php else: ?>
                <?php foreach ($data['tasks'] as $task): ?>
                <label class="task-row <?= $task['done'] ? 'is-done' : '' ?>" data-id="<?= $task['id'] ?>" data-is-task="<?= !empty($task['is_task']) ? '1' : '0' ?>" data-status="<?= $task['done'] ? 'completed' : 'pending' ?>">
                    <input type="checkbox" <?= $task['done'] ? 'checked' : '' ?> class="task-check" data-id="<?= $task['id'] ?>" data-is-task="<?= !empty($task['is_task']) ? '1' : '0' ?>">
                    <span class="custom-check"><i class="fa-solid fa-check"></i></span>
                    <span class="task-copy">
                        <strong><?= htmlspecialchars($task['title']) ?></strong>
                        <small><i class="fa-regular fa-clock"></i> <?= $task['time'] ?></small>
                    </span>
                    <span class="priority <?= ($task['priority_key'] ?? '') === 'urgent' ? 'urgent' : (($task['priority_key'] ?? '') === 'high' ? 'high' : (($task['priority_key'] ?? '') === 'low' ? 'low' : 'medium')) ?>">
                        <?= $task['priority'] ?>
                    </span>
                </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </article>

    <article class="focus-card">
        <div class="focus-card-top">
            <span class="focus-label">Pomodoro</span>
            <i class="fa-solid fa-stopwatch text-muted"></i>
        </div>
        <div>
            <div class="focus-timer-preview">25:00</div>
            <h2>Phiên làm việc tập trung</h2>
            <p>Bắt đầu một chu kỳ 25 phút tập trung và 5 phút nghỉ ngắn</p>
        </div>
        <a href="index.php?page=pomodoro" class="btn btn-focus">Bắt đầu phiên <i class="fa-solid fa-arrow-right"></i></a>
    </article>
</section>
