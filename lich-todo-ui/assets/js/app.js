import { store } from './store.js';
import { Router } from './router.js';
import { icon, showToast, openModal, closeModal, formatDateVN, isDateToday, isDateOverdue, renderSkeletonState, renderEmptyState, renderErrorState } from './ui.js';

// Global state variables for UI interactions
let currentCalendarView = 'month'; // 'month' | 'week' | 'day' | 'list'
let selectedCalendarDate = new Date().toISOString().split('T')[0];
let calendarCategoryFilter = 'all';
let taskCategoryFilter = 'all';
let taskSortBy = 'dueDate'; // 'dueDate' | 'priority' | 'title'
let taskSearchQuery = '';
let completedGroupCollapsed = true;

// App Actions accessible globally for inline handlers
window.appActions = {
  // Navigation
  navigate(path) {
    window.location.hash = path;
  },

  // Demo state switch
  setDemoMode(mode) {
    store.setDemoState(mode);
    renderCurrentScreen();
  },

  // Modal handlers
  openAddTaskModal(presetDate = '') {
    const titleInput = document.getElementById('task-input-title');
    const dateInput = document.getElementById('task-input-due-date');
    const timeInput = document.getElementById('task-input-due-time');
    const catInput = document.getElementById('task-input-category');
    const prioInput = document.getElementById('task-input-priority');
    const notesInput = document.getElementById('task-input-notes');
    const subtaskContainer = document.getElementById('task-subtasks-editor');

    if (titleInput) titleInput.value = '';
    if (dateInput) dateInput.value = presetDate || new Date().toISOString().split('T')[0];
    if (timeInput) timeInput.value = '';
    if (catInput) catInput.value = 'cat-work';
    if (prioInput) prioInput.value = 'medium';
    if (notesInput) notesInput.value = '';
    if (subtaskContainer) subtaskContainer.innerHTML = '';

    document.getElementById('task-modal-title').textContent = 'Thêm việc mới';
    document.getElementById('task-form-error').classList.remove('visible');
    document.getElementById('task-edit-id').value = '';

    openModal('task-modal');
  },

  setQuickDueDate(offsetDays) {
    const d = new Date();
    d.setDate(d.getDate() + offsetDays);
    const dateStr = d.toISOString().split('T')[0];
    const input = document.getElementById('task-input-due-date');
    if (input) input.value = dateStr;

    document.querySelectorAll('.quick-due-chips .chip-btn').forEach(btn => btn.classList.remove('active'));
    if (offsetDays === 0) document.getElementById('chip-today')?.classList.add('active');
    else if (offsetDays === 1) document.getElementById('chip-tomorrow')?.classList.add('active');
    else if (offsetDays === 7) document.getElementById('chip-next-week')?.classList.add('active');
  },

  saveTaskFromModal() {
    const id = document.getElementById('task-edit-id').value;
    const titleInput = document.getElementById('task-input-title');
    const errorEl = document.getElementById('task-form-error');
    const title = titleInput.value.trim();

    if (!title) {
      errorEl.textContent = 'Vui lòng nhập tiêu đề công việc';
      errorEl.classList.add('visible');
      titleInput.focus();
      return;
    }

    const dueDate = document.getElementById('task-input-due-date').value;
    const dueTime = document.getElementById('task-input-due-time').value;
    const category = document.getElementById('task-input-category').value;
    const priority = document.getElementById('task-input-priority').value;
    const notes = document.getElementById('task-input-notes').value;

    // Collect subtasks
    const subtasks = [];
    document.querySelectorAll('#task-subtasks-editor .subtask-edit-row').forEach((row, i) => {
      const val = row.querySelector('input[type="text"]').value.trim();
      const chk = row.querySelector('input[type="checkbox"]').checked;
      if (val) {
        subtasks.push({ id: 'sub-' + Date.now() + '-' + i, title: val, completed: chk });
      }
    });

    if (id) {
      store.updateTask(id, { title, dueDate, dueTime, category, priority, notes, subtasks });
      showToast(`Đã lưu thay đổi: "${title}"`);
    } else {
      store.addTask({ title, dueDate, dueTime, category, priority, notes, subtasks });
      showToast(`Đã thêm việc: "${title}"`);
    }

    closeModal('task-modal');
    renderCurrentScreen();
  },

  openEditTaskModal(id) {
    const task = store.getTask(id);
    if (!task) return;

    document.getElementById('task-edit-id').value = task.id;
    document.getElementById('task-modal-title').textContent = 'Chỉnh sửa công việc';
    document.getElementById('task-input-title').value = task.title;
    document.getElementById('task-input-due-date').value = task.dueDate || '';
    document.getElementById('task-input-due-time').value = task.dueTime || '';
    document.getElementById('task-input-category').value = task.category || 'cat-work';
    document.getElementById('task-input-priority').value = task.priority || 'medium';
    document.getElementById('task-input-notes').value = task.notes || '';
    document.getElementById('task-form-error').classList.remove('visible');

    const subtaskContainer = document.getElementById('task-subtasks-editor');
    subtaskContainer.innerHTML = '';
    (task.subtasks || []).forEach(sub => {
      window.appActions.addSubtaskInput(sub.title, sub.completed);
    });

    openModal('task-modal');
  },

  addSubtaskInput(initialTitle = '', isChecked = false) {
    const subtaskContainer = document.getElementById('task-subtasks-editor');
    const row = document.createElement('div');
    row.className = 'subtask-edit-row';
    row.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:6px;';
    row.innerHTML = `
      <input type="checkbox" ${isChecked ? 'checked' : ''} aria-label="Hoàn thành việc con">
      <input type="text" class="form-input" style="flex:1;" value="${initialTitle}" placeholder="Nhập tên việc con...">
      <button type="button" class="btn btn-icon btn-ghost" onclick="this.parentElement.remove()" aria-label="Xóa việc con">
        ${icon('x', 'icon-sm')}
      </button>
    `;
    subtaskContainer.appendChild(row);
  },

  openTaskDetailModal(id) {
    const task = store.getTask(id);
    if (!task) return;

    const modal = document.getElementById('task-detail-modal');
    const categories = store.getCategories();
    const cat = categories.find(c => c.id === task.category) || { name: 'Chung', color: '#64748b' };

    let subtasksHtml = '';
    if (task.subtasks && task.subtasks.length > 0) {
      subtasksHtml = `
        <div style="margin-top:16px;">
          <h4 style="font-size:14px;font-weight:700;margin-bottom:8px;">Việc con (${task.subtasks.filter(s=>s.completed).length}/${task.subtasks.length})</h4>
          <div class="subtask-list">
            ${task.subtasks.map((s, idx) => `
              <div class="subtask-item ${s.completed ? 'checked' : ''}">
                <button type="button" class="custom-checkbox ${s.completed ? 'checked' : ''}" onclick="window.appActions.toggleSubtask('${task.id}', ${idx})" aria-label="Đổi trạng thái việc con">
                  ${s.completed ? icon('check', 'icon-sm') : ''}
                </button>
                <span>${s.title}</span>
              </div>
            `).join('')}
          </div>
        </div>
      `;
    }

    modal.querySelector('.modal-panel').innerHTML = `
      <div class="modal-header">
        <div style="display:flex;align-items:center;gap:8px;">
          <button type="button" class="custom-checkbox ${task.completed ? 'checked' : ''}" onclick="window.appActions.toggleTask('${task.id}')" aria-label="Đổi trạng thái hoàn thành">
            ${task.completed ? icon('check', 'icon-sm') : ''}
          </button>
          <h2 class="modal-title" style="${task.completed ? 'text-decoration:line-through;color:var(--text-muted);' : ''}">${task.title}</h2>
        </div>
        <button type="button" class="btn btn-icon btn-ghost" onclick="window.appActions.closeModal('task-detail-modal')" aria-label="Đóng">
          ${icon('x')}
        </button>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <span class="badge" style="background-color:${cat.bg || 'var(--bg-subtle)'};color:${cat.color};">
          <span class="badge-dot" style="background-color:${cat.color};"></span>
          ${cat.name}
        </span>
        <span class="badge badge-priority-${task.priority}">
          ${icon('flag', 'icon-sm')} Ưu tiên ${task.priority === 'high' ? 'Cao' : task.priority === 'medium' ? 'Trung bình' : 'Thấp'}
        </span>
        ${task.dueDate ? `
          <span class="badge ${isDateOverdue(task.dueDate) && !task.completed ? 'badge-priority-high' : 'badge-cat'}">
            ${icon('clock', 'icon-sm')} ${formatDateVN(task.dueDate)} ${task.dueTime ? task.dueTime : ''}
          </span>
        ` : ''}
      </div>

      ${task.notes ? `
        <div style="background-color:var(--bg-subtle);padding:12px;border-radius:var(--radius-md);margin-bottom:16px;font-size:14px;">
          <div style="font-weight:600;margin-bottom:4px;color:var(--text-secondary);">Ghi chú:</div>
          <div>${task.notes}</div>
        </div>
      ` : ''}

      ${subtasksHtml}

      <div style="margin-top:20px;border-top:1px solid var(--border-subtle);padding-top:12px;font-size:12px;color:var(--text-muted);">
        <div style="font-weight:600;margin-bottom:4px;">Lịch sử thay đổi:</div>
        <div>• Tạo vào: 2 ngày trước bởi Người dùng</div>
        <div>• Cập nhật trạng thái gần nhất: Hôm nay</div>
      </div>

      <div class="modal-footer" style="justify-content:space-between;margin-top:24px;">
        <button type="button" class="btn btn-danger" onclick="window.appActions.deleteTask('${task.id}');window.appActions.closeModal('task-detail-modal');">
          ${icon('trash')} Xóa việc
        </button>
        <div style="display:flex;gap:8px;">
          <button type="button" class="btn btn-secondary" onclick="window.appActions.duplicateTask('${task.id}');window.appActions.closeModal('task-detail-modal');">
            ${icon('copy')} Nhân bản
          </button>
          <button type="button" class="btn btn-primary" onclick="window.appActions.closeModal('task-detail-modal');window.appActions.openEditTaskModal('${task.id}');">
            ${icon('edit')} Sửa
          </button>
        </div>
      </div>
    `;

    openModal('task-detail-modal');
  },

  toggleSubtask(taskId, subtaskIndex) {
    const task = store.getTask(taskId);
    if (!task || !task.subtasks || !task.subtasks[subtaskIndex]) return;
    task.subtasks[subtaskIndex].completed = !task.subtasks[subtaskIndex].completed;
    store.updateTask(taskId, { subtasks: task.subtasks });
    window.appActions.openTaskDetailModal(taskId);
    renderCurrentScreen();
  },

  toggleTask(id) {
    store.toggleTaskCompleted(id);
    renderCurrentScreen();
  },

  deleteTask(id) {
    store.deleteTask(id);
    renderCurrentScreen();
  },

  duplicateTask(id) {
    const cloned = store.duplicateTask(id);
    if (cloned) showToast(`Đã nhân bản "${cloned.title}"`);
    renderCurrentScreen();
  },

  postponeTask(id) {
    store.postponeTask(id, 1);
    renderCurrentScreen();
  },

  // Event Modals
  openAddEventModal(presetDate = '', presetTime = '') {
    document.getElementById('event-edit-id').value = '';
    document.getElementById('event-modal-title').textContent = 'Thêm sự kiện mới';
    document.getElementById('event-input-title').value = '';
    document.getElementById('event-input-start-date').value = presetDate || new Date().toISOString().split('T')[0];
    document.getElementById('event-input-end-date').value = presetDate || new Date().toISOString().split('T')[0];
    document.getElementById('event-input-start-time').value = presetTime || '09:00';
    document.getElementById('event-input-end-time').value = '10:00';
    document.getElementById('event-input-allday').checked = false;
    document.getElementById('event-input-location').value = '';
    document.getElementById('event-collision-alert').style.display = 'none';
    document.getElementById('event-form-error').classList.remove('visible');

    openModal('event-modal');
  },

  openEditEventModal(id) {
    const ev = store.getEvent(id);
    if (!ev) return;

    document.getElementById('event-edit-id').value = ev.id;
    document.getElementById('event-modal-title').textContent = 'Chỉnh sửa sự kiện';
    document.getElementById('event-input-title').value = ev.title;
    document.getElementById('event-input-start-date').value = ev.startDate;
    document.getElementById('event-input-end-date').value = ev.endDate || ev.startDate;
    document.getElementById('event-input-start-time').value = ev.startTime || '09:00';
    document.getElementById('event-input-end-time').value = ev.endTime || '10:00';
    document.getElementById('event-input-allday').checked = !!ev.allDay;
    document.getElementById('event-input-location').value = ev.location || '';
    document.getElementById('event-input-category').value = ev.category || 'cat-work';
    document.getElementById('event-collision-alert').style.display = 'none';
    document.getElementById('event-form-error').classList.remove('visible');

    openModal('event-modal');
  },

  checkEventCollisionLive() {
    const editId = document.getElementById('event-edit-id').value;
    const startDate = document.getElementById('event-input-start-date').value;
    const startTime = document.getElementById('event-input-start-time').value;
    const endTime = document.getElementById('event-input-end-time').value;
    const allDay = document.getElementById('event-input-allday').checked;
    const alertBox = document.getElementById('event-collision-alert');

    if (!allDay && startDate && startTime && endTime) {
      const hasCollision = store.checkEventCollision(startDate, startTime, endTime, editId);
      alertBox.style.display = hasCollision ? 'flex' : 'none';
    } else {
      alertBox.style.display = 'none';
    }
  },

  saveEventFromModal() {
    const id = document.getElementById('event-edit-id').value;
    const titleInput = document.getElementById('event-input-title');
    const errorEl = document.getElementById('event-form-error');
    const title = titleInput.value.trim();

    if (!title) {
      errorEl.textContent = 'Vui lòng nhập tên sự kiện';
      errorEl.classList.add('visible');
      titleInput.focus();
      return;
    }

    const startDate = document.getElementById('event-input-start-date').value;
    const endDate = document.getElementById('event-input-end-date').value || startDate;
    const allDay = document.getElementById('event-input-allday').checked;
    const startTime = allDay ? '' : document.getElementById('event-input-start-time').value;
    const endTime = allDay ? '' : document.getElementById('event-input-end-time').value;
    const location = document.getElementById('event-input-location').value;
    const category = document.getElementById('event-input-category').value;

    if (id) {
      store.updateEvent(id, { title, startDate, endDate, allDay, startTime, endTime, location, category });
      showToast(`Đã lưu sự kiện: "${title}"`);
    } else {
      store.addEvent({ title, startDate, endDate, allDay, startTime, endTime, location, category });
      showToast(`Đã thêm sự kiện: "${title}"`);
    }

    closeModal('event-modal');
    renderCurrentScreen();
  },

  deleteEvent(id) {
    store.deleteEvent(id);
    renderCurrentScreen();
  },

  // Categories Handlers
  openAddCategoryModal() {
    const name = prompt('Nhập tên danh mục mới:');
    if (!name || !name.trim()) return;
    const colors = ['#2563eb', '#7c3aed', '#0d9488', '#059669', '#d97706', '#dc2626'];
    const chosenColor = colors[Math.floor(Math.random() * colors.length)];
    store.addCategory({ name: name.trim(), color: chosenColor });
    showToast(`Đã thêm danh mục "${name.trim()}"`);
    renderCurrentScreen();
  },

  editCategory(id) {
    const cat = store.getCategories().find(c => c.id === id);
    if (!cat) return;
    const newName = prompt('Đổi tên danh mục:', cat.name);
    if (!newName || !newName.trim()) return;
    store.updateCategory(id, { name: newName.trim() });
    showToast(`Đã cập nhật danh mục "${newName.trim()}"`);
    renderCurrentScreen();
  },

  deleteCategory(id) {
    const cat = store.getCategories().find(c => c.id === id);
    if (!cat) return;
    if (confirm(`Bạn có chắc muốn xóa danh mục "${cat.name}"?`)) {
      store.deleteCategory(id);
      showToast(`Đã xóa danh mục "${cat.name}"`);
      renderCurrentScreen();
    }
  },

  // Notification actions
  snoozeNotif(id, minutes) {
    store.snoozeNotification(id, minutes);
    showToast(`Đã hoãn nhắc nhở ${minutes >= 1440 ? '1 ngày' : minutes + ' phút'}`);
    renderCurrentScreen();
  },

  dismissNotif(id) {
    store.dismissNotification(id);
    renderCurrentScreen();
  },

  // Trash actions
  restoreTrash(id) {
    store.restoreTrashItem(id);
    showToast('Đã khôi phục mục đã chọn');
    renderCurrentScreen();
  },

  emptyTrash() {
    if (confirm('Dọn sạch thùng rác vĩnh viễn?')) {
      store.emptyTrash();
      showToast('Thùng rác đã được dọn sạch');
      renderCurrentScreen();
    }
  },

  // Settings & Theme
  toggleTheme(themeName) {
    if (themeName === 'auto') {
      document.documentElement.removeAttribute('data-theme');
    } else {
      document.documentElement.setAttribute('data-theme', themeName);
    }
    showToast(`Đã đổi giao diện sang chế độ: ${themeName === 'dark' ? 'Tối' : themeName === 'light' ? 'Sáng' : 'Tự động'}`);
  },

  exportDataMock() {
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(store.state, null, 2));
    const downloadAnchor = document.createElement('a');
    downloadAnchor.setAttribute("href", dataStr);
    downloadAnchor.setAttribute("download", `lich-todo-backup-${new Date().toISOString().split('T')[0]}.json`);
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    downloadAnchor.remove();
    showToast('Đã xuất dữ liệu thành công!');
  },

  closeModal(modalId) {
    closeModal(modalId);
  },

  refreshScreen() {
    renderCurrentScreen();
  }
};

