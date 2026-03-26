FROM php:8.2-cli

WORKDIR /app

RUN docker-php-ext-install pdo pdo_mysql

COPY . .

RUN mkdir -p uploads

EXPOSE 10000

CMD sh -c "php -S 0.0.0.0:${PORT:-10000} index.php"
