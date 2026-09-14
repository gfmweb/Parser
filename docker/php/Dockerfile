FROM php:8.4-fpm-bookworm

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        bash \
        libfcgi-bin \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions \
        @composer \
        pdo_pgsql \
        pgsql \
        redis \
        intl \
        zip \
        bcmath \
        opcache \
        pcntl \
        sockets \
        pcov

# Контейнер запускается с UID хоста — php-fpm не должен переключать user.
RUN sed -i 's/^user = www-data/;user = www-data/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^group = www-data/;group = www-data/' /usr/local/etc/php-fpm.d/www.conf

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/zz-health.conf /usr/local/etc/php-fpm.d/zz-health.conf

WORKDIR /var/www/html

CMD ["php-fpm"]
