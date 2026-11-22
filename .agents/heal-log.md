# Nhật ký Tự sửa lỗi (Heal Log)

- **2026-11-22 — Chrome DevTools MCP (`browser` subagent)**:
  - Triệu chứng: `ENOENT: no such file or directory, open '...Chrome\User Data\DevToolsActivePort'` do trình duyệt đang mở là Microsoft Edge và Google Chrome chưa bật Remote Debugging.
  - Xử lý: Ghi nhận vào `.agents/pending/chrome-devtools-mcp.md`; xây dựng và chạy bộ kiểm thử E2E HTTP trực tiếp (`tests/live_http_e2e_flows.php`) với cURL + Cookie Jar + CSRF thật trên `http://127.0.0.1:8080` (24/24 PASS) kết hợp kiểm tra cú pháp JS/PHP và đồng bộ DB thực tế.
