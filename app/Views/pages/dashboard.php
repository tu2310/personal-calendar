<?php
$todayItems = $data['todayItems'] ?? [];
$progress = $data['todayProgress'] ?? [];
$totalCount = isset($progress['total']) ? (int)$progress['total'] : count($todayItems);
$doneCount = isset($progress['done']) ? (int)$progress['done'] : 0;
$overdueCount = isset($progress['overdue']) ? (int)$progress['overdue'] : 0;
$percent = isset($progress['percent'])
    ? (int)$progress['percent']
    : ($totalCount > 0 ? (int)round(($doneCount / $totalCount) * 100) : 0);

$csrfToken = $data['csrf_token'] ?? Auth::csrfToken();
$tz = new DateTimeZone('Asia/Ho_Chi_Minh');
$todayDateLabel = (new IntlDateFormatter(
    'vi_VN',
    IntlDateFormatter::FULL,
    IntlDateFormatter::NONE,
    'Asia/Ho_Chi_Minh',
    IntlDateFormatter::GREGORIAN,
    'EEEE, dd/MM/yyyy'
))->format(new DateTime('now', $tz));

$repeatLabels = [
    'daily'   => 'Hằng ngày',
    'weekly'  => 'Hằng tuần',
    'monthly' => 'Hằng tháng',
];
$priorityLabels = [
    'urgent' => 'Khẩn cấp',
    'high'   => 'Cao',
    'medium' => 'Trung bình',
    'low'    => 'Thấp',
];
?>
<section class="today-screen">
    <header class="today-header">
        <div class="today-header-top">
            <div>
                <div class="eyebrow"><span class="eyebrow-dot"></span><?= htmlspecialchars($todayDateLabel) ?></div>
                <h1>Hôm nay</h1>
            </div>
            <button type="button" class="btn btn-primary-soft today-add-btn" id="btnOpenAddModal">
                <i class="fa-solid fa-plus"></i>
                <span>Thêm việc</span>
            </button>
        </div>

        <div class="today-progress-strip" id="todayProgressBarWrap" data-initial-done="<?= (int)$doneCount ?>" data-total="<?= (int)$totalCount ?>">
            <div class="today-progress-meta">
                <span class="today-progress-copy">
                    Hoàn thành <strong id="todayDoneCount"><?= $doneCount ?></strong>/<span id="todayTotalCount"><?= $totalCount ?></span> việc hôm nay
                </span>
                <?php if ($overdueCount > 0): ?>
                    <a href="index.php?page=tasks&status=overdue" class="today-overdue-chip" id="todayOverdueChip">
                        <span class="today-overdue-dot"></span><?= $overdueCount ?> quá hạn
                    </a>
                <?php endif; ?>
            </div>
            <div class="today-progress-track" role="progressbar" aria-label="Tiến độ hoàn thành hôm nay" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percent ?>">
                <div class="today-progress-fill" id="todayProgressFill" style="width: <?= max(0, min(100, $percent)) ?>%;"></div>
            </div>
        </div>
    </header>

    <div class="panel today-panel p-0">
        <input type="hidden" id="dashboardCsrf" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <?php if ($totalCount === 0 && empty($todayItems)): ?>
            <div class="today-empty-state" id="todayEmptyState">
                <div class="empty-icon"><i class="fa-regular fa-sun"></i></div>
                <h2>Chưa có việc cần làm hôm nay</h2>
                <p>Thêm các đầu việc hoặc lịch hẹn quan trọng để theo dõi theo từng khung giờ trong ngày.</p>
                <button type="button" class="btn btn-primary-soft js-open-event-modal">
                    <i class="fa-solid fa-plus"></i>
                    <span>Thêm việc hôm nay</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="today-empty-state <?= ($totalCount > 0 && empty($todayItems)) ? '' : 'd-none' ?>" id="todayAllDoneState">
            <div class="empty-icon today-all-done-icon"><i class="fa-regular fa-circle-check"></i></div>
            <h2>Đã hoàn thành hết việc hôm nay</h2>
            <p>Mọi đầu việc trong ngày đã được xử lý gọn gàng.</p>
            <button type="button" class="btn btn-primary-soft js-open-event-modal">
                <i class="fa-solid fa-plus"></i>
                <span>Thêm việc hôm nay</span>
            </button>
        </div>

        <?php if (!empty($todayItems)): ?>
            <div class="today-timeline-list" id="todayTimelineList">
                <?php $lastIdx = count($todayItems) - 1; ?>
                <?php foreach ($todayItems as $idx => $item):
                    $id = (int)($item['id'] ?? 0);
                    $isTask = !empty($item['is_task']);
                    $isDone = !empty($item['done']);
                    $isOverdue = !empty($item['is_overdue']) && !$isDone;
                    $isCurrent = !empty($item['is_current']) && !$isDone;
                    $isMultiday = !empty($item['is_multiday']);
                    $timeLabel = trim((string)($item['time_label'] ?? 'Chưa gắn giờ'));
                    $repeatType = (string)($item['repeat_type'] ?? 'none');
                    $priority = (string)($item['priority'] ?? 'medium');
                    $catName = trim((string)($item['category_name'] ?? ''));
                    $catColor = trim((string)($item['category_color'] ?? '')) ?: '#c2410c';
                    $location = trim((string)($item['location'] ?? ''));
                ?>
                <div class="task-row today-row <?= $idx === 0 ? 'is-first-visible' : '' ?> <?= $idx === $lastIdx ? 'is-last-visible' : '' ?> <?= $isDone ? 'is-done' : '' ?> <?= $isOverdue ? 'is-overdue' : '' ?> <?= $isCurrent ? 'is-current' : '' ?> <?= $isMultiday ? 'is-multiday' : '' ?>"
                     data-id="<?= $id ?>"
                     data-is-task="<?= $isTask ? '1' : '0' ?>"
                     data-is-overdue="<?= !empty($item['is_overdue']) ? '1' : '0' ?>"
                     data-status="<?= $isDone ? 'completed' : 'pending' ?>">

                    <?php if ($isMultiday): ?>
                        <span class="today-multiday-pin" aria-hidden="true"></span>
                    <?php endif; ?>

                    <label class="task-check-wrap" aria-label="Đánh dấu hoàn thành <?= htmlspecialchars($item['title'] ?? '') ?>">
                        <input type="checkbox"
                               class="task-check"
                               data-id="<?= $id ?>"
                               data-is-task="<?= $isTask ? '1' : '0' ?>"
                               <?= $isDone ? 'checked' : '' ?>>
                        <span class="custom-check"><i class="fa-solid fa-check"></i></span>
                    </label>

                    <div class="task-copy today-copy">
                        <div class="today-title-line">
                            <strong><?= htmlspecialchars($item['title'] ?? '') ?></strong>
                            <?php if ($isCurrent): ?>
                                <span class="today-pill is-current"><span class="live-dot"></span>Sắp diễn ra</span>
                            <?php endif; ?>
                            <?php if ($isOverdue): ?>
                                <span class="today-pill is-overdue">Quá hạn</span>
                            <?php endif; ?>
                            <?php if ($isMultiday): ?>
                                <span class="today-pill is-multiday"><i class="fa-solid fa-thumbtack"></i>Nhiều ngày</span>
                            <?php endif; ?>
                            <?php if (isset($repeatLabels[$repeatType])): ?>
                                <span class="today-pill is-repeat"><i class="fa-solid fa-repeat"></i><?= $repeatLabels[$repeatType] ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="today-submeta">
                            <span class="today-time-mono">
                                <i class="fa-regular fa-clock"></i><?= htmlspecialchars($timeLabel !== '' ? $timeLabel : 'Chưa gắn giờ') ?>
                            </span>
                            <?php if ($catName !== ''): ?>
                                <span class="today-meta-cat">
                                    <span class="today-cat-dot" style="background-color: <?= htmlspecialchars($catColor) ?>;"></span>
                                    <?= htmlspecialchars($catName) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($location !== ''): ?>
                                <span class="today-meta-loc">
                                    <i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($location) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (in_array($priority, ['urgent', 'high'], true) && isset($priorityLabels[$priority])): ?>
                                <span class="priority <?= htmlspecialchars($priority) ?> today-priority-tag"><?= $priorityLabels[$priority] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <a class="today-item-open <?= $isTask ? '' : 'js-open-event-detail' ?>"
                       href="<?= $isTask ? 'index.php?page=tasks&task_id=' . $id : '#event-' . $id ?>"
                       <?php if (!$isTask): ?>data-event-id="<?= $id ?>"<?php endif; ?>
                       aria-label="Xem chi tiết <?= htmlspecialchars($item['title'] ?? '') ?>"
                       title="Chi tiết">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../partials/event_modals.php'; ?>
