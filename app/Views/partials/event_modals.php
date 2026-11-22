<?php
$modalCsrfToken = $data['csrf_token'] ?? (class_exists('Auth') ? Auth::csrfToken() : '');
$modalTodayYmd = date('Y-m-d');
?>
<!-- MODAL THÊM / CHỈNH SỬA LỊCH TRÌNH -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="eventModalLabel">
                    <i class="fa-regular fa-calendar-plus me-2 text-primary-accent"></i><span>Thêm lịch trình</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="eventForm">
                <div class="modal-body p-3 p-sm-4">
                    <input type="hidden" id="eventId" name="id" value="">
                    <input type="hidden" id="csrfToken" name="csrf_token" value="<?= htmlspecialchars($modalCsrfToken) ?>">

                    <div class="mb-3">
                        <label for="eventTitle" class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="eventTitle" name="title" placeholder="VD: Họp định kỳ nhóm thiết kế, Khám sức khỏe" required maxlength="180">
                    </div>

                    <!-- Khối Ngày thực hiện & Khung giờ -->
                    <div class="schedule-compact-box mb-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2 pb-1 border-bottom">
                            <label class="allday-inline-check" for="eventMultiDayToggle">
                                <input type="checkbox" class="form-check-input m-0" id="eventMultiDayToggle">
                                <span class="fw-medium">Sự kiện nhiều ngày</span>
                            </label>
                            <label class="allday-inline-check" for="eventAllDayToggle">
                                <input type="checkbox" class="form-check-input m-0" id="eventAllDayToggle">
                                <span>Chưa gắn giờ / Cả ngày</span>
                            </label>
                        </div>

                        <div class="row g-2 align-items-end" id="eventDateRow">
                            <div class="col-12 col-sm-5" id="eventStartDateCol">
                                <label for="eventStartDate" class="form-label" id="eventStartDateLabel">Ngày thực hiện <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="eventStartDate" name="start_date" value="<?= $modalTodayYmd ?>" required>
                            </div>
                            <div class="col-12 col-sm-6 d-none" id="eventEndDateCol">
                                <label for="eventEndDate" class="form-label" id="eventEndDateLabel">Đến ngày <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="eventEndDate" name="end_date" value="<?= $modalTodayYmd ?>">
                            </div>
                            <div class="col-12 col-sm-7" id="eventTimeInputsCol">
                                <label for="eventStartTime" class="form-label mb-1" id="eventTimeInputsLabel">Khung giờ</label>
                                <div class="time-range-inline" id="eventTimeInputsWrap">
                                    <input type="time" class="form-control" id="eventStartTime" name="start_time" value="08:00" aria-label="Giờ bắt đầu">
                                    <span class="time-range-sep">–</span>
                                    <input type="time" class="form-control" id="eventEndTime" name="end_time" value="09:00" aria-label="Giờ kết thúc">
                                </div>
                            </div>
                        </div>

                        <div id="eventMultiDayHint" class="d-none mt-2 pt-2 border-top">
                            <small class="text-muted" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-circle-info me-1 text-primary"></i>Dành cho chuyến công tác, kỳ nghỉ, hội thảo hoặc sự kiện diễn ra xuyên suốt nhiều ngày.
                            </small>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="eventRepeatType" class="form-label">Lặp lại</label>
                            <select class="form-select" id="eventRepeatType" name="repeat_type">
                                <option value="none" selected>Không lặp</option>
                                <option value="daily">Hằng ngày</option>
                                <option value="weekly">Hằng tuần</option>
                                <option value="monthly">Hằng tháng</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="eventCategory" class="form-label">Danh mục</label>
                            <select class="form-select" id="eventCategory" name="category_id">
                                <option value="">Chọn danh mục</option>
                                <?php foreach (($data['categories'] ?? []) as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="eventLocation" class="form-label">Địa điểm</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-location-dot"></i></span>
                            <input type="text" class="form-control" id="eventLocation" name="location" placeholder="Phòng họp, Google Meet...">
                        </div>
                    </div>
                    <input type="hidden" id="eventPriority" name="priority" value="medium">
                    <input type="hidden" id="eventStatus" name="status" value="pending">

                    <!-- NHẮC NHỞ -->
                    <div class="p-2 px-3 mb-3 rounded-3 border" style="background: var(--surface-2); border-color: var(--line) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <label class="form-check-label fw-medium d-flex align-items-center gap-2 cursor-pointer" for="eventHasReminder" style="font-size: 0.8125rem;">
                                <i class="fa-regular fa-bell text-primary-accent"></i>
                                <span>Nhắc nhở trước giờ hẹn</span>
                            </label>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="eventHasReminder" name="has_reminder" value="1" checked style="cursor: pointer; width: 2.25rem; height: 1.15rem;">
                            </div>
                        </div>

                        <div id="reminderOptionsRow" class="row g-2 align-items-center mt-2">
                            <div class="col-sm-7">
                                <select class="form-select form-select-sm" id="eventReminderMinutes" name="reminder_minutes" aria-label="Thời gian nhắc trước">
                                    <option value="0">Đúng giờ bắt đầu</option>
                                    <option value="5">Trước 5 phút</option>
                                    <option value="10">Trước 10 phút</option>
                                    <option value="15" selected>Trước 15 phút</option>
                                    <option value="30">Trước 30 phút</option>
                                    <option value="60">Trước 1 giờ</option>
                                    <option value="1440">Trước 1 ngày</option>
                                </select>
                            </div>
                            <div class="col-sm-5">
                                <div class="text-muted" style="font-size: 0.75rem;" id="reminderPreviewText">
                                    Sẽ nhắc trước giờ bắt đầu.
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="eventReminder" name="reminder_at" value="">
                    </div>

                    <div class="mb-0">
                        <label for="eventDescription" class="form-label">Ghi chú</label>
                        <textarea class="form-control" id="eventDescription" name="description" rows="2" placeholder="Nội dung chi tiết hoặc tài liệu cần chuẩn bị"></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-between p-3">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnDeleteEvent" style="display: none;">
                            <i class="fa-solid fa-trash-can me-1"></i>Xóa lịch
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-primary-soft btn-sm" id="btnSaveEvent">
                            <i class="fa-solid fa-check me-1"></i>Lưu lịch trình
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
                <h5 class="modal-title fw-semibold" id="eventDetailModalLabel">
                    <i class="fa-regular fa-calendar-check me-2 text-primary-accent"></i>Chi tiết lịch trình
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body p-3 p-sm-4">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <h4 class="fw-semibold mb-0 text-break" style="font-size: 1.125rem;" id="detailTitle"></h4>
                    <span class="badge flex-shrink-0" id="detailCategoryBadge"></span>
                </div>

                <div class="d-flex flex-column gap-2 mb-3" style="font-size: 0.8125rem;">
                    <div class="d-flex align-items-center text-muted">
                        <i class="fa-regular fa-clock me-2 text-primary-accent" style="width: 16px;"></i>
                        <span id="detailTime" style="font-family: var(--font-mono); font-variant-numeric: tabular-nums;"></span>
                    </div>
                    <div class="d-flex align-items-center text-muted" id="detailRepeatRow" style="display: none;">
                        <i class="fa-solid fa-repeat me-2 text-primary-accent" style="width: 16px;"></i>
                        <span id="detailRepeatText"></span>
                    </div>
                    <div class="d-flex align-items-center text-muted" id="detailLocationRow">
                        <i class="fa-solid fa-location-dot me-2 text-danger" style="width: 16px;"></i>
                        <span id="detailLocation"></span>
                    </div>
                    <div class="d-flex align-items-center text-muted" id="detailReminderRow">
                        <i class="fa-regular fa-bell me-2 text-warning" style="width: 16px;"></i>
                        <span id="detailReminderText">Không bật nhắc nhở</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-1" style="display: none;">
                        <span class="badge" id="detailPriorityBadge"></span>
                        <span class="badge" id="detailStatusBadge"></span>
                    </div>
                </div>

                <div class="p-3 rounded" style="background-color: var(--surface-2); border: 1px solid var(--line);">
                    <div class="fw-medium text-muted mb-1" style="font-size: 0.75rem;">Ghi chú:</div>
                    <p class="mb-0 text-break" id="detailDescription" style="font-size: 0.8125rem; white-space: pre-wrap;">Không có ghi chú.</p>
                </div>
            </div>
            <div class="modal-footer justify-content-between p-3">
                <button type="button" class="btn btn-outline-danger btn-sm" id="btnDetailDelete">
                    <i class="fa-solid fa-trash-can me-1"></i>Xóa
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-primary-soft btn-sm" id="btnDetailEdit">
                        <i class="fa-solid fa-pen-to-square me-1"></i>Chỉnh sửa
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
