# Wedding Studio — PHP / Laravel

Website portfolio cưới song ngữ, không cần Node.js trên hosting. Tên **Vow & Verse** và các đám cưới hiện là dữ liệu minh họa.

## Chạy trên máy

Yêu cầu PHP 8.2+ và các extension Laravel, PDO SQLite khi chạy thử hoặc PDO MySQL trên hosting. Dùng `composer install` nếu chưa có thư mục vendor.

```sh
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=StudioSeeder
php artisan studio:admin
php artisan serve --host=127.0.0.1 --port=8000
```

Mở http://127.0.0.1:8000 và http://127.0.0.1:8000/admin. Khi APP_ENV=local và chưa có tài khoản, có thể tạo tài khoản đầu tiên ở /admin. Trên production, việc tạo tài khoản qua web bị tắt; dùng studio:admin.

## Quản trị

- Đám cưới: tên, slug, địa điểm, ngày, phong cách, câu chuyện, ảnh bìa, album, theme, bản nháp/xuất bản. Upload JPG/PNG/WebP/AVIF, tối đa 8 MB/ảnh và 40 ảnh/lần, còn tùy giới hạn PHP. Nên nén WebP/AVIF trước khi upload; chưa tự chuyển đổi ảnh.
- Trang: văn bản theo theme hoặc HTML tùy chỉnh. HTML trong iframe sandbox, không thực thi JavaScript, chiều cao cố định; nội dung tương tác cần lập trình theme riêng.
- Menu: thêm/sửa/xóa, thứ tự số, tên, link và vị trí đầu trang/chân trang/cả hai. Trang: /?page=gioi-thieu. Link section: /#stories, /#about, /#contact.
- Ngôn ngữ: tiếng Việt mặc định, thêm mã như en, fr, zh-cn; ẩn ngôn ngữ phụ khi chưa sẵn sàng.
- Bản dịch: một khóa, nhiều ngôn ngữ. Nhập khóa vào tên menu, tiêu đề, câu chuyện hoặc nội dung trang, ví dụ wedding.an.title. Thiếu bản dịch thì dùng tiếng Việt. Text thường dùng chung cho mọi ngôn ngữ. Dịch thủ công, chưa có tự dịch AI. Nội dung hero, giới thiệu và footer cũng chỉnh ở đây.
- Liên hệ: lưu database trước khi gửi mail, đánh dấu đã xử lý hoặc xóa. Có CSRF, honeypot và giới hạn 5 yêu cầu/giờ/IP. SMTP gửi trực tiếp, không cần worker nền.
- Cài đặt: thương hiệu, email, điện thoại, địa chỉ, URL ảnh trang chủ, đổi mật khẩu. Các trang xuất bản tự được liệt kê ở footer.

## Theme

Có Editorial, Romance và Minimal cho đám cưới/trang văn bản. Thêm CSS vào public/themes/botanical.css, đăng ký botanical => Botanical tại config/studio.php. Dùng selector .theme-botanical để đổi màu, font, bố cục. File botanical.example.css là ví dụ. Không upload mã PHP từ admin.

## Email

Trong .env: MAIL_MAILER=smtp, nhập MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, CONTACT_MAIL_TO. Port 587 dùng MAIL_SCHEME=smtp (STARTTLS), 465 dùng MAIL_SCHEME=smtps. Sau khi đổi: php artisan config:clear. Gửi form thật để kiểm tra mail và trạng thái admin. Chưa cấu hình thì chỉ lưu lời nhắn.

## Ubuntu / shared hosting

1. Hosting cần PHP 8.2+, các extension Laravel, MySQL, quyền ghi storage, bootstrap/cache và public/uploads. Kiểm tra vòng đời bản Laravel/PHP trước khi triển khai thật.
2. Chuẩn bị thư viện bằng composer install --no-dev --optimize-autoloader, tải cả dự án gồm vendor lên server. Host không cần Composer nếu chuẩn bị vendor sẵn.
3. Trỏ document root domain vào public/. Không đưa .env, vendor, database hoặc thư mục dự án ra web root. Nếu cPanel có public_html cố định: đặt ứng dụng bên ngoài, chép nội dung public vào public_html, sửa đường dẫn vendor/bootstrap trong index.php và cập nhật public path trong bootstrap/app.php.
4. Tạo MySQL database. Đổi DB_CONNECTION=mysql và điền DB_HOST/PORT/DATABASE/USERNAME/PASSWORD. Không dùng đường dẫn SQLite làm tên DB MySQL.
5. Tạo .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://ten-mien, SESSION_SECURE_COOKIE=true. Tạo APP_KEY một lần cho web mới; giữ APP_KEY cũ khi chuyển host.
6. Chạy php artisan migrate --force, php artisan db:seed --class=StudioSeeder --force (tạo mẫu một lần), php artisan studio:admin, php artisan optimize. Nếu host không có terminal, chuẩn bị database MySQL tương thích trên máy rồi import; tránh script cài đặt công khai.
7. Cấu hình HTTPS, SMTP, PHP upload_max_filesize=8M, post_max_size=40M, max_file_uploads=40 theo nhu cầu. Mẫu Nginx tại deploy/nginx.conf.example; Apache dùng public/.htaccess.
8. Thay dữ liệu mẫu, thử form, upload, đăng nhập, ngôn ngữ trên domain thật.

## Dời host / sao lưu

Sao lưu database MySQL (export SQL), public/uploads, theme, mã nguồn. Import SQL trên host mới; đổi DB/SMTP/APP_URL; giữ APP_KEY; chạy php artisan optimize:clear rồi php artisan optimize. Không commit .env, database và uploads vào Git.

Ảnh mẫu dùng URL Unsplash, font dùng Google Fonts. Thay bằng ảnh được cấp quyền của bạn và lưu trên hosting trước khi ra mắt. Giao diện có lazy loading, CSS/IntersectionObserver và reduced motion. Chưa có CDN hoặc tự nén ảnh.

## Kiểm tra

```sh
php artisan test
php artisan view:cache
```

Đây là bản nền CMS để tiếp tục tùy biến. Chưa triển khai server thật và chưa xác minh SMTP production.
