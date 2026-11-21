import { createInitialData } from './mock-data.js';

class AppStore {
  constructor() {
    this.initialData = createInitialData();
    this.state = JSON.parse(JSON.stringify(this.initialData));
    this.demoState = 'normal'; // 'normal' | 'loading' | 'empty' | 'error'
    this.subscribers = new Set();
    this.lastUndoAction = null;
    this.undoTimer = null;
  }

  // Subscribe to state changes
  subscribe(callback) {
    this.subscribers.add(callback);
    return () => this.subscribers.delete(callback);
  }

  notify(eventType, payload) {
    for (const callback of this.subscribers) {
      try {
        callback(eventType, payload, this.state);
      } catch (err) {
        console.error('Subscriber error:', err);
      }
    }
  }

  // Demo State Switcher
  setDemoState(mode) {
    this.demoState = mode;
    this.notify('demo_state_change', mode);
  }

  getDemoState() {
    return this.demoState;
  }

  // Reset to initial mock data
  resetAll() {
    this.state = JSON.parse(JSON.stringify(createInitialData()));
    this.notify('state_reset');
  }

  // Undo Mechanism
  setUndoAction(description, undoFn) {
    if (this.undoTimer) clearTimeout(this.undoTimer);
    this.lastUndoAction = { description, undoFn };
    this.undoTimer = setTimeout(() => {
      this.lastUndoAction = null;
      this.notify('undo_expired');
    }, 6000);
    this.notify('undo_available', { description });
  }

  performUndo() {
    if (this.lastUndoAction && typeof this.lastUndoAction.undoFn === 'function') {
      const desc = this.lastUndoAction.description;
      this.lastUndoAction.undoFn();
      this.lastUndoAction = null;
      if (this.undoTimer) clearTimeout(this.undoTimer);
      this.notify('undo_completed', { description: desc });
      return true;
    }
    return false;
  }

  // TASKS API
  getTasks() {
    return this.state.tasks;
  }

  getTask(id) {
    return this.state.tasks.find(t => t.id === id);
  }

  addTask(taskData) {
    const newTask = {
      id: 'task-' + Date.now(),
      title: taskData.title || 'Công việc mới',
      dueDate: taskData.dueDate || '',
      dueTime: taskData.dueTime || '',
      category: taskData.category || 'cat-work',
      priority: taskData.priority || 'medium',
      tags: taskData.tags || [],
      completed: false,
      notes: taskData.notes || '',
      subtasks: taskData.subtasks || [],
      recurrence: taskData.recurrence || 'none',
      reminder: taskData.reminder || 'none'
    };
    this.state.tasks.unshift(newTask);

    this.setUndoAction(`Đã tạo "${newTask.title}"`, () => {
      this.state.tasks = this.state.tasks.filter(t => t.id !== newTask.id);
      this.notify('task_removed', newTask.id);
    });

    this.notify('task_added', newTask);
    return newTask;
  }

  updateTask(id, updates) {
    const idx = this.state.tasks.findIndex(t => t.id === id);
    if (idx === -1) return null;
    const oldCopy = JSON.parse(JSON.stringify(this.state.tasks[idx]));
    this.state.tasks[idx] = { ...this.state.tasks[idx], ...updates };

    this.setUndoAction(`Đã cập nhật "${this.state.tasks[idx].title}"`, () => {
      const curIdx = this.state.tasks.findIndex(t => t.id === id);
      if (curIdx !== -1) {
        this.state.tasks[curIdx] = oldCopy;
        this.notify('task_updated', oldCopy);
      }
    });

    this.notify('task_updated', this.state.tasks[idx]);
    return this.state.tasks[idx];
  }

  toggleTaskCompleted(id) {
    const task = this.getTask(id);
    if (!task) return;
    const prevStatus = task.completed;
    task.completed = !prevStatus;

    this.setUndoAction(`Đã ${task.completed ? 'hoàn thành' : 'mở lại'} "${task.title}"`, () => {
      task.completed = prevStatus;
      this.notify('task_updated', task);
    });

    this.notify('task_completed_toggle', task);
  }

  deleteTask(id) {
    const idx = this.state.tasks.findIndex(t => t.id === id);
    if (idx === -1) return;
    const deletedTask = this.state.tasks[idx];
    this.state.tasks.splice(idx, 1);

    // Add to trash
    const trashItem = {
      id: 'trash-' + Date.now(),
      title: deletedTask.title,
      category: deletedTask.category,
      deletedAt: new Date().toLocaleDateString('vi-VN'),
      originalType: 'task',
      originalData: deletedTask
    };
    this.state.trash.unshift(trashItem);

    this.setUndoAction(`Đã xóa "${deletedTask.title}"`, () => {
      this.state.tasks.splice(idx, 0, deletedTask);
      this.state.trash = this.state.trash.filter(item => item.id !== trashItem.id);
      this.notify('task_restored', deletedTask);
    });

    this.notify('task_deleted', { id, trashItem });
  }

  duplicateTask(id) {
    const task = this.getTask(id);
    if (!task) return;
    const cloned = JSON.parse(JSON.stringify(task));
    cloned.id = 'task-' + Date.now();
    cloned.title = `${cloned.title} (Bản sao)`;
    cloned.completed = false;
    this.state.tasks.unshift(cloned);
    this.notify('task_added', cloned);
    return cloned;
  }

