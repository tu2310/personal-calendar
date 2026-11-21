<!-- THANH CÔNG CỤ LỊCH MỚI (MODERN SAAS TOOLBAR) -->
<div class="calendar-toolbar-card" id="calendarToolbar">
    <!-- Hàng 1: Danh mục -->
    <div class="toolbar-category-row">
        <div class="toolbar-cat-title">
            <span class="cat-title-icon"><i class="fa-solid fa-calendar-day"></i></span>
            <span>Danh mục:</span>
        </div>
        <div class="toolbar-cat-chips" id="categoryChipsContainer">
            <?php foreach (($data['categories'] ?? []) as $cat): 
                $c = $cat['color'] ?: '#c2410c';
            ?>
                <button type="button" class="cat-chip" data-category-id="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>" style="--chip-color: <?= $c ?>; --chip-bg: <?= $c ?>18; --chip-border: <?= $c ?>35;">
                    <span class="chip-dot" style="background-color: <?= $c ?>;"></span>
                    <span class="chip-name"><?= htmlspecialchars($cat['name']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Hàng 2: Điều khiển lịch -->
    <div class="toolbar-controls-bar">
        <!-- Bên trái: Hai nút chuyển tách riêng & Nút Hôm Nay -->
        <div class="controls-left">
            <button type="button" class="cal-nav-btn" id="calBtnPrev" title="Thời gian trước" aria-label="Thời gian trước">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <button type="button" class="cal-nav-btn" id="calBtnNext" title="Thời gian tiếp theo" aria-label="Thời gian tiếp theo">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
            <button type="button" class="cal-today-btn" id="calBtnToday" title="Trở về hôm nay">
                <span class="today-icon"><i class="fa-solid fa-calendar-day"></i></span>
                <span>Hôm Nay</span>
            </button>
        </div>

        <!-- Chính giữa: Tiêu đề tháng và năm căn giữa chính xác có icon lịch và line hai bên -->
        <div class="controls-center">
            <span class="title-divider-line"></span>
            <div class="calendar-title-badge">
                <span class="cal-center-icon"><i class="fa-solid fa-calendar-days"></i></span>
                <span id="calendarTitleText"></span>
            </div>
            <span class="title-divider-line"></span>
        </div>

        <!-- Bên phải: Bộ chọn Tháng / Tuần / Ngày dạng Segmented Control -->
        <div class="controls-right">
            <div class="view-segmented-control" role="group" aria-label="Chế độ xem lịch">
                <button type="button" class="view-seg-btn" id="calViewMonth" data-view="dayGridMonth">
                    <i class="fa-solid fa-calendar"></i>
                    <span>Tháng</span>
                </button>
                <button type="button" class="view-seg-btn" id="calViewWeek" data-view="timeGridWeek">
                    <i class="fa-solid fa-bars-staggered"></i>
                    <span>Tuần</span>
                </button>
                <button type="button" class="view-seg-btn" id="calViewDay" data-view="timeGridDay">
                    <i class="fa-regular fa-calendar-check"></i>
                    <span>Ngày</span>
                </button>
                <button type="button" class="view-seg-btn" id="calViewList" data-view="listMonth">
                    <i class="fa-solid fa-list-ul"></i>
                    <span>Danh sách</span>
                </button>
            </div>
        </div>
    </div>
</div>
