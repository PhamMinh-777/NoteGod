# FinalNote - Cloud-Native Collaborative Note Taking

Đồ án môn Lập trình Web (Final Project) - Trường Đại học Tôn Đức Thắng (TDTU).

---

## 📁 Cấu trúc Thư mục Dự án (Project Directory Structure)

Dự án được tái cấu trúc theo mô hình phân tầng rõ ràng, tách biệt giữa Giao diện (Frontend/UI), Cấu hình (Config), Dịch vụ API (Backend API), Cơ sở dữ liệu (Database) và Tài liệu kỹ thuật (Materials):

```text
Final/
├── docker-compose.yml          # Cấu hình khởi chạy Docker (PHP-Apache, MySQL, phpMyAdmin)
├── .gitignore                  # Bỏ qua file rác hệ thống, zip, và thư mục dữ liệu mysql/data
├── README.md                   # Tài liệu hướng dẫn dự án
│
├── Materials/                  # Tài liệu đặc tả kỹ thuật và thiết kế
│   ├── APISpecifications.docx  # Đặc tả toàn bộ RESTful API endpoints
│   ├── ERD.docx                # Thiết kế cơ sở dữ liệu và bảo mật
│   └── UXFlow.docx             # Sơ đồ và luồng trải nghiệm người dùng
│
├── mysql/                      # Cấu hình & kịch bản cơ sở dữ liệu MySQL
│   ├── data/                   # Thư mục volume runtime MySQL (được gitignore)
│   └── sql/                    # Scripts khởi tạo database khi build container
│       ├── 01_finalnote_schema.sql # Bảng CSDL FinalNote (users, notes, labels, shares,...)
│       └── data.sql            # Dữ liệu mẫu ban đầu (theo template môn học)
│
└── www/                        # Mã nguồn ứng dụng Web (DocumentRoot: /var/www/html)
    ├── Dockerfile              # Dockerfile cấu hình PHP 8.4 Apache + PDO MySQL
    ├── .dockerignore           # Tối ưu hóa build context của Docker
    │
    ├── config/                 # Cấu hình hệ thống & kết nối DB dùng chung
    │   └── database.php        # Helper kết nối PDO qua biến môi trường (fallback an toàn)
    │
    ├── api/                    # Cổng RESTful API Gateway (sẵn sàng kết nối frontend & backend)
    │   └── index.php           # API Router & chuẩn hóa response JSON envelope
    │
    ├── assets/                 # Quản lý tài nguyên tĩnh tập trung
    │   ├── css/
    │   │   └── style.css       # Toàn bộ CSS giao diện người dùng
    │   ├── js/
    │   │   └── main.js         # JavaScript logic (gọi API, render UI, offline sync)
    │   └── images/             # Hình ảnh, icons, logo trường TDTU
    │       ├── icon-192.png
    │       ├── icon-512.png
    │       └── tdt-logo.png
    │
    ├── auth/                   # Các trang quy trình xác thực người dùng
    │   ├── login.php           # Đăng nhập
    │   ├── register.php        # Đăng ký tài khoản
    │   ├── forgot.php          # Quên mật khẩu
    │   ├── reset.php           # Đặt lại mật khẩu qua OTP
    │   └── verify.php          # Xác thực tài khoản qua email
    │
    ├── partials/               # Các thành phần giao diện tái sử dụng
    │   ├── head.php            # Thẻ <head>, nạp CSS, font, manifest
    │   ├── foot.php            # Nạp thư viện JS (jQuery, Bootstrap, main.js)
    │   └── icons.php           # Helper SVG icons inline
    │
    ├── admin/                  # Endpoint kiểm tra môi trường theo yêu cầu của môn học
    │   ├── db.php              # Test kết nối MySQLi
    │   └── db-pdo.php          # Test kết nối PDO (sử dụng config/database.php)
    │
    ├── index.php               # Trang chính Dashboard (Ghi chú, Lọc theo nhãn, Tìm kiếm, AI)
    ├── editor.php              # Trình soạn thảo ghi chú (Rich-text, màu sắc, khóa bảo mật)
    ├── profile.php             # Trang thông tin cá nhân & tùy chọn giao diện
    │
    ├── manifest.json           # Cấu hình PWA (Progressive Web App)
    └── sw.js                   # Service Worker hỗ trợ offline caching (ở root để quản lý toàn bộ scope)
```

---

## 🚀 Hướng dẫn Chạy Dự án (Quick Start)

### 1. Khởi động môi trường với Docker

Chạy lệnh sau tại thư mục gốc của dự án:

```bash
docker compose up -d --build
```

### 2. Các cổng dịch vụ (Ports)

| Dịch vụ | URL | Thông tin đăng nhập |
| :--- | :--- | :--- |
| **FinalNote Web App** | `http://localhost:8080` | Sử dụng trực tiếp trên trình duyệt |
| **Kiểm tra kết nối DB (PDO)** | `http://localhost:8080/admin/db-pdo.php` | Test kết nối DB tự động |
| **phpMyAdmin** | `http://localhost:8888` | Server: `mysql-server`<br>User: `root`<br>Password: `root` |
| **MySQL Server** | `localhost:3399` | Database: `finalnote` hoặc `product_management` |

---

## 🛠 Điểm Cải tiến & Đảm bảo Tính Nhất quán

1. **Chuẩn hóa Assets**: Tách `style.css`, `main.js`, và hình ảnh vào thư mục `assets/css/`, `assets/js/`, `assets/images/`, giúp cấu trúc dự án ngăn nắp và dễ bảo trì.
2. **Tuân thủ Tiêu chuẩn PWA**: `sw.js` và `manifest.json` được duy trì tại thư mục gốc (`www/`) để đảm bảo Service Worker có quyền kiểm soát toàn bộ scope (`/`).
3. **Quản trị Cấu hình Database**: Tạo `config/database.php` dùng PDO chuẩn với cơ chế kết nối Singleton/Helper, đọc cấu hình từ biến môi trường của Docker và có fallback linh hoạt.
4. **Bảo tồn Endpoint Đánh giá**: Các file `admin/db.php` và `admin/db-pdo.php` được giữ và liên kết chuẩn để giảng viên chấm bài kiểm tra kết nối CSDL mà không bị lỗi.
5. **Dọn dẹp & Git Hygiene**: Đã bổ sung `.gitignore` đầy đủ (loại bỏ file rác `.DS_Store`, thư mục dữ liệu binary `mysql/data/`), giữ kho lưu trữ sạch sẽ và chuyên nghiệp.
6. **Sẵn sàng cho Backend**: Tạo nền móng `api/` và schema CSDL `mysql/sql/01_finalnote_schema.sql` khớp 100% với tài liệu thiết kế hệ thống.
