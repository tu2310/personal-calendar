<?php
$habits = $data['habits'] ?? [];
$summary = $data['summary'] ?? [
    'total_habits'           => 0,
    'done_today'             => 0,
    'today_rate'             => 0,
    'longest_current_streak' => 0,
    'best_streak_ever'       => 0,
    'weekly_avg_rate'        => 0
];
$daysHeader = $data['daysHeader'] ?? [];
$habitWeekly = $data['habitWeekly'] ?? ['labels' => [], 'dates' => [], 'counts' => [], 'total_habits' => 0];
$eventStats = $data['eventStats'] ?? ['today_count' => 0, 'upcoming_count' => 0, 'pending_count' => 0, 'completed_count' => 0];
$weeklyChart = $data['weeklyChart'] ?? ['labels' => [], 'completed' => [], 'pending' => [], 'total' => 0];
$taskStats = $data['taskStats'] ?? ['total' => 0, 'completed' => 0, 'pending' => 0, 'overdue' => 0, 'progress_percent' => 0];

$eventCompleted = (int)($eventStats['completed_count'] ?? 0);
$eventPending = (int)($eventStats['pending_count'] ?? 0);
$eventTotal = $eventCompleted + $eventPending;
$eventRate = $eventTotal > 0 ? (int)round(($eventCompleted / $eventTotal) * 100) : 0;

$csrfToken = $data['csrf_token'] ?? Auth::csrfToken();
$presetColors = ['#c2410c', '#15803d', '#2563eb', '#b45309', '#0f766e', '#6d28d9'];
?>
<section class="welcome-row">
    <div>
        <div class="eyebrow">Theo dõi &amp; Báo cáo</div>
        <h1>Thói quen &amp; Thống kê</h1>
        <p class="page-subtitle">Đánh dấu chuỗi thói quen lặp lại và phân tích hiệu suất lịch trình, công việc</p>
    </div>
    <button type="button" class="btn btn-primary-soft" id="btnOpenHabitModal">
        <i class="fa-solid fa-plus"></i><span>Thêm thói quen</span>
    </button>
</section>

<input type="hidden" id="habitCsrfToken" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

<!-- Band 1: 4 Unified KPI Cards -->
<section class="stats-grid">
    <article class="stat-card">
        <div class="stat-top">
            <span class="stat-label-top">Thói quen hôm nay</span>
            <span class="stat-icon"><i class="fa-solid fa-check"></i></span>
        </div>
        <div class="stat-value" id="habitStatToday"><?= (int)$summary['done_today'] ?>/<?= (int)$summary['total_habits'] ?></div>
        <div class="stat-change green" id="habitStatRate"><?= (int)$summary['today_rate'] ?>% hôm nay</div>
    </article>

    <article class="stat-card">
        <div class="stat-top">
            <span class="stat-label-top">Chuỗi thói quen</span>
            <span class="stat-icon"><i class="fa-solid fa-bolt"></i></span>
        </div>
        <div class="stat-value" id="habitStatCurrentStreak"><?= (int)$summary['longest_current_streak'] ?> <span class="habit-unit">ngày</span></div>
        <div class="stat-change" id="habitStatBestStreakText">Kỷ lục: <?= (int)$summary['best_streak_ever'] ?> ngày</div>
    </article>

    <article class="stat-card">
        <div class="stat-top">
            <span class="stat-label-top">Tiến độ công việc</span>
            <span class="stat-icon"><i class="fa-solid fa-list-check"></i></span>
        </div>
        <div class="stat-value"><?= (int)$taskStats['completed'] ?>/<?= (int)$taskStats['total'] ?></div>
        <div class="stat-change <?= ((int)$taskStats['overdue'] > 0) ? 'orange' : '' ?>">
            <?= (int)$taskStats['progress_percent'] ?>% hoàn thành<?php if ((int)$taskStats['overdue'] > 0): ?> · <?= (int)$taskStats['overdue'] ?> quá hạn<?php endif; ?>
        </div>
    </article>

    <article class="stat-card">
        <div class="stat-top">
            <span class="stat-label-top">Hiệu suất lịch trình</span>
            <span class="stat-icon"><i class="fa-solid fa-calendar-check"></i></span>
        </div>
        <div class="stat-value"><?= $eventRate ?>%</div>
        <div class="stat-change"><?= $eventCompleted ?> đã xong · <?= $eventPending ?> chờ xử lý</div>
    </article>
</section>