// Listen for store notifications (undo, toast, updates)
store.subscribe((event, data) => {
  if (event === 'undo_available') {
    showToast(data.description, 'Hoàn tác', () => {
      store.performUndo();
    });
  } else if (event === 'undo_completed') {
    showToast(`Đã hoàn tác: ${data.description}`);
    renderCurrentScreen();
  }
});

// ROUTING & SCREEN RENDERING
const mainContent = document.getElementById('main-content');

export function renderCurrentScreen() {
  const hash = window.location.hash || '#/hom-nay';
  const [baseRoute] = hash.split('?');
  const demoState = store.getDemoState();

  // If in demo loading, empty or error state, render standard shells
  if (demoState === 'loading') {
    mainContent.innerHTML = renderSkeletonState(5);
    return;
  }
  if (demoState === 'empty') {
    mainContent.innerHTML = renderEmptyState('Chưa có công việc nào', 'Danh sách của bạn đang trống. Hãy thêm một công việc để bắt đầu ngày mới hiệu quả!');
    return;
  }
  if (demoState === 'error') {
    mainContent.innerHTML = renderErrorState('Không thể kết nối với bộ nhớ dữ liệu hoặc có lỗi xảy ra.', 'refreshScreen');
    return;
  }

  // Render normal screen
  switch (baseRoute) {
    case '#/hom-nay':
      renderTodayScreen();
      break;
    case '#/lich':
      renderCalendarScreen();
      break;
    case '#/viec':
      renderTasksScreen();
      break;
    case '#/thong-ke':
      renderStatsScreen();
      break;
    case '#/cai-dat':
      renderSettingsScreen();
      break;
    case '#/tim-kiem':
      renderSearchScreen();
      break;
    case '#/danh-muc':
      renderCategoriesScreen();
      break;
    case '#/thong-bao':
      renderNotificationsScreen();
      break;
    case '#/thung-rac':
      renderTrashScreen();
      break;
    case '#/auth':
      renderAuthScreen();
      break;
    default:
      renderTodayScreen();
  }
}

