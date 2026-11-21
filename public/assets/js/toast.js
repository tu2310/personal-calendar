/**
 * Toast Notification System - Personal Calendar SaaS
 * Tích hợp thông báo Toast hiện đại, tinh gọn, animation mượt mà
 */

(function (window, document) {
    'use strict';

    const ICONS = {
        success: 'fa-solid fa-circle-check',
        error: 'fa-solid fa-circle-exclamation',
        warning: 'fa-solid fa-triangle-exclamation',
        info: 'fa-solid fa-circle-info'
    };

    const DEFAULT_TITLES = {
        success: 'Thành công!',
        error: 'Có lỗi xảy ra!',
        warning: 'Lưu ý!',
        info: 'Thông tin'
    };

    const DEFAULT_DURATIONS = {
        success: 2000,
        error: 2000,
        warning: 2000,
        info: 2000
    };

    let container = null;
    let lastToastKey = null;
    let lastToastTime = 0;

    function getContainer() {
        if (!container || !document.body.contains(container)) {
            container = document.querySelector('.toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container';
                container.id = 'toastContainer';
                container.setAttribute('aria-live', 'polite');
                document.body.appendChild(container);
            }
        }
        return container;
    }

    class ToastInstance {
        constructor(options) {
            this.type = ['success', 'error', 'warning', 'info'].includes(options.type)
                ? options.type
                : 'info';
            this.title = options.title !== undefined && options.title !== null
                ? options.title
                : DEFAULT_TITLES[this.type];
            this.message = options.message || '';
            this.duration = Number(options.duration) || DEFAULT_DURATIONS[this.type];

            this.remaining = this.duration;
            this.startTime = null;
            this.timerId = null;
            this.rafId = null;
            this.isPaused = false;
            this.isClosing = false;

            this.element = null;
            this.progressBar = null;

            this.create();
        }

        create() {
            const host = getContainer();

            // Giới hạn số lượng toast đồng thời tối đa là 5
            const existingToasts = host.querySelectorAll('.toast-item:not(.toast-closing)');
            if (existingToasts.length >= 5) {
                const oldest = existingToasts[0];
                if (oldest && oldest._toastInstance) {
                    oldest._toastInstance.close();
                }
            }

            const item = document.createElement('div');
            item.className = `toast-item toast-${this.type}`;
            item.setAttribute('role', 'alert');
            item._toastInstance = this;

            const iconClass = ICONS[this.type] || ICONS.info;

            item.innerHTML = `
                <div class="toast-header-row">
                    <div class="toast-title-wrap">
                        <span class="toast-icon"><i class="${iconClass}"></i></span>
                        <h4 class="toast-title">${this.escapeHtml(this.title)}</h4>
                    </div>
                    <button type="button" class="toast-close-btn" aria-label="Đóng thông báo">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                ${this.message ? `<div class="toast-message">${this.escapeHtml(this.message)}</div>` : ''}
                <div class="toast-progress-track">
                    <div class="toast-progress-bar"></div>
                </div>
            `;

            this.element = item;
            this.progressBar = item.querySelector('.toast-progress-bar');

            // Nút đóng thủ công
            const closeBtn = item.querySelector('.toast-close-btn');
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.close();
            });

            // Tạm dừng khi di chuột vào (Hover pause)
            item.addEventListener('mouseenter', () => this.pause());
            item.addEventListener('mouseleave', () => this.resume());

            host.appendChild(item);

            this.startTimer();
        }

        startTimer() {
            if (this.duration <= 0) return;

            this.startTime = performance.now();
            this.updateProgress();

            this.timerId = setTimeout(() => {
                this.close();
            }, this.remaining);
        }

        updateProgress() {
            if (this.isPaused || this.isClosing || !this.progressBar) return;

            const elapsed = performance.now() - this.startTime;
            const currentRemaining = Math.max(0, this.remaining - elapsed);
            const ratio = Math.max(0, Math.min(1, currentRemaining / this.duration));

            this.progressBar.style.width = `${(ratio * 100).toFixed(2)}%`;

            if (currentRemaining > 0) {
                this.rafId = requestAnimationFrame(() => this.updateProgress());
            }
        }

        pause() {
            if (this.isPaused || this.isClosing) return;
            this.isPaused = true;
            clearTimeout(this.timerId);
            cancelAnimationFrame(this.rafId);

            const elapsed = performance.now() - this.startTime;
            this.remaining = Math.max(0, this.remaining - elapsed);
        }

        resume() {
            if (!this.isPaused || this.isClosing) return;
            this.isPaused = false;
            this.startTime = performance.now();

            this.timerId = setTimeout(() => {
                this.close();
            }, this.remaining);

            this.rafId = requestAnimationFrame(() => this.updateProgress());
        }

        close() {
            if (this.isClosing) return;
            this.isClosing = true;

            clearTimeout(this.timerId);
            cancelAnimationFrame(this.rafId);

            if (!this.element) return;

            this.element.classList.add('toast-closing');

            const removeElement = () => {
                if (this.element && this.element.parentElement) {
                    this.element.parentElement.removeChild(this.element);
                }
                this.element = null;
            };

            this.element.addEventListener('animationend', removeElement, { once: true });
            setTimeout(removeElement, 350);
        }

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    const Toast = {
        show(options) {
            if (typeof options === 'string') {
                options = { message: options, type: 'info' };
            }

            // Chống spam thông báo trùng lặp trong khoảng thời gian ngắn (< 400ms)
            const key = `${options.type || 'info'}_${options.title || ''}_${options.message || ''}`;
            const now = Date.now();
            if (key === lastToastKey && now - lastToastTime < 400) {
                return null;
            }
            lastToastKey = key;
            lastToastTime = now;

            return new ToastInstance(options);
        },

        success(message, title = DEFAULT_TITLES.success, duration = DEFAULT_DURATIONS.success) {
            return this.show({ type: 'success', title, message, duration });
        },

        error(message, title = DEFAULT_TITLES.error, duration = DEFAULT_DURATIONS.error) {
            return this.show({ type: 'error', title, message, duration });
        },

        warning(message, title = DEFAULT_TITLES.warning, duration = DEFAULT_DURATIONS.warning) {
            return this.show({ type: 'warning', title, message, duration });
        },

        info(message, title = DEFAULT_TITLES.info, duration = DEFAULT_DURATIONS.info) {
            return this.show({ type: 'info', title, message, duration });
        },

        clearAll() {
            const host = document.querySelector('.toast-container');
            if (host) {
                host.querySelectorAll('.toast-item').forEach(item => {
                    if (item._toastInstance) item._toastInstance.close();
                });
            }
        }
    };

    window.Toast = Toast;
})(window, document);