  postponeTask(id, days = 1) {
    const task = this.getTask(id);
    if (!task) return;
    const oldDue = task.dueDate;
    const curDate = task.dueDate ? new Date(task.dueDate) : new Date();
    curDate.setDate(curDate.getDate() + days);
    const yyyy = curDate.getFullYear();
    const mm = String(curDate.getMonth() + 1).padStart(2, '0');
    const dd = String(curDate.getDate()).padStart(2, '0');
    task.dueDate = `${yyyy}-${mm}-${dd}`;

    this.setUndoAction(`Đã dời hạn "${task.title}" sang ngày mai`, () => {
      task.dueDate = oldDue;
      this.notify('task_updated', task);
    });

    this.notify('task_updated', task);
  }

  // EVENTS API
  getEvents() {
    return this.state.events;
  }

  getEvent(id) {
    return this.state.events.find(e => e.id === id);
  }

  checkEventCollision(startDate, startTime, endTime, excludeId = null) {
    if (!startTime || !endTime) return false;
    return this.state.events.some(ev => {
      if (excludeId && ev.id === excludeId) return false;
      if (ev.allDay || ev.startDate !== startDate) return false;
      if (!ev.startTime || !ev.endTime) return false;
      // Compare time ranges (e.g. "09:00" < "10:30")
      return (startTime < ev.endTime && endTime > ev.startTime);
    });
  }

  addEvent(eventData) {
    const newEvent = {
      id: 'event-' + Date.now(),
      title: eventData.title || 'Sự kiện mới',
      startDate: eventData.startDate,
      endDate: eventData.endDate || eventData.startDate,
      startTime: eventData.startTime || '',
      endTime: eventData.endTime || '',
      allDay: !!eventData.allDay,
      category: eventData.category || 'cat-work',
      location: eventData.location || '',
      recurrence: eventData.recurrence || 'none',
      reminder: eventData.reminder || 'none',
      color: eventData.color || 'var(--cat-work)'
    };
    this.state.events.push(newEvent);

    this.setUndoAction(`Đã tạo sự kiện "${newEvent.title}"`, () => {
      this.state.events = this.state.events.filter(e => e.id !== newEvent.id);
      this.notify('event_removed', newEvent.id);
    });

    this.notify('event_added', newEvent);
    return newEvent;
  }

  updateEvent(id, updates) {
    const idx = this.state.events.findIndex(e => e.id === id);
    if (idx === -1) return null;
    const oldCopy = JSON.parse(JSON.stringify(this.state.events[idx]));
    this.state.events[idx] = { ...this.state.events[idx], ...updates };

    this.setUndoAction(`Đã cập nhật sự kiện "${this.state.events[idx].title}"`, () => {
      const curIdx = this.state.events.findIndex(e => e.id === id);
      if (curIdx !== -1) {
        this.state.events[curIdx] = oldCopy;
        this.notify('event_updated', oldCopy);
      }
    });

    this.notify('event_updated', this.state.events[idx]);
    return this.state.events[idx];
  }

  deleteEvent(id) {
    const idx = this.state.events.findIndex(e => e.id === id);
    if (idx === -1) return;
    const deletedEvent = this.state.events[idx];
    this.state.events.splice(idx, 1);

    this.setUndoAction(`Đã xóa sự kiện "${deletedEvent.title}"`, () => {
      this.state.events.splice(idx, 0, deletedEvent);
      this.notify('event_added', deletedEvent);
    });

    this.notify('event_deleted', id);
  }

  // TRASH API
  getTrash() {
    return this.state.trash;
  }

  restoreTrashItem(trashId) {
    const idx = this.state.trash.findIndex(t => t.id === trashId);
    if (idx === -1) return;
    const item = this.state.trash[idx];
    this.state.trash.splice(idx, 1);

    if (item.originalData) {
      if (item.originalType === 'task') {
        this.state.tasks.unshift(item.originalData);
      } else if (item.originalType === 'event') {
        this.state.events.unshift(item.originalData);
      }
    } else {
      // Create minimal task
      this.state.tasks.unshift({
        id: 'task-' + Date.now(),
        title: item.title,
        dueDate: '',
        dueTime: '',
        category: item.category || 'cat-work',
        priority: 'medium',
        tags: [],
        completed: false,
        notes: '',
        subtasks: [],
        recurrence: 'none',
        reminder: 'none'
      });
    }

    this.notify('trash_restored', item);
  }

  emptyTrash() {
    const count = this.state.trash.length;
    this.state.trash = [];
    this.notify('trash_emptied', count);
  }

  // CATEGORIES API
  getCategories() {
    return this.state.categories;
  }

  addCategory(category) {
    const newCat = {
      id: 'cat-' + Date.now(),
      name: category.name,
      color: category.color || '#2563eb',
      bg: category.bg || '#dbeafe',
      icon: 'folder',
      count: 0
    };
    this.state.categories.push(newCat);
    this.notify('category_added', newCat);
    return newCat;
  }

  updateCategory(id, updates) {
    const cat = this.state.categories.find(c => c.id === id);
    if (!cat) return;
    Object.assign(cat, updates);
    this.notify('category_updated', cat);
    return cat;
  }

  deleteCategory(id) {
    this.state.categories = this.state.categories.filter(c => c.id !== id);
    this.notify('category_deleted', id);
  }

  // NOTIFICATIONS API
  getNotifications() {
    return this.state.notifications;
  }

  snoozeNotification(id, durationMinutes) {
    const notif = this.state.notifications.find(n => n.id === id);
    if (!notif) return;
    notif.snoozed = true;
    notif.snoozedUntil = Date.now() + durationMinutes * 60000;
    this.notify('notification_snoozed', { id, durationMinutes });
  }

  dismissNotification(id) {
    this.state.notifications = this.state.notifications.filter(n => n.id !== id);
    this.notify('notification_dismissed', id);
  }
}

export const store = new AppStore();