// 1. MÀN HÌNH HÔM NAY
function renderTodayScreen() {
  const tasks = store.getTasks();
  const events = store.getEvents();
  const categories = store.getCategories();
  const now = new Date();
  const todayStr = now.toISOString().split('T')[0];

  const weekdayNames = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
  const formattedTodayDate = `${weekdayNames[now.getDay()]}, ${formatDateVN(todayStr)}`;

  // Filter tasks
  const overdueTasks = tasks.filter(t => !t.completed && isDateOverdue(t.dueDate));
  const todayTasks = tasks.filter(t => t.dueDate === todayStr);
  const completedToday = todayTasks.filter(t => t.completed).length;
  const totalToday = todayTasks.length;
  const progressPercent = totalToday > 0 ? Math.round((completedToday / totalToday) * 100) : 0;

  // Timed items for today's timeline (events + time-bound tasks)
  const todayEvents = events.filter(e => e.startDate === todayStr);
  const timedTasks = todayTasks.filter(t => !!t.dueTime);
  const nonTimedTasks = todayTasks.filter(t => !t.dueTime);

  // Merge into timeline sorted by time
  const timelineItems = [
    ...todayEvents.map(e => ({ type: 'event', time: e.allDay ? 'Cả ngày' : (e.startTime || '00:00'), rawTime: e.startTime || '00:00', data: e })),
    ...timedTasks.map(t => ({ type: 'task', time: t.dueTime, rawTime: t.dueTime, data: t }))
  ].sort((a, b) => a.rawTime.localeCompare(b.rawTime));

  mainContent.innerHTML = `
    <div class="screen-container">
      <div class="today-summary-card">
        <h1 class="today-greeting">Xin chào, Nguyễn Văn Minh 👋</h1>
        <div class="today-date">${formattedTodayDate}</div>
        <div class="progress-container">
          <div style="display:flex;justify-content:space-between;font-size:14px;font-weight:600;">
            <span>Tiến độ công việc hôm nay</span>
            <span style="color:var(--primary);">${completedToday}/${totalToday} việc (${progressPercent}%)</span>
          </div>
          <div class="progress-bar" role="progressbar" aria-valuenow="${progressPercent}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-fill" style="width: ${progressPercent}%;"></div>
          </div>
        </div>
      </div>

      <!-- Quá hạn -->
      ${overdueTasks.length > 0 ? `
        <div class="overdue-block">
          <div class="section-title text-danger" style="margin-bottom:8px;">
            <span>${icon('alert-circle')} Việc quá hạn (${overdueTasks.length})</span>
          </div>
          <div>
            ${overdueTasks.map(t => renderTaskRow(t, categories)).join('')}
          </div>
        </div>
      ` : ''}

      <!-- Timeline hôm nay -->
      <div class="section-block">
        <div class="section-title">
          <span>${icon('clock')} Dòng thời gian hôm nay</span>
          <button type="button" class="btn btn-ghost btn-sm" onclick="window.appActions.openAddEventModal('${todayStr}')">
            ${icon('plus', 'icon-sm')} Thêm sự kiện
          </button>
        </div>
        ${timelineItems.length === 0 ? `
          <div class="card" style="text-align:center;padding:24px;color:var(--text-muted);">
            Không có lịch hẹn hay việc có giờ cố định hôm nay.
          </div>
        ` : `
          <div class="timeline-list">
            ${timelineItems.map(item => {
              if (item.type === 'event') {
                const ev = item.data;
                const cat = categories.find(c => c.id === ev.category) || {};
                return `
                  <div class="timeline-item">
                    <div class="timeline-time">${item.time}</div>
                    <div class="timeline-card" style="border-left-color:${ev.color || cat.color || 'var(--primary)'};">
                      <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                        <div>
                          <div class="font-semibold" style="font-size:15px;">${ev.title}</div>
                          ${ev.location ? `<div class="text-muted" style="font-size:12px;display:flex;align-items:center;gap:4px;margin-top:2px;">${icon('map-pin', 'icon-sm')} ${ev.location}</div>` : ''}
                        </div>
                        <span class="badge" style="background-color:${cat.bg || 'var(--bg-subtle)'};color:${cat.color || 'inherit'};">${cat.name || 'Sự kiện'}</span>
                      </div>
                    </div>
                  </div>
                `;
              } else {
                const task = item.data;
                return `
                  <div class="timeline-item">
                    <div class="timeline-time">${item.time}</div>
                    <div style="flex:1;">
                      ${renderTaskRow(task, categories)}
                    </div>
                  </div>
                `;
              }
            }).join('')}
          </div>
        `}
      </div>

      <!-- Việc không giờ -->
      <div class="section-block">
        <div class="section-title">
          <span>${icon('check-square')} Việc cần làm hôm nay (không gắn giờ)</span>
        </div>
        ${nonTimedTasks.length === 0 ? `
          <div class="card" style="text-align:center;padding:20px;color:var(--text-muted);font-size:14px;">
            Đã hết việc không giờ trong ngày.
          </div>
        ` : `
          <div>
            ${nonTimedTasks.map(t => renderTaskRow(t, categories)).join('')}
          </div>
        `}
      </div>
    </div>
  `;
}

