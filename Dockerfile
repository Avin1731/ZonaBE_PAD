# Gunakan image PHP resmi dengan Apache
FROM php:8.2-apache

# 1. Install library yang dibutuhkan Laravel & Composer
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl

# 2. Bersihkan cache apt agar image kecil
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# 3. Install Ekstensi PHP yang wajib
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# 4. Aktifkan Mod Rewrite Apache (biar URL cantik Laravel jalan)
RUN a2enmod rewrite

# 5. Ubah Document Root Apache ke folder /public (PENTING!)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 6. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 7. Set Working Directory
WORKDIR /var/www/html

# 8. Copy semua file project ke dalam container
COPY . .

# 9. Install Dependencies Laravel (tanpa dev tools biar ringan)
RUN composer install --no-dev --optimize-autoloader

# 10. Set Permission folder storage (biar Laravel bisa tulis log/cache)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 11. Expose Port 80 (Render mengharapkan port ini)
EXPOSE 80