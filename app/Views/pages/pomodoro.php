<section class="welcome-row">
    <div>
        <div class="eyebrow">TẬP TRUNG VÀO ĐIỀU QUAN TRỌNG</div>
        <h1>Đồng hồ Pomodoro</h1>
        <p class="page-subtitle">Phương pháp làm việc tập trung sâu trong 25 phút, kết hợp nghỉ ngơi tái tạo năng lượng.</p>
    </div>
</section>

<div class="pomodoro-layout">
    <div class="panel pomodoro-panel">
        <div class="pomodoro-mode">
            <button class="mode-btn active" data-minutes="25">Tập trung (25p)</button>
            <button class="mode-btn" data-minutes="5">Nghỉ ngắn (5p)</button>
            <button class="mode-btn" data-minutes="15">Nghỉ dài (15p)</button>
        </div>

        <div class="timer-ring">
            <div>
                <strong id="timerDisplay">25:00</strong>
                <span id="timerCaption">SẴN SÀNG TẬP TRUNG?</span>
            </div>
        </div>

        <div class="timer-actions">
            <button class="btn btn-primary-soft" id="timerStart">
                <i class="fa-solid fa-play me-2"></i>Bắt đầu
            </button>
            <button class="btn btn-light" id="timerReset">
                <i class="fa-solid fa-rotate-left me-2"></i>Đặt lại
            </button>
        </div>
        <p class="timer-hint">Một phiên tập trung thường kéo dài 25 phút làm việc, sau đó nghỉ giải lao 5 phút.</p>
    </div>

    <div class="panel pomodoro-side">
        <span class="stat-icon purple mb-3"><i class="fa-solid fa-trophy"></i></span>
        <h2>Tiến độ hôm nay</h2>
        <?php 
        $cycles = (int)($data['pomoCycles'] ?? 0);
        $percent = min(100, round(($cycles / 4) * 100));
        ?>
        <div class="focus-number"><span id="pomoCycles"><?= $cycles ?></span> <small>/ 4 vòng</small></div>
        <p><?= $cycles > 0 ? "Bạn đã hoàn thành {$cycles} phiên tập trung! Hãy tiếp tục duy trì nhịp độ làm việc hiệu quả." : "Hoàn thành phiên đầu tiên để bắt đầu chuỗi tập trung hiệu quả của bạn hôm nay." ?></p>
        <div class="progress">
            <div class="progress-bar" id="pomoProgressBar" style="width: <?= $percent ?>%"></div>
        </div>
    </div>
</div>
