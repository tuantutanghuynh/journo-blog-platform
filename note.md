24/04/2026

###php artisan install:api làm 3 việc:

Tạo file routes/api.php — file chứa tất cả API routes của dự án (hiện tại chưa có file này)

Publish Sanctum migration — tạo bảng personal_access_tokens trong database để lưu token đăng nhập

Cài đặt Sanctum middleware — kích hoạt hệ thống xác thực token cho API
y
