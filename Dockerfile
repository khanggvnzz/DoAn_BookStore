FROM php:8.2-apache

# Cài đặt phụ thuộc cần thiết
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    && docker-php-ext-install pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Kích hoạt module rewrite cho Apache
RUN a2enmod rewrite

# Sao chép mã nguồn vào container
WORKDIR /var/www/html
COPY . .

# Cài đặt Composer và phụ thuộc PHP (nếu có)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

# Cấp quyền cho thư mục
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Cấu hình Apache để sử dụng cổng từ biến môi trường
ENV PORT=10000
RUN echo "Listen \${PORT}" > /etc/apache2/ports.conf \
    && echo "<VirtualHost *:\${PORT}>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    </Directory>\n\
    ErrorLog \${APACHE_LOG_DIR}/error.log\n\
    CustomLog \${APACHE_LOG_DIR}/access.log combined\n\
    </VirtualHost>" > /etc/apache2/sites-available/000-default.conf

# Mở cổng
EXPOSE $PORT

# Khởi động Apache
CMD ["apache2-foreground"]