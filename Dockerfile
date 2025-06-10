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

# Tạo thư mục ứng dụng trong /DoAn_BookStore
RUN mkdir -p /var/www/html/DoAn_BookStore

# Copy mã nguồn vào thư mục con DoAn_BookStore
COPY . /var/www/html/DoAn_BookStore/

# Copy cấu hình Apache
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Thiết lập quyền truy cập
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    find /var/www/html -type f -exec chmod 644 {} \;

# Mở cổng Apache
EXPOSE 80

# Khởi động Apache khi container chạy
CMD ["apache2-foreground"]
