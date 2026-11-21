<section class="welcome-row">
    <div>
        <div class="eyebrow">Lịch biểu</div>
        <h1>Danh sách lịch</h1>
        <p class="page-subtitle">Chế độ xem danh sách sự kiện nhiều ngày theo thứ tự thời gian</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary-soft" id="btnOpenAddModal">
            <i class="fa-solid fa-plus"></i><span>Thêm lịch mới</span>
        </button>
    </div>
</section>

<?php require __DIR__ . '/../partials/calendar_toolbar.php'; ?>

<div class="calendar-wrapper has-custom-toolbar">
    <div id="calendar" data-initial-view="listMonth"></div>
</div>

<?php require_once __DIR__ . '/../partials/event_modals.php'; ?>
