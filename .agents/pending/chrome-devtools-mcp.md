# Pending: Kết nối Chrome DevTools MCP (`browser`)

## Nguyên nhân gốc
Công cụ `chrome-devtools` báo thiếu file `C:\Users\Thohi\AppData\Local\Google\Chrome\User Data\DevToolsActivePort` (Google Chrome chưa chạy chế độ Remote Debugging, hiện đang dùng Microsoft Edge).

## Cách bật khi cần AI tự điều khiển Chrome UI
Mở Google Chrome vào địa chỉ `chrome://inspect/#remote-debugging` và bật Remote Debugging, hoặc khởi chạy Chrome với cổng debug:
```powershell
Start-Process "chrome.exe" "--remote-debugging-port=9222 http://127.0.0.1:8080"
```
