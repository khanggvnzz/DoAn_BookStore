FROM php:8.2-apache

# Cài đặt tiện ích hệ thống và PHP extensions cần thiết
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Kích hoạt các module Apache cần thiết
RUN a2enmod rewrite ssl headers

# Cấu hình thư mục làm việc
WORKDIR /var/www/html

# Copy mã nguồn ứng dụng vào container
COPY . .

# Thiết lập quyền truy cập
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    find /var/www/html -type f -exec chmod 644 {} \;

# Mở cổng Apache (Render mặc định sẽ map cổng 80)
EXPOSE 80

# Khởi động Apache khi container chạy
CMD ["apache2-foreground"]
