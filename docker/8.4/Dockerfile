FROM ubuntu:22.04

ARG WWWUSER
ARG WWWGROUP

# Создание пользователя sail
RUN groupadd --force -g ${WWWGROUP} sail && \
    useradd -ms /bin/bash --no-user-group -g ${WWWGROUP} -u ${WWWUSER} sail

LABEL maintainer="Олег Laravel 12 + PHP 8.4 + Imagick"

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# Добавляем PPA с PHP 8.4
RUN apt-get update && apt-get install -y \
    software-properties-common curl lsb-release && \
    add-apt-repository ppa:ondrej/php -y && \
    apt-get update

# Установка PHP 8.4 и модулей
RUN apt-get install -y \
    php8.4 php8.4-cli php8.4-dev php8.4-common \
    php8.4-mysql php8.4-mbstring php8.4-xml php8.4-zip php8.4-bcmath \
    php8.4-soap php8.4-intl php8.4-readline php8.4-curl \
    php8.4-imagick \
    imagemagick libmagickwand-dev \
    libimage-exiftool-perl \
    git unzip zip nano curl wget ffmpeg \
    mysql-client sqlite3 supervisor libcap2-bin \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Установка Node.js, Yarn, Bun, PNPM
ARG NODE_VERSION=20
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g npm yarn pnpm bun

# Установка Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Рабочая директория
WORKDIR /var/www/html

# Копируем скрипт запуска и конфиг supervisor (если нужно)
COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/8.4/cli/conf.d/99-sail.ini

RUN chmod +x /usr/local/bin/start-container

EXPOSE 80

ENTRYPOINT ["start-container"]
