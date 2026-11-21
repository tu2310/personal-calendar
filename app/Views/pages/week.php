<section class="welcome-row">
    <div>
        <div class="eyebrow">Lịch biểu</div>
        <h1>Lịch tuần</h1>
        <p class="page-subtitle">Theo dõi thời gian biểu 7 ngày theo khung giờ chi tiết</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary-soft" id="btnOpenAddModal">
            <i class="fa-solid fa-plus me-2"></i>Thêm lịch mới
        </button>
    </div>
</section>

<?php require __DIR__ . '/../partials/calendar_toolbar.php'; ?>

<!-- Khung FullCalendar -->
<div class="calendar-wrapper has-custom-toolbar">
    <div id="calendar" data-initial-view="timeGridWeek"></div>
</div>

<?php require_once __DIR__ . '/../partials/event_modals.php'; ?>