// 2. MÀN HÌNH LỊCH
function renderCalendarScreen() {
  const events = store.getEvents();
  const tasks = store.getTasks();
  const categories = store.getCategories();

  // Filter items by category
  const filteredEvents = calendarCategoryFilter === 'all' ? events : events.filter(e => e.category === calendarCategoryFilter);
  const filteredTasks = calendarCategoryFilter === 'all' ? tasks : tasks.filter(t => t.category === calendarCategoryFilter);

  mainContent.innerHTML = `
    <div class="screen-container">
      <div class="calendar-view-header">
        <div class="screen-title" style="margin:0;">Lịch biểu</div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <!-- Category Filter -->
          <select class="form-select" style="min-height:36px;padding:4px 8px;font-size:13px;" onchange="calendarCategoryFilter = this.value; renderCurrentScreen();">
            <option value="all">Tất cả danh mục</option>
            ${categories.map(c => `<option value="${c.id}" ${calendarCategoryFilter === c.id ? 'selected' : ''}>${c.name}</option>`).join('')}
          </select>

          <!-- View Switcher -->
          <div class="segmented-control">
            <button type="button" class="segmented-btn ${currentCalendarView === 'month' ? 'active' : ''}" onclick="currentCalendarView = 'month'; renderCurrentScreen();">Tháng</button>
            <button type="button" class="segmented-btn ${currentCalendarView === 'week' ? 'active' : ''}" onclick="currentCalendarView = 'week'; renderCurrentScreen();">Tuần</button>
            <button type="button" class="segmented-btn ${currentCalendarView === 'day' ? 'active' : ''}" onclick="currentCalendarView = 'day'; renderCurrentScreen();">Ngày</button>
            <button type="button" class="segmented-btn ${currentCalendarView === 'list' ? 'active' : ''}" onclick="currentCalendarView = 'list'; renderCurrentScreen();">Danh sách</button>
          </div>

          <button type="button" class="btn btn-secondary" style="min-height:36px;padding:0 12px;" onclick="selectedCalendarDate = new Date().toISOString().split('T')[0]; renderCurrentScreen();">
            Hôm nay
          </button>
        </div>
      </div>

      ${currentCalendarView === 'month' ? renderMonthView(filteredEvents, filteredTasks, categories) : ''}
      ${currentCalendarView === 'week' ? renderWeekView(filteredEvents, filteredTasks, categories) : ''}
      ${currentCalendarView === 'day' ? renderDayView(filteredEvents, filteredTasks, categories) : ''}
      ${currentCalendarView === 'list' ? renderCalendarListView(filteredEvents, filteredTasks, categories) : ''}
    </div>
  `;
}

function renderMonthView(events, tasks, categories) {
  const selDate = new Date(selectedCalendarDate);
  const year = selDate.getFullYear();
  const month = selDate.getMonth();

  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);

  // Monday is start of week: 0 -> Sun, 1 -> Mon...
  let startOffset = firstDay.getDay() - 1;
  if (startOffset < 0) startOffset = 6;

  const daysInMonth = lastDay.getDate();
  const todayStr = new Date().toISOString().split('T')[0];

  let cellsHtml = '';
  // Leading empty/prev month days
  for (let i = 0; i < startOffset; i++) {
    cellsHtml += `<div class="calendar-day-cell other-month"></div>`;
  }

  for (let d = 1; d <= daysInMonth; d++) {
    const dStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    const isSelected = dStr === selectedCalendarDate;
    const isToday = dStr === todayStr;

    // Count events and tasks for dots
    const dayEvs = events.filter(e => e.startDate === dStr);
    const dayTasks = tasks.filter(t => t.dueDate === dStr && !t.completed);

    let dotsHtml = '';
    dayEvs.slice(0, 3).forEach(ev => {
      dotsHtml += `<span class="day-dot" style="background-color:${ev.color || 'var(--primary)'};"></span>`;
    });
    dayTasks.slice(0, 3).forEach(t => {
      const cat = categories.find(c => c.id === t.category);
      dotsHtml += `<span class="day-dot" style="background-color:${cat ? cat.color : 'var(--text-muted)'};"></span>`;
    });

    cellsHtml += `
      <div class="calendar-day-cell ${isSelected ? 'selected' : ''} ${isToday ? 'is-today' : ''}" onclick="selectedCalendarDate = '${dStr}'; renderCurrentScreen();">
        <span class="day-number">${d}</span>
        <div class="day-dots">${dotsHtml}</div>
      </div>
    `;
  }

  // Selected date details
  const selDayEvs = events.filter(e => e.startDate === selectedCalendarDate);
  const selDayTasks = tasks.filter(t => t.dueDate === selectedCalendarDate);

  return `
    <div style="margin-bottom:12px;font-weight:700;font-size:18px;">
      Tháng ${month + 1}/${year}
    </div>

    <div class="calendar-month-grid">
      <div class="calendar-weekday">T2</div>
      <div class="calendar-weekday">T3</div>
      <div class="calendar-weekday">T4</div>
      <div class="calendar-weekday">T5</div>
      <div class="calendar-weekday">T6</div>
      <div class="calendar-weekday">T7</div>
      <div class="calendar-weekday">CN</div>
      ${cellsHtml}
    </div>

    <!-- Selected Date Details Below -->
    <div class="card" style="margin-top:20px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 class="font-bold">Hoạt động ngày ${formatDateVN(selectedCalendarDate)}</h3>
        <div style="display:flex;gap:8px;">
          <button type="button" class="btn btn-secondary btn-sm" onclick="window.appActions.openAddEventModal('${selectedCalendarDate}')">
            ${icon('plus', 'icon-sm')} Sự kiện
          </button>
          <button type="button" class="btn btn-primary btn-sm" onclick="window.appActions.openAddTaskModal('${selectedCalendarDate}')">
            ${icon('plus', 'icon-sm')} Việc
          </button>
        </div>
      </div>

      ${selDayEvs.length === 0 && selDayTasks.length === 0 ? `
        <div class="text-muted" style="text-align:center;padding:24px;">Không có công việc hoặc sự kiện nào trong ngày này.</div>
      ` : `
        <div style="display:flex;flex-direction:column;gap:12px;">
          ${selDayEvs.map(e => `
            <div style="border-left:4px solid ${e.color || 'var(--primary)'};padding:8px 12px;background:var(--bg-subtle);border-radius:4px;display:flex;justify-content:space-between;align-items:center;">
              <div>
                <div class="font-semibold">${e.title}</div>
                <div class="text-muted" style="font-size:12px;">${e.allDay ? 'Cả ngày' : `${e.startTime} - ${e.endTime}`} ${e.location ? `• ${e.location}` : ''}</div>
              </div>
              <button type="button" class="btn btn-icon btn-ghost" onclick="window.appActions.openEditEventModal('${e.id}')">
                ${icon('edit', 'icon-sm')}
              </button>
            </div>
          `).join('')}

          ${selDayTasks.map(t => renderTaskRow(t, categories)).join('')}
        </div>
      `}
    </div>
  `;
}

