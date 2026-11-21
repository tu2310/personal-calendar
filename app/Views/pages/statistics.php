<?php
$stats = $data['stats'] ?? ['today_count' => 0, 'upcoming_count' => 0, 'pending_count' => 0, 'completed_count' => 0];
$total = ($stats['completed_count'] + $stats['pending_count']);
$rate = $total > 0 ? round(($stats['completed_count'] / $total) * 100) : 0;
$weekly = $data['weekly'] ?? [];
$habits = $data['habits'] ?? [];
$habitSummary = $data['habitSummary'] ?? ['total_habits' => 0, 'done_today' => 0, 'today_rate' => 0, 'longest_current_streak' => 0, 'best_streak_ever' => 0, 'weekly_avg_rate' => 0];
$habitWeekly = $data['habitWeekly'] ?? ['labels' => [], 'dates' => [], 'counts' => [], 'total_habits' => 0];
?>
<section class="welcome-row">
    <div>
        <div class="eyebrow">Báo cáo</div>
        <h1>Thống kê hoạt động</h1>
        <p class="page-subtitle">Tỷ lệ hoàn thành công việc và phân bổ lịch trình trong tuần</p>
    </div>
</section>

<div class="stats-grid">
    <article class="stat-card">
        <div class="stat-top">
            <span class="stat-icon purple"><i class="fa-solid fa-calendar-check"></i></span>
            <span class="badge bg-primary-subtle text-primary border">Tổng cộng</span>
        </div>
        <div class="stat-value"><?= $total ?></div>
        <div class="stat-label">Tổng số lịch trình</div>
        <div class="stat-change purple"><i class="fa-solid fa-chart-line"></i> Toàn bộ hệ thống</div>
    </article>

    <article class="stat-card">
        <div class="stat-top">
            <span class="stat-icon green"><i class="fa-solid fa-circle-check"></i></span>
            <span class="badge bg-success-subtle text-success border">Hoàn thành</span>
        </div>
        <div class="stat-value"><?= $stats['completed_count'] ?></div>
        <div class="stat-label">Đã hoàn thành</div>
        <div class="stat-change green"><i class="fa-solid fa-check"></i> Đạt mục tiêu</div>
    </article>

    <article class="stat-card">
        <div class="stat-top">
            <span class="stat-icon orange"><i class="fa-solid fa-clock"></i></span>
            <span class="badge bg-warning-subtle text-warning border">Chờ xử lý</span>
        </div>
        <div class="stat-value"><?= $stats['pending_count'] ?></div>
        <div class="stat-label">Chưa hoàn thành</div>
        <div class="stat-change orange"><i class="fa-solid fa-hourglass-half"></i> Cần thực hiện</div>
    </article>

    <article class="stat-card">
        <div class="stat-top">
            <span class="stat-icon blue"><i class="fa-solid fa-percent"></i></span>
            <span class="badge bg-info-subtle text-info border">Tỷ lệ</span>
        </div>
        <div class="stat-value"><?= $rate ?>%</div>
        <div class="stat-label">Tỷ lệ hoàn thành</div>
        <div class="stat-change blue"><i class="fa-solid fa-arrow-trend-up"></i> Hiệu suất làm việc</div>
    </article>
</div>

