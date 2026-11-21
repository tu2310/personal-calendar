<!-- MODAL THÊM / CHỈNH SỬA LỊCH TRÌNH -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="eventModalLabel">
                    <i class="fa-regular fa-calendar-plus me-2 text-primary"></i><span>Thêm lịch trình mới</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="eventForm">
                <div class="modal-body p-4">
                    <input type="hidden" id="eventId" name="id" value="">
                    <input type="hidden" id="csrfToken" name="csrf_token" value="<?= htmlspecialchars($data['csrf_token'] ?? '') ?>">

                    <div class="mb-3">
                        <label for="eventTitle" class="form-label fw-bold">Tiêu đề lịch trình <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="eventTitle" name="title" placeholder="VD: Họp định kỳ với nhóm thiết kế" required maxlength="180">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="eventCategory" class="form-label fw-bold">Danh mục</label>
                            <select class="form-select" id="eventCategory" name="category_id">
                                <option value="">-- Chọn danh mục --</option>
                                <?php foreach (($data['categories'] ?? []) as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="eventLocation" class="form-label fw-bold">Địa điểm</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-location-dot"></i></span>
                                <input type="text" class="form-control" id="eventLocation" name="location" placeholder="VD: Phòng họp A, Zoom, Google Meet">
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Thời gian bắt đầu <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-7">
                                    <input type="date" class="form-control" id="eventStartDate" name="start_date" required>
                                </div>
                                <div class="col-5">
                                    <input type="time" class="form-control" id="eventStartTime" name="start_time" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Thời gian kết thúc <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-7">
                                    <input type="date" class="form-control" id="eventEndDate" name="end_date" required>
                                </div>
                                <div class="col-5">
                                    <input type="time" class="form-control" id="eventEndTime" name="end_time" required>
                                </div>
                            </div>
                            <small class="text-muted" style="font-size: 10px;">* Giờ kết thúc phải sau giờ bắt đầu</small>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="eventPriority" class="form-label fw-bold">Mức độ ưu tiên</label>
                            <select class="form-select" id="eventPriority" name="priority">
                                <option value="low">Thấp</option>
                                <option value="medium" selected>Trung bình</option>
                                <option value="high">Cao</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="eventStatus" class="form-label fw-bold">Trạng thái</label>
                            <select class="form-select" id="eventStatus" name="status">
                                <option value="pending" selected>Chưa hoàn thành</option>
                                <option value="completed">Đã hoàn thành</option>
                            </select>
                        </div>
                    </div>

                    <!-- NHẮC NHỞ THÔNG MINH -->
                    <div class="p-3 mb-3 rounded-3 border" style="background: var(--surface-2); border-color: var(--line) !important;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-check-label fw-bold d-flex align-items-center gap-2 cursor-pointer" for="eventHasReminder">
                                <i class="fa-solid fa-bell text-primary"></i>
                                <span>Bật thông báo nhắc nhở thông minh</span>
                            </label>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="eventHasReminder" name="has_reminder" value="1" checked style="cursor: pointer; width: 38px; height: 20px;">
                            </div>
                        </div>

                        <div id="reminderOptionsRow" class="row g-2 align-items-center mt-1">
                            <div class="col-md-6">
                                <label for="eventReminderMinutes" class="form-label mb-1 text-muted" style="font-size: 11.5px; font-weight: 600;">Thời gian nhắc trước:</label>
                                <select class="form-select form-select-sm" id="eventReminderMinutes" name="reminder_minutes">
                                    <option value="0">Đúng giờ sự kiện bắt đầu (0 phút)</option>
                                    <option value="5">Trước 5 phút</option>
                                    <option value="10">Trước 10 phút</option>
                                    <option value="15" selected>Trước 15 phút (Khuyên dùng)</option>
                                    <option value="30">Trước 30 phút</option>
                                    <option value="60">Trước 1 giờ (60 phút)</option>
                                    <option value="1440">Trước 1 ngày (24 giờ)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="pt-3 text-muted" style="font-size: 11px;" id="reminderPreviewText">
                                    <i class="fa-solid fa-clock-rotate-left me-1 text-info"></i>Sẽ nhắc nhở trước khi lịch bắt đầu.
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="eventReminder" name="reminder_at" value="">
                    </div>

                    <div class="mb-0">
                        <label for="eventDescription" class="form-label fw-bold">Mô tả chi tiết</label>
                        <textarea class="form-control" id="eventDescription" name="description" rows="3" placeholder="Ghi chú chi tiết về nội dung công việc hoặc tài liệu cần chuẩn bị..."></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-between p-3">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnDeleteEvent" style="display: none;">
                            <i class="fa-solid fa-trash-can me-1"></i> Xóa lịch
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-primary-soft btn-sm" id="btnSaveEvent">
                            <i class="fa-solid fa-check me-1"></i> Lưu lịch trình
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL XEM CHI TIẾT LỊCH TRÌNH -->
<div class="modal fade" id="eventDetailModal" tabindex="-1" aria-labelledby="eventDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="eventDetailModalLabel">
                    <i class="fa-regular fa-calendar-check me-2 text-primary"></i>Chi tiết lịch trình
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h4 class="fw-bold mb-0 text-break" id="detailTitle"></h4>
                    <span class="badge" id="detailCategoryBadge"></span>
                </div>

                <div class="d-flex flex-column gap-2 mb-3">
                    <div class="d-flex align-items-center text-muted">
                        <i class="fa-regular fa-clock me-2 text-primary" style="width: 16px;"></i>
                        <span id="detailTime"></span>
                    </div>
                    <div class="d-flex align-items-center text-muted" id="detailLocationRow">
                        <i class="fa-solid fa-location-dot me-2 text-danger" style="width: 16px;"></i>
                        <span id="detailLocation"></span>
                    </div>
                    <div class="d-flex align-items-center text-muted" id="detailReminderRow">
                        <i class="fa-regular fa-bell me-2 text-warning" style="width: 16px;"></i>
                        <span id="detailReminderText">Không bật nhắc nhở</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <span class="badge" id="detailPriorityBadge"></span>
                        <span class="badge" id="detailStatusBadge"></span>
                    </div>
                </div>

                <div class="p-3 rounded" style="background-color: var(--surface-2); border: 1px solid var(--line);">
                    <div class="fw-semibold text-muted mb-1" style="font-size: 11px;">Ghi chú / Mô tả:</div>
                    <p class="mb-0 text-break" id="detailDescription" style="font-size: 12px; white-space: pre-wrap;">Không có mô tả.</p>
                </div>
            </div>
            <div class="modal-footer justify-content-between p-3">
                <button type="button" class="btn btn-outline-danger btn-sm" id="btnDetailDelete">
                    <i class="fa-solid fa-trash-can me-1"></i> Xóa
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-primary-soft btn-sm" id="btnDetailEdit">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Chỉnh sửa
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