function renderDayView(events, tasks, categories) {
  const dayEvs = events.filter(e => e.startDate === selectedCalendarDate);
  const now = new Date();
  const currentMinutes = now.getHours() * 60 + now.getMinutes();
  const isToday = selectedCalendarDate === now.toISOString().split('T')[0];

  let hoursHtml = '';
  for (let h = 0; h < 24; h++) {
    const hh = String(h).padStart(2, '0') + ':00';
    // Find events matching this hour
    const matchingEvs = dayEvs.filter(e => !e.allDay && e.startTime && e.startTime.startsWith(String(h).padStart(2, '0')));

    hoursHtml += `
      <div class="hourly-row" data-hour="${h}">
        <div class="hourly-time-label">${hh}</div>
        <div class="hourly-content-slot" onclick="window.appActions.openAddEventModal('${selectedCalendarDate}', '${hh}')">
          ${matchingEvs.map(ev => `
            <div class="event-block" draggable="true" ondragstart="event.dataTransfer.setData('text/plain', '${ev.id}')" onclick="event.stopPropagation(); window.appActions.openEditEventModal('${ev.id}')" style="top:4px;border-left-color:${ev.color || 'var(--primary)'};background:var(--primary-light);">
              <div class="font-semibold">${ev.title}</div>
              <div style="color:var(--text-muted);font-size:11px;">${ev.startTime} - ${ev.endTime}</div>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }

  return `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 class="font-bold">Lưới giờ: ${formatDateVN(selectedCalendarDate)}</h3>
      <button type="button" class="btn btn-primary" onclick="window.appActions.openAddEventModal('${selectedCalendarDate}')">
        ${icon('plus')} Thêm sự kiện
      </button>
    </div>

    <div class="hourly-grid-container" id="hourly-grid">
      ${isToday ? `<div class="now-indicator-line" style="top: ${currentMinutes}px;"></div>` : ''}
      ${hoursHtml}
    </div>
  `;
}

function renderWeekView(events, tasks, categories) {
  return renderDayView(events, tasks, categories); // Fallback seamless view for week/day
}

function renderCalendarListView(events, tasks, categories) {
  const combined = [
    ...events.map(e => ({ type: 'event', date: e.startDate, title: e.title, time: e.allDay ? 'Cả ngày' : `${e.startTime} - ${e.endTime}`, raw: e })),
    ...tasks.filter(t => t.dueDate).map(t => ({ type: 'task', date: t.dueDate, title: t.title, time: t.dueTime || 'Không giờ', raw: t }))
  ].sort((a, b) => a.date.localeCompare(b.date));

  return `
    <div class="card">
      <h3 class="font-bold" style="margin-bottom:16px;">Danh sách tất cả lịch trình & hạn công việc</h3>
      <div style="display:flex;flex-direction:column;gap:8px;">
        ${combined.map(item => `
          <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border-bottom:1px solid var(--border-subtle);">
            <div>
              <div class="font-semibold">${item.title}</div>
              <div class="text-muted" style="font-size:12px;">${formatDateVN(item.date)} • ${item.time}</div>
            </div>
            <span class="badge ${item.type === 'event' ? 'badge-priority-low' : 'badge-cat'}">
              ${item.type === 'event' ? 'Sự kiện' : 'Công việc'}
            </span>
          </div>
        `).join('')}
      </div>
    </div>
  `;
}

// 3. MÀN HÌNH DANH SÁCH VIỆC
function renderTasksScreen() {
  const allTasks = store.getTasks();
  const categories = store.getCategories();
  const todayStr = new Date().toISOString().split('T')[0];

  // Apply Search & Category filters
  let filtered = allTasks.filter(t => {
    if (taskCategoryFilter !== 'all' && t.category !== taskCategoryFilter) return false;
    if (taskSearchQuery) {
      const q = taskSearchQuery.toLowerCase();
      return t.title.toLowerCase().includes(q) || (t.notes && t.notes.toLowerCase().includes(q));
    }
    return true;
  });

  // Apply Sorting
  filtered.sort((a, b) => {
    if (taskSortBy === 'dueDate') {
      if (!a.dueDate) return 1;
      if (!b.dueDate) return -1;
      return a.dueDate.localeCompare(b.dueDate);
    } else if (taskSortBy === 'priority') {
      const pMap = { high: 3, medium: 2, low: 1 };
      return pMap[b.priority] - pMap[a.priority];
    } else if (taskSortBy === 'title') {
      return a.title.localeCompare(b.title);
    }
    return 0;
  });

  // Groups
  const overdueGroup = filtered.filter(t => !t.completed && isDateOverdue(t.dueDate));
  const todayGroup = filtered.filter(t => !t.completed && t.dueDate === todayStr);
  const upcomingGroup = filtered.filter(t => !t.completed && t.dueDate > todayStr);
  const noDateGroup = filtered.filter(t => !t.completed && !t.dueDate);
  const completedGroup = filtered.filter(t => t.completed);

  mainContent.innerHTML = `
    <div class="screen-container">
      <div class="screen-header">
        <div class="screen-title">
          <span>Danh sách công việc</span>
          <button type="button" class="btn btn-primary" onclick="window.appActions.openAddTaskModal()">
            ${icon('plus')} Thêm việc
          </button>
        </div>
      </div>

      <!-- Search, Filter & Sort Controls -->
      <div class="card" style="margin-bottom:20px;padding:12px;">
        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
          <div style="flex:1;min-width:200px;position:relative;">
            <input type="text" class="form-input" placeholder="Tìm kiếm công việc..." value="${taskSearchQuery}" oninput="taskSearchQuery = this.value; renderTasksScreen();" style="width:100%;padding-left:36px;">
            <div style="position:absolute;left:10px;top:10px;color:var(--text-muted);pointer-events:none;">
              ${icon('search', 'icon-sm')}
            </div>
          </div>

          <select class="form-select" onchange="taskCategoryFilter = this.value; renderTasksScreen();" style="width:auto;">
            <option value="all">Tất cả danh mục</option>
            ${categories.map(c => `<option value="${c.id}" ${taskCategoryFilter === c.id ? 'selected' : ''}>${c.name}</option>`).join('')}
          </select>

          <select class="form-select" onchange="taskSortBy = this.value; renderTasksScreen();" style="width:auto;">
            <option value="dueDate" ${taskSortBy === 'dueDate' ? 'selected' : ''}>Sắp xếp: Ngày hạn</option>
            <option value="priority" ${taskSortBy === 'priority' ? 'selected' : ''}>Sắp xếp: Mức ưu tiên</option>
            <option value="title" ${taskSortBy === 'title' ? 'selected' : ''}>Sắp xếp: Tên A-Z</option>
          </select>
        </div>
      </div>

      <!-- Task Groups -->
      <!-- 1. Quá hạn -->
      ${overdueGroup.length > 0 ? `
        <div class="task-group">
          <div class="task-group-header text-danger">
            <span>${icon('alert-circle', 'icon-sm')} Quá hạn (${overdueGroup.length})</span>
          </div>
          <div>${overdueGroup.map(t => renderTaskRow(t, categories)).join('')}</div>
        </div>
      ` : ''}

      <!-- 2. Hôm nay -->
      <div class="task-group">
        <div class="task-group-header">
          <span>${icon('calendar-day', 'icon-sm')} Hôm nay (${todayGroup.length})</span>
        </div>
        ${todayGroup.length === 0 ? `<div class="text-muted" style="font-size:13px;padding:8px 0;">Không có việc nào trong hôm nay.</div>` : `<div>${todayGroup.map(t => renderTaskRow(t, categories)).join('')}</div>`}
      </div>

      <!-- 3. Sắp tới -->
      <div class="task-group">
        <div class="task-group-header">
          <span>${icon('calendar', 'icon-sm')} Sắp tới (${upcomingGroup.length})</span>
        </div>
        ${upcomingGroup.length === 0 ? `<div class="text-muted" style="font-size:13px;padding:8px 0;">Không có việc sắp tới.</div>` : `<div>${upcomingGroup.map(t => renderTaskRow(t, categories)).join('')}</div>`}
      </div>

      <!-- 4. Không hạn -->
      <div class="task-group">
        <div class="task-group-header">
          <span>${icon('clock', 'icon-sm')} Không hạn (${noDateGroup.length})</span>
        </div>
        ${noDateGroup.length === 0 ? `<div class="text-muted" style="font-size:13px;padding:8px 0;">Không có việc không hạn.</div>` : `<div>${noDateGroup.map(t => renderTaskRow(t, categories)).join('')}</div>`}
      </div>

      <!-- 5. Đã xong (Collapsible) -->
      <div class="task-group">
        <div class="task-group-header" onclick="completedGroupCollapsed = !completedGroupCollapsed; renderTasksScreen();">
          <span style="display:flex;align-items:center;gap:6px;">
            ${icon(completedGroupCollapsed ? 'chevron-right' : 'chevron-down', 'icon-sm')}
            Đã hoàn thành (${completedGroup.length})
          </span>
          <span style="font-size:12px;font-weight:normal;color:var(--text-muted);">${completedGroupCollapsed ? 'Bấm để mở' : 'Thu gọn'}</span>
        </div>
        ${!completedGroupCollapsed ? `<div>${completedGroup.map(t => renderTaskRow(t, categories)).join('')}</div>` : ''}
      </div>
    </div>
  `;
}