<div class="dashboard-grid">
    <div class="panel">
        <div class="panel-heading">
            <div>
                <h2>Hiệu suất tuần này</h2>
                <p>Số lượng công việc hoàn thành so với chưa hoàn thành</p>
            </div>
            <span class="select-chip">7 ngày qua</span>
        </div>
        <div class="chart-wrap" style="height: 240px;">
            <canvas id="weeklyChart" 
                    data-labels='<?= json_encode($weekly['labels'] ?? []) ?>'
                    data-completed='<?= json_encode($weekly['completed'] ?? []) ?>'
                    data-pending='<?= json_encode($weekly['pending'] ?? []) ?>'></canvas>
        </div>
    </div>

    <div class="panel d-flex flex-column justify-content-between">
        <div class="panel-heading">
            <div>
                <h2>Tỷ lệ hoàn thành mục tiêu</h2>
                <p>Mức độ hoàn thành tổng thể trên tài khoản</p>
            </div>
        </div>
        <div class="text-center py-3">
            <div class="display-3 fw-bold text-primary mb-2"><?= $rate ?>%</div>
            <p class="text-muted mb-0" style="font-size: 11.5px;">
                <?php if ($rate >= 70): ?>
                    <i class="fa-solid fa-fire text-danger me-1"></i> Hiệu suất tuyệt vời! Hãy tiếp tục duy trì phong độ này.
                <?php elseif ($rate >= 40): ?>
                    <i class="fa-solid fa-thumbs-up text-primary me-1"></i> Tiến độ ổn định. Cố gắng hoàn thành nốt các việc còn lại nhé!
                <?php else: ?>
                    <i class="fa-solid fa-seedling text-success me-1"></i> Hãy bắt đầu ngày mới bằng việc hoàn thành một công việc nhỏ.
                <?php endif; ?>
            </p>
        </div>
        <div class="progress" style="height: 10px;">
            <div class="progress-bar" style="width: <?= $rate ?>%"></div>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="panel">
        <div class="panel-heading">
            <div>
                <h2>Mức duy trì thói quen (7 ngày gần nhất)</h2>
                <p>Tỷ lệ hoàn thành mục tiêu tuần và chuỗi hiện tại của từng thói quen</p>
            </div>
            <a href="index.php?page=habits" class="text-link">Quản lý thói quen <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <?php if (empty($habits)): ?>
            <div class="empty-calendar" style="min-height: 12rem; padding: 1.5rem 1rem;">
                <div class="empty-icon"><i class="fa-solid fa-repeat"></i></div>
                <h2>Chưa có dữ liệu thói quen</h2>
                <p>Thêm thói quen hằng ngày hoặc hằng tuần để theo dõi tiến độ duy trì tại đây.</p>
                <a href="index.php?page=habits" class="btn btn-light btn-sm">Tạo thói quen</a>
            </div>
        <?php else: ?>
            <div class="stat-habit-list">
                <?php
                $rankedHabits = $habits;
                usort($rankedHabits, fn($a, $b) => ($b['week_rate'] ?? 0) <=> ($a['week_rate'] ?? 0));
                foreach ($rankedHabits as $h):
                    $hColor = htmlspecialchars($h['color'] ?? '#c2410c', ENT_QUOTES, 'UTF-8');
                    $hName = htmlspecialchars($h['name'] ?? '', ENT_QUOTES, 'UTF-8');
                    $hTarget = (int)($h['target_per_week'] ?? 7);
                    $hWeekDone = (int)($h['week_done'] ?? 0);
                    $hWeekRate = (int)($h['week_rate'] ?? 0);
                    $hStreak = (int)($h['current_streak'] ?? 0);
                    $hFreqLabel = ($h['frequency'] ?? 'daily') === 'weekly' ? "{$hTarget} buổi/tuần" : 'Hằng ngày';
                ?>
                <div class="stat-habit-item">
                    <div class="stat-habit-head">
                        <div class="stat-habit-title">
                            <span class="habit-dot" style="background-color: <?= $hColor ?>;"></span>
                            <strong><?= $hName ?></strong>
                            <span class="habit-freq-pill"><?= htmlspecialchars($hFreqLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="stat-habit-nums">
                            <span><?= $hWeekDone ?>/<?= $hTarget ?></span>
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

    <div class="panel d-flex flex-column justify-content-between">
        <div class="panel-heading">
            <div>
                <h2>Nhịp duy trì 7 ngày qua</h2>
                <p>Số thói quen hoàn thành mỗi ngày</p>
            </div>
            <span class="select-chip"><?= (int)$habitSummary['weekly_avg_rate'] ?>% TB tuần</span>
        </div>

        <div class="stat-habit-bars">
            <?php
            $labels = $habitWeekly['labels'] ?? [];
            $dates = $habitWeekly['dates'] ?? [];
            $counts = $habitWeekly['counts'] ?? [];
            $maxHabits = max(1, (int)($habitWeekly['total_habits'] ?? 1));
            $todayDate = date('Y-m-d');
            foreach ($labels as $idx => $lbl):
                $cnt = (int)($counts[$idx] ?? 0);
                $dStr = $dates[$idx] ?? '';
                $isToday = ($dStr === $todayDate);
                $pct = min(100, (int)round(($cnt / $maxHabits) * 100));
            ?>
            <div class="stat-habit-bar-col <?= $isToday ? 'is-today' : '' ?>">
                <span class="stat-habit-bar-val"><?= $cnt ?></span>
                <div class="stat-habit-bar-col-track" title="<?= htmlspecialchars($lbl . ' (' . $cnt . '/' . $maxHabits . ')', ENT_QUOTES, 'UTF-8') ?>">
                    <div class="stat-habit-bar-col-fill" style="height: <?= max(6, $pct) ?>%; opacity: <?= $cnt > 0 ? '1' : '0.2' ?>;"></div>
                </div>
                <span class="stat-habit-bar-lbl"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="stat-habit-footer">
            <div>
                <small>Chuỗi hiện tại dài nhất</small>
                <strong><?= (int)$habitSummary['longest_current_streak'] ?> ngày</strong>
            </div>
            <div>
                <small>Kỷ lục cá nhân</small>
                <strong><?= (int)$habitSummary['best_streak_ever'] ?> ngày</strong>
            </div>
        </div>
    </div>
</div>
