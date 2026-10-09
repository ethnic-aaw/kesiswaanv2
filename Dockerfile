FROM php:8.2-apache
RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev unzip \
 && docker-php-ext-install pdo_mysql mbstring zip \
 && rm -rf /var/lib/apt/lists/*
# ponytail: tanpa gd — phpspreadsheet xlsx jalan tanpa gd; add libpng/libjpeg-dev + ext gd bila perlu cetak gambar
# URL tetap /kesiswaanv2/... spt Laragon (docroot /var/www/html)
COPY . /var/www/html/kesiswaanv2
