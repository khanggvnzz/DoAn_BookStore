FROM php:8.2-apache

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

RUN a2enmod rewrite ssl headers

# Tạo thư mục và copy mã nguồn vào
RUN mkdir -p /var/www/html/DoAn_BookStore
COPY . /var/www/html/DoAn_BookStore/

# Copy cấu hình Apache đã chỉnh DocumentRoot
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Thiết lập quyền truy cập
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80
CMD ["apache2-foreground"]