// Reusable Task Row Renderer
function renderTaskRow(task, categories) {
  const cat = categories.find(c => c.id === task.category) || {};
  const isOverdue = isDateOverdue(task.dueDate) && !task.completed;
  const subtasksCount = task.subtasks ? task.subtasks.length : 0;
  const completedSubtasks = task.subtasks ? task.subtasks.filter(s => s.completed).length : 0;

  return `
    <div class="task-item-row ${task.completed ? 'completed' : ''}" id="row-${task.id}">
      <button type="button" class="custom-checkbox ${task.completed ? 'checked' : ''}" onclick="window.appActions.toggleTask('${task.id}')" aria-label="Hoàn thành việc ${task.title}">
        ${task.completed ? icon('check', 'icon-sm') : ''}
      </button>

      <div class="task-main" onclick="window.appActions.openTaskDetailModal('${task.id}')">
        <div class="task-title">${task.title}</div>
        <div class="task-meta">
          ${task.dueDate ? `
            <span style="${isOverdue ? 'color:var(--danger);font-weight:600;' : ''}">
              ${icon('clock', 'icon-sm')} ${formatDateVN(task.dueDate)} ${task.dueTime || ''}
            </span>
          ` : ''}
          ${cat.name ? `
            <span class="badge" style="background-color:${cat.bg || 'var(--bg-subtle)'};color:${cat.color};">
              <span class="badge-dot" style="background-color:${cat.color};"></span>
              ${cat.name}
            </span>
          ` : ''}
          <span class="badge badge-priority-${task.priority}">
            ${icon('flag', 'icon-sm')} ${task.priority === 'high' ? 'Cao' : task.priority === 'medium' ? 'TB' : 'Thấp'}
          </span>
          ${subtasksCount > 0 ? `
            <span class="badge badge-cat">
              ${icon('check-square', 'icon-sm')} ${completedSubtasks}/${subtasksCount}
            </span>
          ` : ''}
          ${task.recurrence && task.recurrence !== 'none' ? `
            <span>${icon('repeat', 'icon-sm')}</span>
          ` : ''}
        </div>
      </div>

      <div class="task-actions">
        <button type="button" class="btn btn-icon btn-ghost" onclick="window.appActions.postponeTask('${task.id}')" title="Dời sang ngày mai" aria-label="Dời sang ngày mai">
          ${icon('clock', 'icon-sm')}
        </button>
        <button type="button" class="btn btn-icon btn-ghost" onclick="window.appActions.openEditTaskModal('${task.id}')" title="Chỉnh sửa" aria-label="Chỉnh sửa">
          ${icon('edit', 'icon-sm')}
        </button>
        <button type="button" class="btn btn-icon btn-ghost text-danger" onclick="window.appActions.deleteTask('${task.id}')" title="Xóa" aria-label="Xóa">
          ${icon('trash', 'icon-sm')}
        </button>
      </div>
    </div>
  `;
}

// 7. MÀN HÌNH DANH MỤC & NHÃN
function renderCategoriesScreen() {
  const categories = store.getCategories();
  const tasks = store.getTasks();

  mainContent.innerHTML = `
    <div class="screen-container">
      <div class="screen-header">
        <div class="screen-title">
          <span>Danh mục & Nhãn</span>
          <button type="button" class="btn btn-primary" onclick="window.appActions.openAddCategoryModal()">
            ${icon('plus')} Thêm danh mục
          </button>
        </div>
      </div>

      <div class="cat-grid">
        ${categories.map(cat => {
          const count = tasks.filter(t => t.category === cat.id && !t.completed).length;
          return `
            <div class="cat-card">
              <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:36px;height:36px;border-radius:var(--radius-md);background-color:${cat.bg};color:${cat.color};display:flex;align-items:center;justify-content:center;">
                  ${icon('folder')}
                </div>
                <div>
                  <div class="font-bold" style="font-size:16px;">${cat.name}</div>
                  <div class="text-muted" style="font-size:13px;">${count} công việc chưa xong</div>
                </div>
              </div>
              <div style="display:flex;gap:4px;">
                <button type="button" class="btn btn-icon btn-ghost" onclick="window.appActions.editCategory('${cat.id}')" aria-label="Sửa danh mục">
                  ${icon('edit', 'icon-sm')}
                </button>
                <button type="button" class="btn btn-icon btn-ghost text-danger" onclick="window.appActions.deleteCategory('${cat.id}')" aria-label="Xóa danh mục">
                  ${icon('trash', 'icon-sm')}
                </button>
              </div>
            </div>
          `;
        }).join('')}
      </div>
    </div>
  `;
}

// 8. MÀN HÌNH THÔNG BÁO
function renderNotificationsScreen() {
  const notifs = store.getNotifications();

  mainContent.innerHTML = `
    <div class="screen-container">
      <div class="screen-header">
        <div class="screen-title">Trung tâm thông báo & nhắc việc</div>
      </div>

      ${notifs.length === 0 ? `
        <div class="card state-empty">
          <div class="state-empty-icon">${icon('bell', 'icon-lg')}</div>
          <h2 class="state-empty-title">Không có nhắc nhở nào</h2>
          <p class="state-empty-desc">Bạn đã hoàn thành tốt và không có việc nào bị trễ hẹn.</p>
        </div>
      ` : `
        <div style="display:flex;flex-direction:column;gap:12px;">
          ${notifs.map(n => `
            <div class="notification-card" style="border-left:4px solid ${n.type === 'overdue' ? 'var(--danger)' : n.type === 'collision' ? 'var(--warning)' : 'var(--primary)'};">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div class="font-bold" style="font-size:15px;">${n.title}</div>
                <button type="button" class="btn btn-icon btn-ghost" onclick="window.appActions.dismissNotif('${n.id}')" aria-label="Bỏ qua">
                  ${icon('x', 'icon-sm')}
                </button>
              </div>
              <div class="text-secondary" style="font-size:13px;">${n.body}</div>
              <div class="text-muted" style="font-size:11px;">Thời gian: ${n.time}</div>

              <div class="notification-actions" style="margin-top:8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.appActions.snoozeNotif('${n.id}', 10)">
                  Hoãn 10 phút
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.appActions.snoozeNotif('${n.id}', 60)">
                  Hoãn 1 giờ
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.appActions.snoozeNotif('${n.id}', 1440)">
                  Hoãn sang mai
                </button>
              </div>
            </div>
          `).join('')}
        </div>
      `}
    </div>
  `;
}

