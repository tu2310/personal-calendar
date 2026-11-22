<section class="welcome-row">
    <div>
        <div class="eyebrow"><span class="eyebrow-dot"></span>Lịch biểu</div>
        <h1>Lịch</h1>
        <p class="page-subtitle">Xem và sắp xếp lịch trình cá nhân theo ngày, tuần hoặc tháng</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary-soft" id="btnOpenAddModal">
            <i class="fa-solid fa-plus"></i><span>Thêm lịch mới</span>
        </button>
    </div>
</section>

<?php require __DIR__ . '/../partials/calendar_toolbar.php'; ?>

<!-- Khung FullCalendar -->
<div class="calendar-wrapper has-custom-toolbar">
    <div id="calendar" data-initial-view="dayGridMonth"></div>
</div>

<?php require_once __DIR__ . '/../partials/event_modals.php'; ?>
