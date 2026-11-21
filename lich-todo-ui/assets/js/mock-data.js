// Mock Data Generator relative to current date
export function createInitialData() {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth();
  const date = now.getDate();

  function offsetDate(dayDelta, hours = 9, minutes = 0) {
    const d = new Date(year, month, date + dayDelta, hours, minutes, 0);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return {
      dateStr: `${yyyy}-${mm}-${dd}`,
      dateTimeStr: `${yyyy}-${mm}-${dd}T${hh}:${min}`,
      timeStr: `${hh}:${min}`,
      displayDate: `${dd}/${mm}/${yyyy}`,
      jsDate: d
    };
  }

  const categories = [
    { id: 'cat-work', name: 'Công việc', color: 'var(--cat-work)', bg: 'var(--cat-work-bg)', icon: 'folder', count: 8 },
    { id: 'cat-study', name: 'Học tập', color: 'var(--cat-study)', bg: 'var(--cat-study-bg)', icon: 'folder', count: 5 },
    { id: 'cat-personal', name: 'Cá nhân', color: 'var(--cat-personal)', bg: 'var(--cat-personal-bg)', icon: 'folder', count: 6 },
    { id: 'cat-health', name: 'Sức khỏe', color: 'var(--cat-health)', bg: 'var(--cat-health-bg)', icon: 'folder', count: 3 },
    { id: 'cat-family', name: 'Gia đình', color: 'var(--cat-family)', bg: 'var(--cat-family-bg)', icon: 'folder', count: 3 }
  ];

  const tags = [
    { id: 'tag-urgent', name: 'Khẩn cấp', color: '#dc2626' },
    { id: 'tag-finance', name: 'Tài chính', color: '#16a34a' },
    { id: 'tag-project', name: 'Dự án A', color: '#2563eb' },
    { id: 'tag-self', name: 'Phát triển bản thân', color: '#7c3aed' }
  ];

  const tasks = [
    // Quá hạn (-3 to -1 days)
    {
      id: 'task-1',
      title: 'Nộp báo cáo tài chính tháng trước',
      dueDate: offsetDate(-3, 17, 0).dateStr,
      dueTime: '17:00',
      category: 'cat-work',
      priority: 'high', // high | medium | low
      tags: ['tag-urgent', 'tag-finance'],
      completed: false,
      notes: 'Gửi bản PDF cho kế toán trưởng trước khi chốt sổ.',
      subtasks: [
        { id: 'sub-1-1', title: 'Kiểm kê biên lai thanh toán', completed: true },
        { id: 'sub-1-2', title: 'Xuất file excel tổng hợp', completed: true },
        { id: 'sub-1-3', title: 'Ký duyệt số liệu', completed: false }
      ],
      recurrence: 'none',
      reminder: '15m'
    },
    {
      id: 'task-2',
      title: 'Đăng ký môn học học kỳ phụ',
      dueDate: offsetDate(-2, 12, 0).dateStr,
      dueTime: '12:00',
      category: 'cat-study',
      priority: 'high',
      tags: ['tag-urgent'],
      completed: false,
      notes: 'Cần vào cổng thông tin đào tạo trước 12h trưa.',
      subtasks: [],
      recurrence: 'none',
      reminder: '30m'
    },
    {
      id: 'task-3',
      title: 'Thanh toán tiền điện nước căn hộ',
      dueDate: offsetDate(-1, 20, 0).dateStr,
      dueTime: '20:00',
      category: 'cat-family',
      priority: 'medium',
      tags: ['tag-finance'],
      completed: false,
      notes: 'Thanh toán qua ví điện tử.',
      subtasks: [],
      recurrence: 'monthly',
      reminder: '1h'
    },

    // Hôm nay (Day 0)
    {
      id: 'task-4',
      title: 'Hoàn thiện bản thiết kế UI Dashboard',
      dueDate: offsetDate(0, 10, 0).dateStr,
      dueTime: '10:00',
      category: 'cat-work',
      priority: 'high',
      tags: ['tag-project'],
      completed: false,
      notes: 'Đồng bộ token màu và hoàn thiện responsive 360px.',
      subtasks: [
        { id: 'sub-4-1', title: 'Kiểm tra độ tương phản WCAG AA', completed: true },
        { id: 'sub-4-2', title: 'Xuất các asset SVG sạch', completed: false }
      ],
      recurrence: 'none',
      reminder: '15m'
    },
    {
      id: 'task-5',
      title: 'Họp giao ban tiến độ tuần với team Dev',
      dueDate: offsetDate(0, 14, 0).dateStr,
      dueTime: '14:00',
      category: 'cat-work',
      priority: 'high',
      tags: ['tag-project'],
      completed: false,
      notes: 'Chuẩn bị danh sách blocker và kế hoạch sprint tới.',
      subtasks: [],
      recurrence: 'weekly',
      reminder: '10m'
    },
    {
      id: 'task-6',
      title: 'Chạy bộ 5km công viên',
      dueDate: offsetDate(0, 18, 0).dateStr,
      dueTime: '18:00',
      category: 'cat-health',
      priority: 'medium',
      tags: ['tag-self'],
      completed: true,
      notes: 'Giữ pace ổn định 6:30.',
      subtasks: [],
      recurrence: 'daily',
      reminder: 'none'
    },
    {
      id: 'task-7',
      title: 'Đọc 2 chương sách Thiết kế Tương tác',
      dueDate: offsetDate(0).dateStr,
      dueTime: '',
      category: 'cat-study',
      priority: 'low',
      tags: ['tag-self'],
      completed: false,
      notes: 'Ghi chép các nguyên lý Jakob và Fitts law.',
      subtasks: [],
      recurrence: 'none',
      reminder: 'none'
    },
    {
      id: 'task-8',
      title: 'Mua quà sinh nhật cho mẹ',
      dueDate: offsetDate(0).dateStr,
      dueTime: '',
      category: 'cat-family',
      priority: 'medium',
      tags: [],
      completed: false,
      notes: 'Tìm khăn lụa hoặc bộ trà thảo mộc.',
      subtasks: [],
      recurrence: 'none',
      reminder: 'none'
    },
    {
      id: 'task-9',
      title: 'Uống đủ 2 lít nước trong ngày',
      dueDate: offsetDate(0).dateStr,
      dueTime: '',
      category: 'cat-health',
      priority: 'low',
      tags: [],
      completed: true,
      notes: '',
      subtasks: [],
      recurrence: 'daily',
      reminder: 'none'
    },

    // Ngày mai & Sắp tới (+1 to +14 days)
    {
      id: 'task-10',
      title: 'Review PR mã nguồn frontend',
      dueDate: offsetDate(1, 9, 30).dateStr,
      dueTime: '09:30',
      category: 'cat-work',
      priority: 'high',
      tags: ['tag-project'],
      completed: false,
      notes: 'Kiểm tra kỹ phần xử lý hash change và accessibility.',
      subtasks: [
        { id: 'sub-10-1', title: 'Kiểm tra Tab index và focus trap', completed: false },
        { id: 'sub-10-2', title: 'Chạy test node check', completed: false }
      ],
      recurrence: 'none',
      reminder: '15m'
    },
    {
      id: 'task-11',
      title: 'Luyện nghe IELTS Cambridge Test 3',
      dueDate: offsetDate(1, 20, 0).dateStr,
      dueTime: '20:00',
      category: 'cat-study',
      priority: 'medium',
      tags: ['tag-self'],
      completed: false,
      notes: 'Tập trung Section 3 và 4.',
      subtasks: [],
      recurrence: 'none',
      reminder: '30m'
    },
    {
      id: 'task-12',
      title: 'Bảo dưỡng xe máy định kỳ',
      dueDate: offsetDate(2, 8, 30).dateStr,
      dueTime: '08:30',
      category: 'cat-personal',
      priority: 'medium',
      tags: [],
      completed: false,
      notes: 'Thay dầu nhớt và kiểm tra má phanh.',
      subtasks: [],
      recurrence: 'none',
      reminder: '1h'
    },
    {
      id: 'task-13',
      title: 'Gặp gỡ cố vấn nghề nghiệp',
      dueDate: offsetDate(3, 15, 0).dateStr,
      dueTime: '15:00',
      category: 'cat-work',
      priority: 'high',
      tags: ['tag-self'],
      completed: false,
      notes: 'Trao đổi về lộ trình thăng tiến Lead UX.',
      subtasks: [],
      recurrence: 'none',
      reminder: '1h'
    },
    {
      id: 'task-14',
      title: 'Đặt lịch khám răng tổng quát',
      dueDate: offsetDate(4, 10, 0).dateStr,
      dueTime: '10:00',
      category: 'cat-health',
      priority: 'medium',
      tags: [],
      completed: false,
      notes: 'Nha khoa Quốc tế, hẹn trước 3 ngày.',
      subtasks: [],
      recurrence: 'none',
      reminder: '1d'
    },
    {
      id: 'task-15',
      title: 'Dọn dẹp và sao lưu ổ cứng cá nhân',
      dueDate: offsetDate(5, 21, 0).dateStr,
      dueTime: '21:00',
      category: 'cat-personal',
      priority: 'low',
      tags: [],
      completed: false,
      notes: 'Đồng bộ ảnh chụp và tài liệu quan trọng lên ổ cứng ngoài.',
      subtasks: [],
      recurrence: 'monthly',
      reminder: 'none'
    },
    {
      id: 'task-16',
      title: 'Nộp bài tiểu luận môn Quản trị dự án',
      dueDate: offsetDate(7, 23, 59).dateStr,
      dueTime: '23:59',
      category: 'cat-study',
      priority: 'high',
      tags: ['tag-urgent'],
      completed: false,
      notes: 'Định dạng chuẩn IEEE, dung lượng tối đa 15 trang.',
      subtasks: [
        { id: 'sub-16-1', title: 'Viết mở bài và cơ sở lý thuyết', completed: true },
        { id: 'sub-16-2', title: 'Phân tích nghiên cứu tình huống', completed: false },
        { id: 'sub-16-3', title: 'Trích dẫn tài liệu tham khảo', completed: false }
      ],
      recurrence: 'none',
      reminder: '1d'
    },
    {
      id: 'task-17',
      title: 'Lên thực đơn ăn sạch cả tuần sau',
      dueDate: offsetDate(6, 16, 0).dateStr,
      dueTime: '16:00',
      category: 'cat-health',
      priority: 'low',
      tags: [],
      completed: false,
      notes: 'Tăng cường rau xanh, giảm đường và đồ chiên rán.',
      subtasks: [],
      recurrence: 'weekly',
      reminder: 'none'
    },
    {
      id: 'task-18',
      title: 'Gửi thiệp mừng tân gia nhà bạn thân',
      dueDate: offsetDate(8, 11, 0).dateStr,
      dueTime: '11:00',
      category: 'cat-family',
      priority: 'medium',
      tags: [],
      completed: false,
      notes: '',
      subtasks: [],
      recurrence: 'none',
      reminder: 'none'
    },
    {
      id: 'task-19',
      title: 'Tham gia hội thảo trực tuyến AI in Product Design',
      dueDate: offsetDate(10, 19, 0).dateStr,
      dueTime: '19:00',
      category: 'cat-study',
      priority: 'medium',
      tags: ['tag-project'],
      completed: false,
      notes: 'Đăng nhập phòng Zoom trước 10 phút.',
      subtasks: [],
      recurrence: 'none',
      reminder: '1h'
    },
    {
      id: 'task-20',
      title: 'Đăng ký gia hạn hộ chiếu online',
      dueDate: offsetDate(12, 14, 0).dateStr,
      dueTime: '14:00',
      category: 'cat-personal',
      priority: 'medium',
      tags: ['tag-finance'],
      completed: false,
      notes: 'Chụp ảnh thẻ nền trắng và chuẩn bị CCCD gắn chip.',
      subtasks: [],
      recurrence: 'none',
      reminder: '1d'
    },
    {
      id: 'task-21',
      title: 'Tái cơ cấu thư mục tài liệu Google Drive',
      dueDate: offsetDate(14).dateStr,
      dueTime: '',
      category: 'cat-work',
      priority: 'low',
      tags: [],
      completed: false,
      notes: 'Phân loại theo Năm / Quý / Khách hàng.',
      subtasks: [],
      recurrence: 'none',
      reminder: 'none'
    },

    // Không hạn (No due date)
    {
      id: 'task-22',
      title: 'Tìm hiểu khóa học lập trình Rust cơ bản',
      dueDate: '',
      dueTime: '',
      category: 'cat-study',
      priority: 'low',
      tags: ['tag-self'],
      completed: false,
      notes: 'Xem giáo trình The Rust Programming Language.',
      subtasks: [],
      recurrence: 'none',
      reminder: 'none'
    },
    {
      id: 'task-23',
      title: 'Lên kế hoạch du lịch Đà Lạt cùng gia đình',
      dueDate: '',
      dueTime: '',
      category: 'cat-family',
      priority: 'medium',
      tags: [],
      completed: false,
      notes: 'Tìm homestay gần trung tâm và các quán cà phê ngắm cảnh.',
      subtasks: [],
      recurrence: 'none',
      reminder: 'none'
    },
    {
      id: 'task-24',
      title: 'Thử nghiệm công thức pha chế Cold Brew tại nhà',
      dueDate: '',
      dueTime: '',
      category: 'cat-personal',
      priority: 'low',
      tags: [],
      completed: false,
      notes: 'Tỷ lệ hạt 1:8, ủ trong tủ lạnh 18 tiếng.',
      subtasks: [],
      recurrence: 'none',
      reminder: 'none'
    },
    {
      id: 'task-25',
      title: 'Cập nhật portfolio cá nhân trên Behance',
      dueDate: '',
      dueTime: '',
      category: 'cat-work',
      priority: 'medium',
      tags: ['tag-project'],
      completed: true,
      notes: 'Đã thêm 2 dự án ứng dụng di động mới nhất.',
      subtasks: [],
      recurrence: 'none',
      reminder: 'none'
    }
  ];

  const events = [
    // Hôm nay: Sự kiện cả ngày
    {
      id: 'event-1',
      title: 'Ngày làm việc từ xa (Work From Home)',
      startDate: offsetDate(0, 0, 0).dateStr,
      endDate: offsetDate(0, 23, 59).dateStr,
      startTime: '',
      endTime: '',
      allDay: true,
      category: 'cat-work',
      location: 'Tại nhà riêng',
      recurrence: 'weekly',
      reminder: 'none',
      color: 'var(--cat-work)'
    },
    // Hôm nay: Sự kiện 1 (09:00 - 10:30)
    {
      id: 'event-2',
      title: 'Họp Kick-off Dự án Thiết Kế Hệ Thống',
      startDate: offsetDate(0, 9, 0).dateStr,
      endDate: offsetDate(0, 10, 30).dateStr,
      startTime: '09:00',
      endTime: '10:30',
      allDay: false,
      category: 'cat-work',
      location: 'Phòng họp Tầng 4 / Google Meet',
      recurrence: 'none',
      reminder: '15m',
      color: 'var(--cat-work)'
    },
    // Hôm nay: Sự kiện trùng giờ (10:00 - 11:30) -> Cảnh báo trùng với event-2!
    {
      id: 'event-3',
      title: 'Phỏng vấn ứng viên Thực tập sinh UI/UX',
      startDate: offsetDate(0, 10, 0).dateStr,
      endDate: offsetDate(0, 11, 30).dateStr,
      startTime: '10:00',
      endTime: '11:30',
      allDay: false,
      category: 'cat-work',
      location: 'Phòng họp nhỏ số 2',
      recurrence: 'none',
      reminder: '10m',
      color: 'var(--danger)'
    },
    // Hôm nay: Sự kiện chiều (14:30 - 16:00)
    {
      id: 'event-4',
      title: 'Buổi chia sẻ kiến thức Design System nội bộ',
      startDate: offsetDate(0, 14, 30).dateStr,
      endDate: offsetDate(0, 16, 0).dateStr,
      startTime: '14:30',
      endTime: '16:00',
      allDay: false,
      category: 'cat-study',
      location: 'Hội trường chính',
      recurrence: 'none',
      reminder: '30m',
      color: 'var(--cat-study)'
    },
    // Ngày mai: Sự kiện
    {
      id: 'event-5',
      title: 'Hội thảo Đổi mới sáng tạo Công nghệ số',
      startDate: offsetDate(1, 8, 30).dateStr,
      endDate: offsetDate(1, 11, 30).dateStr,
      startTime: '08:30',
      endTime: '11:30',
      allDay: false,
      category: 'cat-work',
      location: 'Trung tâm Hội nghị Quốc gia',
      recurrence: 'none',
      reminder: '1d',
      color: 'var(--cat-work)'
    },
    // +2 days
    {
      id: 'event-6',
      title: 'Trận bóng đá giao hữu câu lạc bộ',
      startDate: offsetDate(2, 17, 30).dateStr,
      endDate: offsetDate(2, 19, 0).dateStr,
      startTime: '17:30',
      endTime: '19:00',
      allDay: false,
      category: 'cat-health',
      location: 'Sân bóng Thảo Điền',
      recurrence: 'weekly',
      reminder: '2h',
      color: 'var(--cat-health)'
    },
    // +4 days
    {
      id: 'event-7',
      title: 'Bữa tối sum họp gia đình cuối tuần',
      startDate: offsetDate(4, 18, 30).dateStr,
      endDate: offsetDate(4, 21, 0).dateStr,
      startTime: '18:30',
      endTime: '21:00',
      allDay: false,
      category: 'cat-family',
      location: 'Nhà hàng Cơm Niêu Sài Gòn',
      recurrence: 'none',
      reminder: '3h',
      color: 'var(--cat-family)'
    },
    // +7 days: Sự kiện cả ngày
    {
      id: 'event-8',
      title: 'Ngày hội Hiến máu tình nguyện',
      startDate: offsetDate(7, 7, 30).dateStr,
      endDate: offsetDate(7, 12, 0).dateStr,
      startTime: '07:30',
      endTime: '12:00',
      allDay: false,
      category: 'cat-health',
      location: 'Bệnh viện Truyền máu Huyết học',
      recurrence: 'none',
      reminder: '1d',
      color: 'var(--cat-health)'
    },
    // +11 days
    {
      id: 'event-9',
      title: 'Kiểm toán nội bộ Quý 4',
      startDate: offsetDate(11, 9, 0).dateStr,
      endDate: offsetDate(11, 17, 0).dateStr,
      startTime: '09:00',
      endTime: '17:00',
      allDay: false,
      category: 'cat-work',
      location: 'Phòng Giám đốc',
      recurrence: 'none',
      reminder: '1d',
      color: 'var(--cat-work)'
    },
    // +14 days
    {
      id: 'event-10',
      title: 'Lớp học vẽ ký họa cuối tuần',
      startDate: offsetDate(14, 14, 0).dateStr,
      endDate: offsetDate(14, 16, 30).dateStr,
      startTime: '14:00',
      endTime: '16:30',
      allDay: false,
      category: 'cat-personal',
      location: 'Xưởng nghệ thuật ArtHouse',
      recurrence: 'weekly',
      reminder: '1d',
      color: 'var(--cat-personal)'
    }
  ];

  const trash = [
    {
      id: 'trash-1',
      title: 'Đặt mua đồ dùng văn phòng cũ',
      category: 'cat-work',
      deletedAt: offsetDate(-4).displayDate,
      originalType: 'task'
    },
    {
      id: 'trash-2',
      title: 'Hủy lịch hẹn cà phê trao đổi hợp đồng',
      category: 'cat-personal',
      deletedAt: offsetDate(-2).displayDate,
      originalType: 'task'
    }
  ];

  const notifications = [
    {
      id: 'notif-1',
      title: 'Việc quá hạn: Nộp báo cáo tài chính',
      body: 'Hạn chót vào 3 ngày trước nhưng chưa hoàn thành.',
      type: 'overdue',
      taskId: 'task-1',
      time: '08:00 hôm nay',
      snoozed: false
    },
    {
      id: 'notif-2',
      title: 'Sắp diễn ra: Họp giao ban tiến độ',
      body: 'Bắt đầu lúc 14:00 chiều nay với team Dev.',
      type: 'reminder',
      taskId: 'task-5',
      time: '13:50 hôm nay',
      snoozed: false
    },
    {
      id: 'notif-3',
      title: 'Cảnh báo trùng lịch',
      body: 'Phỏng vấn ứng viên trùng giờ với Họp Kick-off sáng nay.',
      type: 'collision',
      time: '10:00 hôm nay',
      snoozed: false
    }
  ];

  return {
    categories,
    tags,
    tasks,
    events,
    trash,
    notifications,
    userProfile: {
      name: 'Nguyễn Văn Minh',
      email: 'minh.nguyen@example.com',
      avatar: 'NV',
      theme: 'auto', // light | dark | auto
      weekStart: 'monday', // monday | sunday
      dateFormat: 'dd/mm/yyyy',
      timeFormat: '24h',
      defaultReminder: '15m'
    }
  };
}