<!-- Band 2: Bảng đánh dấu Thói quen 7 ngày -->
<section class="panel p-0 overflow-hidden mb-4">
    <div class="habit-panel-bar">
        <div>
            <h2>Chuỗi thói quen 7 ngày gần nhất</h2>
            <p>Chạm vào từng ô ngày để đánh dấu hoặc bỏ chọn</p>
        </div>
        <span class="select-chip" id="habitStatWeeklyAvgBadge"><?= (int)$summary['weekly_avg_rate'] ?>% đạt mục tiêu tuần</span>
    </div>

    <?php if (empty($habits)): ?>
        <div class="empty-calendar">
            <div class="empty-icon"><i class="fa-solid fa-repeat"></i></div>
            <h2>Chưa có thói quen nào</h2>
            <p>Tạo thói quen hằng ngày hoặc hằng tuần như đọc sách, tập thể dục hay uống nước để theo dõi chuỗi ngày liên tiếp.</p>
            <button type="button" class="btn btn-primary-soft" id="btnOpenHabitModalEmpty">
                <i class="fa-solid fa-plus"></i><span>Thêm thói quen đầu tiên</span>
            </button>
        </div>
    <?php else: ?>
        <div class="habit-matrix-wrap">
            <div class="habit-matrix-header">
                <div class="habit-col-name">Thói quen</div>
                <div class="habit-col-days">
                    <?php foreach ($daysHeader as $day): ?>
                        <div class="habit-day-head <?= $day['is_today'] ? 'is-today' : '' ?>">
                            <span class="habit-weekday"><?= htmlspecialchars($day['weekday'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="habit-daynum"><?= htmlspecialchars($day['day_num'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="habit-col-streak">Chuỗi</div>
                <div class="habit-col-actions">Thao tác</div>
            </div>

            <div class="habit-list" id="habitList">
                <?php foreach ($habits as $h):
                    $hid = (int)$h['id'];
                    $color = htmlspecialchars($h['color'] ?? '#c2410c', ENT_QUOTES, 'UTF-8');
                    $name = htmlspecialchars($h['name'] ?? '', ENT_QUOTES, 'UTF-8');
                    $desc = htmlspecialchars($h['description'] ?? '', ENT_QUOTES, 'UTF-8');
                    $freq = ($h['frequency'] ?? 'daily') === 'weekly' ? 'weekly' : 'daily';
                    $target = (int)($h['target_per_week'] ?? 7);
                    $freqLabel = $freq === 'daily' ? 'Hằng ngày' : "{$target} buổi/tuần";
                    $streak = (int)($h['current_streak'] ?? 0);
                    $weekDone = (int)($h['week_done'] ?? 0);
                ?>
                <div class="habit-row"
                     data-habit-id="<?= $hid ?>"
                     data-name="<?= $name ?>"
                     data-description="<?= $desc ?>"
                     data-frequency="<?= $freq ?>"
                     data-target="<?= $target ?>"
                     data-color="<?= $color ?>">
                    <div class="habit-info">
                        <span class="habit-dot" style="background-color: <?= $color ?>;"></span>
                        <div class="habit-copy">
                            <strong><?= $name ?></strong>
                            <?php if ($desc !== ''): ?>
                                <small class="habit-desc"><?= $desc ?></small>
                            <?php endif; ?>
                            <div class="habit-meta">
                                <span class="habit-freq-pill"><?= htmlspecialchars($freqLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="habit-week-progress" id="habitWeek_<?= $hid ?>"><?= $weekDone ?>/<?= $target ?> tuần</span>
                            </div>
                        </div>
                    </div>

                    <div class="habit-days-grid">
                        <?php foreach ($h['recent_days'] as $day):
                            $checked = !empty($day['checked']);
                            $dStr = htmlspecialchars($day['date'], ENT_QUOTES, 'UTF-8');
                            $dLabel = htmlspecialchars($day['weekday'] . ' ' . $day['day_num'], ENT_QUOTES, 'UTF-8');
                        ?>
                        <button type="button"
                                class="habit-day-btn <?= $checked ? 'is-checked' : '' ?> <?= $day['is_today'] ? 'is-today' : '' ?>"
                                data-habit-id="<?= $hid ?>"
                                data-date="<?= $dStr ?>"
                                style="--habit-color: <?= $color ?>;"
                                aria-pressed="<?= $checked ? 'true' : 'false' ?>"
                                aria-label="<?= $name ?> — <?= $dLabel ?>"
                                title="<?= $dLabel ?>">
                            <i class="fa-solid fa-check"></i>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="habit-streak-cell">
                        <span class="habit-streak-badge <?= $streak > 0 ? 'is-active' : '' ?>" id="habitStreak_<?= $hid ?>">
                            <?= $streak ?> ngày
                        </span>
                    </div>

                    <div class="habit-actions-cell">
                        <button type="button" class="icon-btn btn-habit-edit" data-id="<?= $hid ?>" aria-label="Sửa <?= $name ?>" title="Sửa thói quen">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button type="button" class="icon-btn btn-habit-delete" data-id="<?= $hid ?>" aria-label="Xóa <?= $name ?>" title="Xóa thói quen">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- Band 3: Khối Thống kê trực quan -->
<section class="dashboard-grid">
    <div class="panel d-flex flex-column justify-content-between">
        <div>
            <div class="panel-heading">
                <div>
                    <h2>Hiệu suất lịch trình &amp; công việc tuần này</h2>
                    <p>Số lượng sự kiện hoàn thành so với chưa hoàn thành</p>
                </div>
                <span class="select-chip">7 ngày qua</span>
            </div>
            <div class="chart-wrap" style="height: 220px;">
                <canvas id="weeklyChart"
                        data-labels='<?= json_encode($weeklyChart['labels'] ?? []) ?>'
                        data-completed='<?= json_encode($weeklyChart['completed'] ?? []) ?>'
                        data-pending='<?= json_encode($weeklyChart['pending'] ?? []) ?>'></canvas>
            </div>
            <div class="chart-legend">
                <span><i class="legend-dot completed"></i> Hoàn thành</span>
                <span><i class="legend-dot pending"></i> Chưa hoàn thành</span>
            </div>
        </div>

        <div class="stat-chart-strip">
            <div>
                <small>Tổng lịch trình</small>
                <strong><?= $eventTotal ?></strong>
            </div>
            <div>
                <small>Công việc hoàn thành</small>
                <strong><?= (int)$taskStats['completed'] ?>/<?= (int)$taskStats['total'] ?></strong>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-heading">
            <div>
                <h2>Phân tích duy trì thói quen</h2>
                <p>Nhịp đánh dấu 7 ngày qua và tiến độ từng thói quen</p>
            </div>
            <span class="select-chip" id="statHabitTotalBadge"><?= (int)$summary['total_habits'] ?> thói quen</span>
        </div>

        <div class="stat-habit-bars" id="statHabitBars">
            <?php
            $labels = $habitWeekly['labels'] ?? [];
            $dates = $habitWeekly['dates'] ?? [];
            $counts = $habitWeekly['counts'] ?? [];
            $maxHabits = max(1, (int)($habitWeekly['total_habits'] ?? 1));
            $todayDate = date('Y-m-d');
            foreach ($labels as $idx => $lbl):
                $cnt = (int)($counts[$idx] ?? 0);
                $dStr = htmlspecialchars($dates[$idx] ?? '', ENT_QUOTES, 'UTF-8');
                $isToday = (($dates[$idx] ?? '') === $todayDate);
                $pct = min(100, (int)round(($cnt / $maxHabits) * 100));
            ?>
            <div class="stat-habit-bar-col <?= $isToday ? 'is-today' : '' ?>" data-bar-date="<?= $dStr ?>">
                <span class="stat-habit-bar-val" data-bar-val-date="<?= $dStr ?>"><?= $cnt ?></span>
                <div class="stat-habit-bar-col-track" title="<?= htmlspecialchars($lbl . ' (' . $cnt . '/' . $maxHabits . ')', ENT_QUOTES, 'UTF-8') ?>">
                    <div class="stat-habit-bar-col-fill" data-bar-fill-date="<?= $dStr ?>" style="height: <?= max(6, $pct) ?>%; opacity: <?= $cnt > 0 ? '1' : '0.2' ?>;"></div>
                </div>
                <span class="stat-habit-bar-lbl"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="stat-habit-divider"></div>

        <?php if (empty($habits)): ?>
            <p class="text-muted mb-0" style="font-size: 0.8125rem;">Chưa có dữ liệu thói quen để phân tích.</p>
        <?php else: ?>
            <div class="stat-habit-list" id="statHabitList">
                <?php
                $rankedHabits = $habits;
                usort($rankedHabits, fn($a, $b) => ($b['week_rate'] ?? 0) <=> ($a['week_rate'] ?? 0));
                foreach ($rankedHabits as $h):
                    $hid = (int)$h['id'];
                    $hColor = htmlspecialchars($h['color'] ?? '#c2410c', ENT_QUOTES, 'UTF-8');
                    $hName = htmlspecialchars($h['name'] ?? '', ENT_QUOTES, 'UTF-8');
                    $hTarget = (int)($h['target_per_week'] ?? 7);
                    $hWeekDone = (int)($h['week_done'] ?? 0);
                    $hWeekRate = (int)($h['week_rate'] ?? 0);
                    $hStreak = (int)($h['current_streak'] ?? 0);
                ?>
                <div class="stat-habit-item" data-stat-habit-id="<?= $hid ?>">
                    <div class="stat-habit-head">
                        <div class="stat-habit-title">
                            <span class="habit-dot" style="background-color: <?= $hColor ?>;"></span>
                            <strong><?= $hName ?></strong>
                        </div>
                        <div class="stat-habit-nums">
                            <span class="stat-habit-ratio"><?= $hWeekDone ?>/<?= $hTarget ?></span>
                            <span class="stat-habit-streak <?= $hStreak > 0 ? 'is-active' : '' ?>"><?= $hStreak ?> ngày</span>
                        </div>
                    </div>
                    <div class="stat-habit-bar-track">
                        <div class="stat-habit-bar-fill" style="width: <?= $hWeekRate ?>%; background-color: <?= $hColor ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Modal Thêm / Sửa Thói quen -->
<div class="modal fade" id="habitModal" tabindex="-1" aria-labelledby="habitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-6 fw-semibold" id="habitModalLabel">Thêm thói quen</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="habitForm">
                <div class="modal-body">
                    <input type="hidden" id="habitId" name="id" value="">
                    <input type="hidden" id="habitModalCsrf" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label for="habitName" class="form-label">Tên thói quen <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="habitName" name="name" placeholder="Ví dụ: Đọc sách 20 phút" required maxlength="120">
                    </div>

                    <div class="mb-3">
                        <label for="habitDesc" class="form-label">Ghi chú ngắn</label>
                        <input type="text" class="form-control" id="habitDesc" name="description" placeholder="Thời điểm hoặc mục tiêu cụ thể" maxlength="255">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label for="habitFrequency" class="form-label">Tần suất</label>
                            <select class="form-select" id="habitFrequency" name="frequency">
                                <option value="daily">Hằng ngày</option>
                                <option value="weekly">Hằng tuần</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="habitTarget" class="form-label">Mục tiêu mỗi tuần</label>
                            <select class="form-select" id="habitTarget" name="target_per_week" disabled>
                                <?php for ($i = 1; $i <= 7; $i++): ?>
                                    <option value="<?= $i ?>" <?= $i === 7 ? 'selected' : '' ?>><?= $i ?> ngày/tuần</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-1">
                        <label for="habitColor" class="form-label d-block">Màu đánh dấu</label>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php foreach ($presetColors as $sw): ?>
                                <button type="button" class="habit-color-swatch" data-color="<?= $sw ?>" style="background-color: <?= $sw ?>;" aria-label="Chọn màu <?= $sw ?>"></button>
                            <?php endforeach; ?>
                            <input type="color" class="form-control form-control-color p-1" id="habitColor" name="color" value="#c2410c" title="Chọn màu tùy chỉnh" style="width: 2.5rem; height: 2rem;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary-soft" id="btnSaveHabit">Lưu thói quen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrf = document.getElementById('habitCsrfToken')?.value || '';
    const modalEl = document.getElementById('habitModal');
    const modal = (modalEl && window.bootstrap) ? new bootstrap.Modal(modalEl) : null;
    const form = document.getElementById('habitForm');
    const idInput = document.getElementById('habitId');
    const nameInput = document.getElementById('habitName');
    const descInput = document.getElementById('habitDesc');
    const freqSelect = document.getElementById('habitFrequency');
    const targetSelect = document.getElementById('habitTarget');
    const colorInput = document.getElementById('habitColor');
    const modalTitle = document.getElementById('habitModalLabel');
    const saveBtn = document.getElementById('btnSaveHabit');

    function syncFrequencyTarget() {
        if (freqSelect.value === 'daily') {
            targetSelect.value = '7';
            targetSelect.disabled = true;
        } else {
            if (targetSelect.value === '7') {
                targetSelect.value = '5';
            }
            targetSelect.disabled = false;
        }
    }

    function syncSwatches(hex) {
        document.querySelectorAll('.habit-color-swatch').forEach(btn => {
            btn.classList.toggle('is-selected', (btn.dataset.color || '').toLowerCase() === (hex || '').toLowerCase());
        });
    }

    function openCreateModal() {
        form.reset();
        idInput.value = '';
        freqSelect.value = 'daily';
        targetSelect.value = '7';
        colorInput.value = '#c2410c';
        syncFrequencyTarget();
        syncSwatches('#c2410c');
        modalTitle.textContent = 'Thêm thói quen';
        modal?.show();
        setTimeout(() => nameInput?.focus(), 180);
    }

    document.getElementById('btnOpenHabitModal')?.addEventListener('click', openCreateModal);
    document.getElementById('btnOpenHabitModalEmpty')?.addEventListener('click', openCreateModal);
    freqSelect?.addEventListener('change', syncFrequencyTarget);

    document.querySelectorAll('.habit-color-swatch').forEach(btn => {
        btn.addEventListener('click', () => {
            colorInput.value = btn.dataset.color || '#c2410c';
            syncSwatches(colorInput.value);
        });
    });
    colorInput?.addEventListener('input', () => syncSwatches(colorInput.value));

    document.querySelectorAll('.btn-habit-edit').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('.habit-row');
            if (!row) return;
            idInput.value = row.dataset.habitId || '';
            nameInput.value = row.dataset.name || '';
            descInput.value = row.dataset.description || '';
            freqSelect.value = row.dataset.frequency === 'weekly' ? 'weekly' : 'daily';
            targetSelect.value = row.dataset.target || '7';
            colorInput.value = row.dataset.color || '#c2410c';
            syncFrequencyTarget();
            syncSwatches(colorInput.value);
            modalTitle.textContent = 'Chỉnh sửa thói quen';
            modal?.show();
            setTimeout(() => nameInput?.focus(), 180);
        });
    });

    function updateSummaryAndAnalytics(summary, habitWeekly, habits) {
        if (summary) {
            const todayEl = document.getElementById('habitStatToday');
            const rateEl = document.getElementById('habitStatRate');
            const curEl = document.getElementById('habitStatCurrentStreak');
            const bestTextEl = document.getElementById('habitStatBestStreakText');
            const avgBadge = document.getElementById('habitStatWeeklyAvgBadge');
            const totalBadge = document.getElementById('statHabitTotalBadge');

            if (todayEl) todayEl.textContent = `${summary.done_today}/${summary.total_habits}`;
            if (rateEl) rateEl.textContent = `${summary.today_rate}% hôm nay`;
            if (curEl) curEl.innerHTML = `${summary.longest_current_streak} <span class="habit-unit">ngày</span>`;
            if (bestTextEl) bestTextEl.textContent = `Kỷ lục: ${summary.best_streak_ever} ngày`;
            if (avgBadge) avgBadge.textContent = `${summary.weekly_avg_rate}% đạt mục tiêu tuần`;
            if (totalBadge) totalBadge.textContent = `${summary.total_habits} thói quen`;
        }

        if (habitWeekly && Array.isArray(habitWeekly.dates)) {
            const maxH = Math.max(1, Number(habitWeekly.total_habits || 1));
            habitWeekly.dates.forEach((dStr, idx) => {
                const cnt = Number(habitWeekly.counts[idx] || 0);
                const pct = Math.min(100, Math.round((cnt / maxH) * 100));
                const valEl = document.querySelector(`[data-bar-val-date="${dStr}"]`);
                const fillEl = document.querySelector(`[data-bar-fill-date="${dStr}"]`);
                if (valEl) valEl.textContent = String(cnt);
                if (fillEl) {
                    fillEl.style.height = `${Math.max(6, pct)}%`;
                    fillEl.style.opacity = cnt > 0 ? '1' : '0.2';
                }
            });
        }

        if (Array.isArray(habits)) {
            const activeIds = new Set(habits.map(h => Number(h.id)));
            document.querySelectorAll('#statHabitList .stat-habit-item').forEach(item => {
                const hid = Number(item.dataset.statHabitId);
                if (!activeIds.has(hid)) {
                    item.remove();
                    return;
                }
                const h = habits.find(x => Number(x.id) === hid);
                if (!h) return;
                const ratioEl = item.querySelector('.stat-habit-ratio');
                const streakEl = item.querySelector('.stat-habit-streak');
                const barEl = item.querySelector('.stat-habit-bar-fill');
                const s = Number(h.current_streak || 0);
                if (ratioEl) ratioEl.textContent = `${h.week_done}/${h.target_per_week}`;
                if (streakEl) {
                    streakEl.textContent = `${s} ngày`;
                    streakEl.classList.toggle('is-active', s > 0);
                }
                if (barEl) barEl.style.width = `${Number(h.week_rate || 0)}%`;
            });
        }
    }

    document.querySelectorAll('.habit-day-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (btn.disabled) return;
            const habitId = Number(btn.dataset.habitId);
            const date = btn.dataset.date;
            const prevChecked = btn.classList.contains('is-checked');

            btn.disabled = true;
            btn.classList.toggle('is-checked', !prevChecked);
            btn.setAttribute('aria-pressed', !prevChecked ? 'true' : 'false');

            try {
                const res = await fetch('index.php?page=api-habit-toggle', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ habit_id: habitId, date, csrf_token: csrf })
                });
                const data = await res.json();
                if (!data.success) {
                    throw new Error(data.message || 'Không thể cập nhật trạng thái');
                }

                btn.classList.toggle('is-checked', !!data.checked);
                btn.setAttribute('aria-pressed', data.checked ? 'true' : 'false');

                if (data.habit) {
                    const streakEl = document.getElementById(`habitStreak_${habitId}`);
                    const weekEl = document.getElementById(`habitWeek_${habitId}`);
                    if (streakEl) {
                        const s = Number(data.habit.current_streak || 0);
                        streakEl.textContent = `${s} ngày`;
                        streakEl.classList.toggle('is-active', s > 0);
                    }
                    if (weekEl) {
                        weekEl.textContent = `${data.habit.week_done}/${data.habit.target_per_week} tuần`;
                    }
                }
                updateSummaryAndAnalytics(data.summary, data.habitWeekly, data.habits);
                window.refreshNotifications?.(data.unread_count);
            } catch (err) {
                btn.classList.toggle('is-checked', prevChecked);
                btn.setAttribute('aria-pressed', prevChecked ? 'true' : 'false');
                window.Toast?.error(err.message || 'Lỗi kết nối máy chủ');
            } finally {
                btn.disabled = false;
            }
        });
    });

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const origText = saveBtn.textContent;
        saveBtn.disabled = true;
        saveBtn.textContent = 'Đang lưu...';

        try {
            const payload = {
                id: idInput.value ? Number(idInput.value) : null,
                name: nameInput.value.trim(),
                description: descInput.value.trim(),
                frequency: freqSelect.value,
                target_per_week: freqSelect.value === 'daily' ? 7 : Number(targetSelect.value),
                color: colorInput.value,
                csrf_token: csrf
            };
            const res = await fetch('index.php?page=api-habit-save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.success) {
                throw new Error(data.message || 'Không thể lưu thói quen');
            }
            window.refreshNotifications?.(data.unread_count);
            window.Toast?.success(data.message || 'Đã lưu thói quen');
            modal?.hide();
            location.reload();
        } catch (err) {
            window.Toast?.error(err.message || 'Không thể lưu thói quen');
            saveBtn.disabled = false;
            saveBtn.textContent = origText;
        }
    });

    document.querySelectorAll('.btn-habit-delete').forEach(btn => {
        btn.addEventListener('click', async () => {
            const row = btn.closest('.habit-row');
            const habitName = row?.dataset.name || 'thói quen này';
            let confirmed = false;

            if (window.Swal) {
                const res = await Swal.fire({
                    title: 'Xóa thói quen?',
                    text: `Toàn bộ lịch sử đánh dấu của "${habitName}" sẽ bị xóa vĩnh viễn.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#71717a',
                    confirmButtonText: 'Xóa thói quen',
                    cancelButtonText: 'Hủy'
                });
                confirmed = res.isConfirmed;
            } else {
                confirmed = window.confirm(`Xóa "${habitName}"?`);
            }
            if (!confirmed) return;

            btn.disabled = true;
            try {
                const res = await fetch('index.php?page=api-habit-delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: Number(btn.dataset.id), csrf_token: csrf })
                });
                const data = await res.json();
                if (!data.success) {
                    throw new Error(data.message || 'Không thể xóa thói quen');
                }
                window.Toast?.success(data.message || 'Đã xóa thói quen');
                row?.remove();
                updateSummaryAndAnalytics(data.summary, data.habitWeekly, data.habits);
                window.refreshNotifications?.(data.unread_count);
                if (!document.querySelector('#habitList .habit-row')) {
                    location.reload();
                }
            } catch (err) {
                btn.disabled = false;
                window.Toast?.error(err.message || 'Không thể xóa thói quen');
            }
        });
    });
});
</script>