// 9. MÀN HÌNH THỐNG KÊ
function renderStatsScreen() {
  const tasks = store.getTasks();
  const completed = tasks.filter(t => t.completed).length;
  const total = tasks.length;
  const rate = total > 0 ? Math.round((completed / total) * 100) : 0;
  const categories = store.getCategories();

  // Draw custom SVG Weekly completion Bar Chart
  const days = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
  const values = [4, 6, 3, 7, 5, 8, 2]; // Mock completion counts across week
  const maxVal = Math.max(...values);
  const chartHeight = 140;
  const barWidth = 28;

  const barsSvg = values.map((val, idx) => {
    const x = idx * 46 + 18;
    const h = (val / maxVal) * (chartHeight - 40);
    const y = chartHeight - 20 - h;
    return `
      <rect x="${x}" y="${y}" width="${barWidth}" height="${h}" rx="4" fill="var(--primary)" opacity="0.85"></rect>
      <text x="${x + barWidth/2}" y="${y - 6}" font-size="11" font-weight="600" text-anchor="middle" fill="var(--text-secondary)">${val}</text>
      <text x="${x + barWidth/2}" y="${chartHeight}" font-size="12" text-anchor="middle" fill="var(--text-muted)">${days[idx]}</text>
    `;
  }).join('');

  mainContent.innerHTML = `
    <div class="screen-container">
      <div class="screen-header">
        <div class="screen-title">Thống kê & Hiệu suất</div>
      </div>

      <!-- Stat Metrics Row -->
      <div class="stat-summary-row">
        <div class="stat-metric-card">
          <div class="stat-metric-num">${completed}/${total}</div>
          <div class="stat-metric-label">Việc đã xong</div>
        </div>
        <div class="stat-metric-card">
          <div class="stat-metric-num">${rate}%</div>
          <div class="stat-metric-label">Tỷ lệ đúng hạn</div>
        </div>
        <div class="stat-metric-card">
          <div class="stat-metric-num" style="color:var(--success);">7 ngày</div>
          <div class="stat-metric-label">Chuỗi ngày liên tiếp 🔥</div>
        </div>
        <div class="stat-metric-card">
          <div class="stat-metric-num" style="color:var(--accent);">18.5h</div>
          <div class="stat-metric-label">Thời gian tập trung</div>
        </div>
      </div>

      <!-- Weekly Completion Chart -->
      <div class="chart-box">
        <div class="chart-title">Số việc hoàn thành theo ngày trong tuần</div>
        <svg viewBox="0 0 340 160" style="width:100%;max-width:500px;display:block;margin:0 auto;" aria-label="Biểu đồ cột hoàn thành theo tuần">
          ${barsSvg}
        </svg>
      </div>

      <!-- Category Distribution -->
      <div class="chart-box">
        <div class="chart-title">Phân bổ việc theo danh mục</div>
        <div style="display:flex;flex-direction:column;gap:12px;">
          ${categories.map(c => {
            const count = tasks.filter(t => t.category === c.id).length;
            const pct = total > 0 ? Math.round((count / total) * 100) : 0;
            return `
              <div>
                <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:600;margin-bottom:4px;">
                  <span>${c.name}</span>
                  <span style="color:var(--text-muted);">${count} việc (${pct}%)</span>
                </div>
                <div class="progress-bar" style="height:8px;">
                  <div class="progress-fill" style="width:${pct}%;background:${c.color};"></div>
                </div>
              </div>
            `;
          }).join('')}
        </div>
      </div>
    </div>
  `;
}

// 10. TÌM KIẾM TOÀN CỤC
let searchFilterType = 'all'; // 'all' | 'task' | 'event'
function renderSearchScreen() {
  const tasks = store.getTasks();
  const events = store.getEvents();
  const categories = store.getCategories();

  let searchResults = [];
  if (taskSearchQuery.trim()) {
    const q = taskSearchQuery.toLowerCase();
    if (searchFilterType === 'all' || searchFilterType === 'task') {
      tasks.forEach(t => {
        if (t.title.toLowerCase().includes(q) || (t.notes && t.notes.toLowerCase().includes(q))) {
          searchResults.push({ type: 'task', data: t });
        }
      });
    }
    if (searchFilterType === 'all' || searchFilterType === 'event') {
      events.forEach(e => {
        if (e.title.toLowerCase().includes(q) || (e.location && e.location.toLowerCase().includes(q))) {
          searchResults.push({ type: 'event', data: e });
        }
      });
    }
  }

  mainContent.innerHTML = `
    <div class="screen-container">
      <div class="screen-header">
        <div class="screen-title">Tìm kiếm toàn cục</div>
      </div>

      <div class="search-bar-wrap">
        <input type="text" class="form-input search-input-main" placeholder="Tìm theo tên việc, sự kiện, địa điểm, ghi chú..." value="${taskSearchQuery}" oninput="taskSearchQuery = this.value; renderSearchScreen();" autofocus>
        <div class="search-icon-pos">${icon('search')}</div>
      </div>

      <!-- Quick Filter Chips -->
      <div style="display:flex;gap:8px;margin-bottom:20px;">
        <button type="button" class="segmented-btn ${searchFilterType === 'all' ? 'active' : ''}" onclick="searchFilterType = 'all'; renderSearchScreen();">Tất cả (${searchResults.length})</button>
        <button type="button" class="segmented-btn ${searchFilterType === 'task' ? 'active' : ''}" onclick="searchFilterType = 'task'; renderSearchScreen();">Công việc</button>
        <button type="button" class="segmented-btn ${searchFilterType === 'event' ? 'active' : ''}" onclick="searchFilterType = 'event'; renderSearchScreen();">Sự kiện</button>
      </div>

      <div>
        ${!taskSearchQuery.trim() ? `
          <div class="card text-muted" style="text-align:center;padding:40px;">
            Nhập từ khóa phía trên để bắt đầu tìm kiếm tức thì.
          </div>
        ` : searchResults.length === 0 ? `
          <div class="card state-empty">
            <div class="state-empty-icon">${icon('search', 'icon-lg')}</div>
            <h2 class="state-empty-title">Không tìm thấy kết quả</h2>
            <p class="state-empty-desc">Không có công việc hoặc sự kiện nào khớp với "${taskSearchQuery}".</p>
          </div>
        ` : `
          <div style="display:flex;flex-direction:column;gap:8px;">
            ${searchResults.map(item => {
              if (item.type === 'task') return renderTaskRow(item.data, categories);
              const ev = item.data;
              return `
                <div class="card" style="display:flex;justify-content:space-between;align-items:center;" onclick="window.appActions.openEditEventModal('${ev.id}')">
                  <div>
                    <div class="font-bold">${ev.title}</div>
                    <div class="text-muted" style="font-size:12px;">Sự kiện • ${formatDateVN(ev.startDate)} ${ev.allDay ? '(Cả ngày)' : `(${ev.startTime})`} ${ev.location ? `• ${ev.location}` : ''}</div>
                  </div>
                  <span class="badge badge-priority-low">Sự kiện</span>
                </div>
              `;
            }).join('')}
          </div>
        `}
      </div>
    </div>
  `;
}

// 11. MÀN HÌNH CÀI ĐẶT
function renderSettingsScreen() {
  const currentDemoState = store.getDemoState();

  mainContent.innerHTML = `
    <div class="screen-container">
      <div class="screen-header">
        <div class="screen-title">Cài đặt hệ thống</div>
      </div>

      <!-- Hồ sơ cá nhân (giả) -->
      <div class="settings-group">
        <h3 class="font-bold" style="margin-bottom:12px;">Hồ sơ cá nhân</h3>
        <div style="display:flex;align-items:center;gap:16px;">
          <div style="width:54px;height:54px;border-radius:50%;background:var(--primary);color:#ffffff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:20px;">
            NV
          </div>
          <div>
            <div class="font-bold" style="font-size:16px;">Nguyễn Văn Minh</div>
            <div class="text-muted" style="font-size:13px;">minh.nguyen@example.com</div>
          </div>
        </div>
      </div>

      <!-- Trạng thái demo kiểm tra (bắt buộc) -->
      <div class="settings-group">
        <h3 class="font-bold" style="margin-bottom:4px;">Xem trạng thái giao diện demo</h3>
        <p class="text-secondary" style="font-size:13px;margin-bottom:12px;">Chuyển đổi tức thì để kiểm tra 4 trạng thái bắt buộc của giao diện.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button type="button" class="btn ${currentDemoState === 'normal' ? 'btn-primary' : 'btn-secondary'}" onclick="window.appActions.setDemoMode('normal')">
            Bình thường
          </button>
          <button type="button" class="btn ${currentDemoState === 'loading' ? 'btn-primary' : 'btn-secondary'}" onclick="window.appActions.setDemoMode('loading')">
            Loading (Skeleton)
          </button>
          <button type="button" class="btn ${currentDemoState === 'empty' ? 'btn-primary' : 'btn-secondary'}" onclick="window.appActions.setDemoMode('empty')">
            Rỗng (Empty State)
          </button>
          <button type="button" class="btn ${currentDemoState === 'error' ? 'btn-primary' : 'btn-secondary'}" onclick="window.appActions.setDemoMode('error')">
            Lỗi (Error State)
          </button>
        </div>
      </div>

      <!-- Giao diện Sáng/Tối -->
      <div class="settings-group">
        <h3 class="font-bold" style="margin-bottom:12px;">Chủ đề giao diện</h3>
        <div class="settings-item">
          <span>Chế độ hiển thị</span>
          <div style="display:flex;gap:8px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.appActions.toggleTheme('light')">
              ${icon('sun', 'icon-sm')} Sáng
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.appActions.toggleTheme('dark')">
              ${icon('moon', 'icon-sm')} Tối
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.appActions.toggleTheme('auto')">
              Tự động
            </button>
          </div>
        </div>
      </div>

      <!-- Tùy chọn lịch & giờ -->
      <div class="settings-group">
        <h3 class="font-bold" style="margin-bottom:12px;">Tùy chọn thời gian</h3>
        <div class="settings-item">
          <span>Ngày bắt đầu tuần</span>
          <select class="form-select" style="width:auto;">
            <option value="monday" selected>Thứ Hai (Mặc định)</option>
            <option value="sunday">Chủ Nhật</option>
          </select>
        </div>
        <div class="settings-item">
          <span>Định dạng ngày</span>
          <span class="text-muted">dd/mm/yyyy</span>
        </div>
        <div class="settings-item">
          <span>Định dạng giờ</span>
          <span class="text-muted">24 giờ (00:00 - 23:59)</span>
        </div>
        <div class="settings-item">
          <span>Nhắc nhở mặc định</span>
          <select class="form-select" style="width:auto;">
            <option value="15m" selected>Trước 15 phút</option>
            <option value="30m">Trước 30 phút</option>
            <option value="1h">Trước 1 giờ</option>
            <option value="1d">Trước 1 ngày</option>
          </select>
        </div>
      </div>

      <!-- Dữ liệu & Xuất -->
      <div class="settings-group">
        <h3 class="font-bold" style="margin-bottom:12px;">Dữ liệu bộ nhớ</h3>
        <div class="settings-item">
          <div>
            <div class="font-semibold">Xuất dữ liệu dự phòng</div>
            <div class="text-muted" style="font-size:12px;">Tải về file JSON chứa toàn bộ dữ liệu in-memory hiện tại.</div>
          </div>
          <button type="button" class="btn btn-secondary" onclick="window.appActions.exportDataMock()">
            Xuất JSON
          </button>
        </div>
        <div class="settings-item">
          <div>
            <div class="font-semibold">Đặt lại dữ liệu mẫu gốc</div>
            <div class="text-muted" style="font-size:12px;">Xóa mọi chỉnh sửa và nạp lại dữ liệu 25 việc + 10 sự kiện ban đầu.</div>
          </div>
          <button type="button" class="btn btn-danger" onclick="store.resetAll(); window.appActions.refreshScreen(); showToast('Đã khôi phục dữ liệu mẫu gốc!');">
            ${icon('refresh')} Đặt lại
          </button>
        </div>
      </div>
    </div>
  `;
}

