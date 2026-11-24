FROM php:8.3-cli-alpine

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev oniguruma-dev \
    && docker-php-ext-install pdo_pgsql mbstring \
    && apk del .build-deps \
    && apk add --no-cache libpq oniguruma

WORKDIR /app
COPY backend/ /app/backend/
EXPOSE 10000
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t /app/backend /app/backend/index.php"]
