FROM php:8.1-apache

# Copy source code vào thư mục chứa web
COPY . /var/www/html/

# Cấp quyền cho thư mục nếu cần
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80