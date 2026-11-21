<?php
$stats = $data['stats'] ?? ['today_count' => 0, 'upcoming_count' => 0, 'pending_count' => 0, 'completed_count' => 0];
$total = ($stats['completed_count'] + $stats['pending_count']);
$rate = $total > 0 ? round(($stats['completed_count'] / $total) * 100) : 0;
$weekly = $data['weekly'] ?? [];
?>
<section class="welcome-row">
    <div>
        <div class="eyebrow">BÁO CÁO HIỆU SUẤT</div>
        <h1>Thống kê hoạt động</h1>
        <p class="page-subtitle">Phân tích chi tiết mức độ hoàn thành công việc và phân bổ thời gian biểu của bạn.</p>
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
