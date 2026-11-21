<section class="welcome-row">
    <div>
        <div class="eyebrow">LÀM VIỆC CÙNG NHAU</div>
        <h1>Chia sẻ lịch trình</h1>
        <p class="page-subtitle">Chia sẻ liên kết lịch trình với bạn bè, đối tác hoặc đồng nghiệp một cách an toàn.</p>
    </div>
    <button class="btn btn-primary-soft" id="btnOpenInviteModal">
        <i class="fa-solid fa-user-plus me-2"></i>Mời người cùng xem lịch
    </button>
</section>

<div class="row g-4">
    <div class="col-lg-6">
        <!-- Chia sẻ bằng liên kết -->
        <div class="panel mb-4">
            <div class="panel-heading">
                <div>
                    <h2>Liên kết chia sẻ công khai</h2>
                    <p>Bất kỳ ai có liên kết này đều có thể xem lịch trình của bạn</p>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Đường dẫn chia sẻ (Chỉ xem)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-link"></i></span>
                    <input type="text" class="form-control" id="shareLinkInput" value="https://personal-calendar.local/share?token=pc_<?= substr(md5(Auth::id() ?? 1), 0, 16) ?>" readonly>
                    <button class="btn btn-primary-soft" id="btnCopyShareLink" type="button">
                        <i class="fa-regular fa-copy me-1"></i> Sao chép
                    </button>
                </div>
                <small class="text-muted mt-2 d-block" style="font-size: 10px;">
                    <i class="fa-solid fa-shield-halved me-1 text-success"></i> Người xem không thể chỉnh sửa hoặc xóa lịch trình của bạn.
                </small>
            </div>
        </div>

        <!-- Mời người dùng qua email -->
        <div class="panel">
            <div class="panel-heading">
                <div>
                    <h2>Mời cộng tác viên</h2>
                    <p>Gửi thư mời qua email với quyền hạn tùy chọn</p>
                </div>
            </div>
            <form id="inviteUserForm">
                <div class="mb-3">
                    <label for="inviteEmail" class="form-label fw-bold">Địa chỉ Email người nhận <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
                        <input type="email" class="form-control" id="inviteEmail" placeholder="colleague@example.com" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="inviteRole" class="form-label fw-bold">Quyền hạn truy cập</label>
                    <select class="form-select" id="inviteRole">
                        <option value="view" selected>Chỉ xem (View only)</option>
                        <option value="edit">Được chỉnh sửa lịch trình (Can edit)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary-soft" id="btnSendInvite">
                    <i class="fa-solid fa-paper-plane me-2"></i>Gửi lời mời chia sẻ
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <!-- Danh sách cộng tác viên đã chia sẻ -->
        <div class="panel">
            <div class="panel-heading">
                <div>
                    <h2>Người đang được chia sẻ</h2>
                    <p>Quản lý quyền truy cập lịch trình cá nhân</p>
                </div>
            </div>
            <div class="d-flex flex-column gap-3" id="sharedUsersList">
                <?php if (empty($data['shares'])): ?>
                    <div class="text-muted text-center py-4">Chưa có người dùng nào trong danh sách chia sẻ.</div>
                <?php else: ?>
                    <?php 
                    $avatarColors = [
                        ['#e0e7ff', '#4338ca'],
                        ['#dbeafe', '#1d4ed8'],
                        ['#fce7f3', '#be185d'],
                        ['#d1fae5', '#047857'],
                        ['#fef3c7', '#b45309']
                    ];
                    foreach ($data['shares'] as $idx => $share): 
                        $firstLetter = strtoupper(substr($share['shared_email'], 0, 1));
                        $isEdit = ($share['role'] === 'edit');
                        $colorPair = $avatarColors[$idx % count($avatarColors)];
                    ?>
                    <div class="d-flex align-items-center justify-content-between p-3 rounded border" style="background: var(--surface-2);" data-share-id="<?= $share['id'] ?>">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar" style="width: 34px; height: 34px; font-size: 11px; background: <?= $colorPair[0] ?>; color: <?= $colorPair[1] ?>; font-weight: 700;"><?= htmlspecialchars($firstLetter) ?></div>
                            <div>
                                <strong class="d-block" style="font-size: 11.5px;"><?= htmlspecialchars($share['shared_email']) ?></strong>
                                <small class="text-muted">
                                    <?php if ($isEdit): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Quyền chỉnh sửa</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-dark border">Chỉ xem</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-light text-danger btn-revoke-share" title="Hủy quyền chia sẻ">
                            <i class="fa-solid fa-user-xmark"></i> Hủy
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