// 12. MÀN HÌNH AUTH (ĐĂNG NHẬP / ĐĂNG KÝ / QUÊN MẬT KHẨU)
let authTab = 'login'; // 'login' | 'register' | 'forgot'
function renderAuthScreen() {
  mainContent.innerHTML = `
    <div class="screen-container">
      <div class="auth-container">
        <div class="auth-tabs">
          <button type="button" class="auth-tab-btn ${authTab === 'login' ? 'active' : ''}" onclick="authTab = 'login'; renderAuthScreen();">
            Đăng nhập
          </button>
          <button type="button" class="auth-tab-btn ${authTab === 'register' ? 'active' : ''}" onclick="authTab = 'register'; renderAuthScreen();">
            Đăng ký
          </button>
          <button type="button" class="auth-tab-btn ${authTab === 'forgot' ? 'active' : ''}" onclick="authTab = 'forgot'; renderAuthScreen();">
            Quên MK
          </button>
        </div>

        <div id="auth-form-error" class="form-error" style="margin-bottom:12px;"></div>

        ${authTab === 'login' ? `
          <form onsubmit="handleAuthSubmit(event, 'login')">
            <div class="form-group">
              <label class="form-label" for="auth-email">Email</label>
              <input type="email" id="auth-email" class="form-input" placeholder="ten@vidu.com" value="minh.nguyen@example.com" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="auth-password">Mật khẩu</label>
              <input type="password" id="auth-password" class="form-input" placeholder="••••••••" value="MatKhau123" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:12px;">
              Đăng nhập vào App
            </button>
          </form>
        ` : authTab === 'register' ? `
          <form onsubmit="handleAuthSubmit(event, 'register')">
            <div class="form-group">
              <label class="form-label" for="auth-name">Họ và tên</label>
              <input type="text" id="auth-name" class="form-input" placeholder="Nguyễn Văn A" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="auth-reg-email">Email</label>
              <input type="email" id="auth-reg-email" class="form-input" placeholder="ten@vidu.com" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="auth-reg-password">Mật khẩu</label>
              <input type="password" id="auth-reg-password" class="form-input" placeholder="Tối thiểu 6 ký tự" minlength="6" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:12px;">
              Đăng ký tài khoản
            </button>
          </form>
        ` : `
          <form onsubmit="handleAuthSubmit(event, 'forgot')">
            <div class="form-group">
              <label class="form-label" for="auth-forgot-email">Email nhận lại mật khẩu</label>
              <input type="email" id="auth-forgot-email" class="form-input" placeholder="ten@vidu.com" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:12px;">
              Gửi liên kết đặt lại
            </button>
          </form>
        `}
      </div>
    </div>
  `;
}

window.handleAuthSubmit = function(e, type) {
  e.preventDefault();
  const errorEl = document.getElementById('auth-form-error');
  errorEl.classList.remove('visible');

  // Client-side validation only
  if (type === 'login') {
    showToast('Đăng nhập thành công! Chào mừng bạn trở lại.');
    window.location.hash = '#/hom-nay';
  } else if (type === 'register') {
    showToast('Đăng ký thành công! Đã tự động đăng nhập.');
    window.location.hash = '#/hom-nay';
  } else if (type === 'forgot') {
    showToast('Đã gửi hướng dẫn khôi phục mật khẩu vào hòm thư.');
    authTab = 'login';
    renderAuthScreen();
  }
};

// 13. MÀN HÌNH THÙNG RÁC
function renderTrashScreen() {
  const trashItems = store.getTrash();

  mainContent.innerHTML = `
    <div class="screen-container">
      <div class="screen-header">
        <div class="screen-title">
          <span>Thùng rác (${trashItems.length})</span>
          ${trashItems.length > 0 ? `
            <button type="button" class="btn btn-danger" onclick="window.appActions.emptyTrash()">
              ${icon('trash')} Dọn sạch
            </button>
          ` : ''}
        </div>
      </div>

      ${trashItems.length === 0 ? `
        <div class="card state-empty">
          <div class="state-empty-icon">${icon('trash', 'icon-lg')}</div>
          <h2 class="state-empty-title">Thùng rác trống</h2>
          <p class="state-empty-desc">Không có công việc hoặc sự kiện nào trong thùng rác.</p>
        </div>
      ` : `
        <div style="display:flex;flex-direction:column;gap:8px;">
          ${trashItems.map(item => `
            <div class="card" style="display:flex;justify-content:space-between;align-items:center;">
              <div>
                <div class="font-bold" style="font-size:15px;">${item.title}</div>
                <div class="text-muted" style="font-size:12px;">Đã xóa ngày: ${item.deletedAt}</div>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" onclick="window.appActions.restoreTrash('${item.id}')">
                ${icon('undo', 'icon-sm')} Khôi phục
              </button>
            </div>
          `).join('')}
        </div>
      `}
    </div>
  `;
}

// Router Initialization
export const router = new Router({
  '#/hom-nay': () => renderTodayScreen(),
  '#/lich': () => renderCalendarScreen(),
  '#/viec': () => renderTasksScreen(),
  '#/thong-ke': () => renderStatsScreen(),
  '#/cai-dat': () => renderSettingsScreen(),
  '#/tim-kiem': () => renderSearchScreen(),
  '#/danh-muc': () => renderCategoriesScreen(),
  '#/thong-bao': () => renderNotificationsScreen(),
  '#/thung-rac': () => renderTrashScreen(),
  '#/auth': () => renderAuthScreen()
});

// Global Keyboard Shortcuts
window.addEventListener('keydown', (e) => {
  // 'N' for new task (when not typing in an input)
  if ((e.key === 'n' || e.key === 'N') && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
    e.preventDefault();
    window.appActions.openAddTaskModal();
  }
  // Esc to close any open modal
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.active').forEach(modal => {
      closeModal(modal.id);
    });
  }
});
