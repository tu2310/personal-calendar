<?php
$groupedDays = $data['groupedDays'] ?? [];
$totalCount = isset($data['totalCount']) ? (int)$data['totalCount'] : 0;
$csrfToken = $data['csrf_token'] ?? Auth::csrfToken();

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
<section class="upcoming-screen">
    <section class="welcome-row">
        <div>
            <div class="eyebrow">
                <span class="eyebrow-dot"></span>Lộ trình kế tiếp
                <?php if ($totalCount > 0): ?>
                    <span> · <?= $totalCount ?> mục</span>
                <?php endif; ?>
            </div>
            <h1>Sắp tới</h1>
            <p class="page-subtitle">Lộ trình các đầu việc và lịch trình trong những ngày kế tiếp</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary-soft" id="btnOpenAddModal">
                <i class="fa-solid fa-plus"></i><span>Thêm mới</span>
            </button>
        </div>
    </section>

    <input type="hidden" id="dashboardCsrf" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

    <?php if (empty($groupedDays)): ?>
        <div class="panel p-0">
            <div class="today-empty-state">
                <div class="empty-icon"><i class="fa-regular fa-calendar-plus"></i></div>
                <h2>Chưa có lịch trình sắp tới</h2>
                <p>Lên kế hoạch trước cho các ngày tiếp theo để phân bổ thời gian chủ động và nhẹ nhàng hơn.</p>
                <button type="button" class="btn btn-primary-soft js-open-event-modal">
                    <i class="fa-solid fa-plus"></i><span>Thêm lịch sắp tới</span>
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="upcoming-days-stack">
            <?php foreach ($groupedDays as $dateKey => $dayGroup):
                $dayDate = (string)($dayGroup['date'] ?? $dateKey);
                $dayLabel = (string)($dayGroup['label'] ?? $dayDate);
                $items = $dayGroup['items'] ?? [];
                if (empty($items)) continue;
            ?>
            <section class="panel upcoming-day-card p-0">
                <header class="upcoming-day-header">
                    <div class="upcoming-day-heading">
                        <span class="upcoming-day-dot"></span>
                        <h2><?= htmlspecialchars($dayLabel) ?></h2>
                        <span class="upcoming-day-count"><?= count($items) ?></span>
                    </div>
                    <button type="button"
                            class="btn-day-quick-add js-open-event-modal"
                            data-date="<?= htmlspecialchars($dayDate) ?>"
                            title="Thêm vào ngày này"
                            aria-label="Thêm vào <?= htmlspecialchars($dayLabel) ?>">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </header>

                <div class="today-timeline-list">
                    <?php foreach ($items as $item):
                        $id = (int)($item['id'] ?? 0);
                        $isTask = !empty($item['is_task']);
                        $isDone = !empty($item['done']) || (($item['status'] ?? '') === 'completed');
                        $isMultiday = !empty($item['is_multiday']);
                        $timeLabel = trim((string)($item['time_label'] ?? ($item['time'] ?? 'Chưa gắn giờ')));
                        $repeatType = (string)($item['repeat_type'] ?? 'none');
                        $priority = (string)($item['priority'] ?? 'medium');
                        $catName = trim((string)($item['category_name'] ?? ''));
                        $catColor = trim((string)($item['category_color'] ?? '')) ?: '#c2410c';
                        $location = trim((string)($item['location'] ?? ''));
                    ?>
                    <div class="task-row today-row upcoming-row <?= $isDone ? 'is-done' : '' ?> <?= $isMultiday ? 'is-multiday' : '' ?>"
                         data-id="<?= $id ?>"
                         data-is-task="<?= $isTask ? '1' : '0' ?>"
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
            </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../partials/event_modals.php'; ?>
