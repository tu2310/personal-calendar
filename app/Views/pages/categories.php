<?php
$categories = $data['categories'] ?? [];
$csrfToken = $data['csrf_token'] ?? '';

$presetIcons = [
    'fa-folder'         => 'Thư mục',
    'fa-briefcase'      => 'Công việc',
    'fa-graduation-cap' => 'Học tập',
    'fa-user'           => 'Cá nhân',
    'fa-handshake'      => 'Họp & Khách',
    'fa-heart-pulse'    => 'Sức khỏe',
    'fa-dumbbell'       => 'Thể thao',
    'fa-cart-shopping'  => 'Mua sắm',
    'fa-code'           => 'Lập trình',
    'fa-laptop'         => 'Công nghệ',
    'fa-palette'        => 'Thiết kế',
    'fa-lightbulb'      => 'Ý tưởng',
    'fa-bell'           => 'Nhắc nhở',
    'fa-star'           => 'Quan trọng',
    'fa-flag'           => 'Mục tiêu',
    'fa-bookmark'       => 'Đánh dấu',
    'fa-calendar-days'  => 'Sự kiện',
    'fa-rocket'         => 'Dự án mới'
];
?>
<section class="welcome-row">
    <div>
        <div class="eyebrow">PHÂN LOẠI LỊCH TRÌNH & CÔNG VIỆC</div>
        <h1>Quản lý danh mục</h1>
        <p class="page-subtitle">Tổ chức lịch trình và nhiệm vụ theo từng mảng cuộc sống với màu sắc và biểu tượng trực quan.</p>
    </div>
    <button class="btn btn-primary-soft" id="btnOpenAddCategoryModal">
        <i class="fa-solid fa-plus me-2"></i>Thêm danh mục mới
    </button>
</section>

<div class="category-grid" id="categoryGrid">
    <?php foreach ($categories as $cat): 
        $color = $cat['color'] ?: '#6658e8';
        $icon = !empty($cat['icon']) ? $cat['icon'] : 'fa-folder';
        $isSystem = empty($cat['user_id']);
    ?>
    <div class="panel category-card" 
         data-id="<?= $cat['id'] ?>" 
         data-name="<?= htmlspecialchars($cat['name']) ?>" 
         data-color="<?= $color ?>"
         data-icon="<?= htmlspecialchars($icon) ?>"
         data-system="<?= $isSystem ? '1' : '0' ?>">
        
        <div class="d-flex justify-content-between align-items-start mb-3">
            <span class="stat-icon" style="background-color: <?= $color ?>20; color: <?= $color ?>; width: 44px; height: 44px; font-size: 18px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="fa-solid <?= htmlspecialchars($icon) ?>"></i>
            </span>
            <div class="d-flex gap-1">
                <?php if (!$isSystem): ?>
                <button class="btn btn-sm btn-light btn-cat-edit" data-id="<?= $cat['id'] ?>" title="Sửa danh mục">
                    <i class="fa-solid fa-pen-to-square text-primary" style="font-size: 11px;"></i>
                </button>
                <button class="btn btn-sm btn-light btn-cat-delete text-danger" data-id="<?= $cat['id'] ?>" title="Xóa danh mục">
                    <i class="fa-solid fa-trash-can" style="font-size: 11px;"></i>
                </button>
                <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary" style="font-size: 10px;">Mặc định</span>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <h3 class="mb-1 fs-5 fw-bold"><?= htmlspecialchars($cat['name']) ?></h3>
            <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-inline-block rounded-circle" style="width: 10px; height: 10px; background-color: <?= $color ?>;"></span>
                    <small class="text-muted" style="font-size: 11px;"><?= $isSystem ? 'Danh mục hệ thống' : 'Danh mục cá nhân' ?></small>
                </div>
                <small class="text-muted" style="font-size: 10px;"><?= strtoupper($color) ?></small>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal Thêm / Sửa Danh mục -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="categoryModalLabel">
                    <i class="fa-solid fa-layer-group me-2 text-primary"></i>Thêm danh mục mới
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="categoryForm">
                <div class="modal-body p-4">
                    <input type="hidden" id="categoryId" name="id" value="">
                    <input type="hidden" id="categoryCsrf" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" id="categoryIcon" name="icon" value="fa-folder">

                    <!-- Live Preview Card -->
                    <div class="p-3 mb-4 rounded-3 border d-flex align-items-center gap-3" id="catPreviewBox" style="background: rgba(102, 88, 232, 0.05); border-color: rgba(102, 88, 232, 0.2) !important;">
                        <span class="stat-icon" id="catPreviewIcon" style="background-color: #6658e820; color: #6658e8; width: 44px; height: 44px; font-size: 18px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-folder"></i>
                        </span>
                        <div>
                            <div class="text-muted" style="font-size: 11px; font-weight: 600;">XEM TRƯỚC HIỂN THỊ</div>
                            <h5 class="mb-0 fw-bold" id="catPreviewName">Tên danh mục</h5>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="categoryName" class="form-label fw-bold">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoryName" name="name" placeholder="VD: Dự án khách hàng, Thể thao, Gia đình..." required maxlength="80">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Màu sắc đại diện</label>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <input type="color" class="form-control form-control-color p-1" id="categoryColor" name="color" value="#6658e8" title="Chọn màu sắc" style="width: 48px; height: 38px;">
                            <input type="text" class="form-control" id="categoryColorHex" value="#6658e8" placeholder="#6658e8" maxlength="7" style="max-width: 110px;">
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-2" id="presetColors">
                            <?php foreach (['#6658e8', '#38bdf8', '#10b981', '#f59e0b', '#f43f5e', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16', '#64748b'] as $c): ?>
                                <button type="button" class="btn p-0 rounded-circle border preset-color-btn" data-color="<?= $c ?>" style="width: 26px; height: 26px; background-color: <?= $c ?>;"></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- BỘ CHỌN BIỂU TƯỢNG (ICON PICKER) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold d-flex justify-content-between align-items-center">
                            <span>Biểu tượng đại diện (Icon)</span>
                            <small class="text-muted" style="font-size: 11px;">Chọn 1 biểu tượng phù hợp</small>
                        </label>
                        <div class="icon-picker-grid d-flex flex-wrap gap-2 p-2 rounded border" style="background: var(--surface-2); max-height: 170px; overflow-y: auto;">
                            <?php foreach ($presetIcons as $iconClass => $iconLabel): ?>
                                <button type="button" 
                                        class="btn btn-sm btn-light icon-select-btn d-flex align-items-center gap-2 px-2 py-1 <?= $iconClass === 'fa-folder' ? 'active' : '' ?>" 
                                        data-icon="<?= $iconClass ?>" 
                                        title="<?= $iconLabel ?>"
                                        style="border-radius: 8px; font-size: 12px;">
                                    <i class="fa-solid <?= $iconClass ?>"></i>
                                    <span><?= $iconLabel ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-end p-3 gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary-soft btn-sm" id="btnSaveCategory">
                        <i class="fa-solid fa-check me-1"></i> Lưu danh mục
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
