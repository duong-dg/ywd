# Triển khai bằng Docker trên Ubuntu

Dùng PHP 8.4 + Apache và MySQL 8.4 trong container. Mã nguồn vẫn là Laravel chuẩn, có thể chuyển sang hosting PHP/MySQL theo README-VI.md.

## Cài lần đầu

Cần Docker Engine và Docker Compose v2 (lệnh `docker compose`). Clone repo, vào `wedding-studio/deploy`, sao chép `.env.example` thành `.env`, đặt quyền 600.

Điền APP_KEY (base64: + 32 byte ngẫu nhiên encode base64), DB_PASSWORD, DB_ROOT_PASSWORD bằng các giá trị ngẫu nhiên khác nhau. Đặt APP_URL, BIND_ADDRESS, HTTP_PORT theo địa chỉ phục vụ. Mặc định Compose chỉ bind localhost; chỉ thay bind address khi muốn cho máy khác truy cập.

```sh
sudo docker compose -p ywd build --pull app
sudo docker compose -p ywd up -d
sudo docker compose -p ywd exec -u www-data app php artisan migrate --force
sudo docker compose -p ywd exec -u www-data app php artisan db:seed --class=StudioSeeder --force
sudo docker compose -p ywd exec -u www-data app php artisan studio:admin
sudo docker compose -p ywd exec -u www-data app php artisan optimize
```

Khi phục vụ qua IP nội bộ HTTP: SESSION_SECURE_COOKIE=false. Khi thêm domain/HTTPS: APP_URL=https://domain, SESSION_SECURE_COOKIE=true. Nếu có reverse proxy, cần cấu hình trusted proxies theo địa chỉ proxy thực tế trước khi bật HTTPS. Không public port MySQL; chỉ port HTTP của app được publish.

SMTP: thêm các biến MAIL_MAILER=smtp, MAIL_SCHEME, MAIL_HOST/PORT/USERNAME/PASSWORD/FROM_ADDRESS, CONTACT_MAIL_TO vào deploy/.env; chạy lại `docker compose -p ywd up -d app`, rồi `docker compose -p ywd exec -u www-data app php artisan config:cache`.

## Sao lưu

Chạy trong thư mục deploy (backup có dữ liệu riêng, chỉ lưu ở nơi được bảo vệ):

```sh
umask 077
mkdir -p backups
stamp=$(date +%Y%m%d-%H%M%S)
sudo docker compose -p ywd exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -u root --single-transaction --no-tablespaces wedding_studio' | gzip > "backups/database-$stamp.sql.gz"
sudo docker compose -p ywd exec -T app tar -czf - -C /var/www/html/public uploads > "backups/uploads-$stamp.tar.gz"
cp .env "backups/config-$stamp.env"
```

Kiểm tra file backup và thử khôi phục vào môi trường riêng trước khi dựa vào nó. Database và uploads nằm trong Docker named volumes; không chạy `docker compose down -v` vì sẽ xóa các volume đó.

## Cập nhật

Sao lưu trước. Trong repo chạy git pull --ff-only, sau đó vào deploy:

```sh
sudo docker compose -p ywd build --pull app
sudo docker compose -p ywd run --rm --no-deps -u www-data app php artisan migrate --force
sudo docker compose -p ywd up -d app
sudo docker compose -p ywd exec -u www-data app php artisan optimize
sudo docker compose -p ywd ps
```

APP_KEY và mật khẩu DB phải được giữ nguyên khi cập nhật. Các container tự chạy lại sau reboot nhờ restart: unless-stopped. Giữ cả Docker service ở trạng thái enabled.
