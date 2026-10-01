# ==========================================
# Stage 1: Build Frontend Assets (Vite + React)
# ==========================================
FROM node:22-alpine AS frontend
WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .

# Build Vite frontend assets (uses fallback if VITE envs are not passed at build)
ARG VITE_SUPABASE_URL
ARG VITE_SUPABASE_ANON_KEY
ARG VITE_APP_NAME="SMKI"
ENV VITE_SUPABASE_URL=$VITE_SUPABASE_URL
ENV VITE_SUPABASE_ANON_KEY=$VITE_SUPABASE_ANON_KEY
ENV VITE_APP_NAME=$VITE_APP_NAME

RUN npm run build

# ==========================================
# Stage 2: Production PHP Runtime (FrankenPHP)
# ==========================================
FROM dunglas/frankenphp:1-php8.4-alpine

WORKDIR /app

# Install system dependencies:
# - Node.js + npm: required at runtime by spatie/browsershot to generate PDFs via Chromium
# - Chromium + fonts: headless browser for PDF rendering
# - libcap: to strip capabilities from frankenphp binary for unprivileged containers
RUN apk add --no-cache \
    nodejs \
    npm \
    chromium \
    nss \
    freetype \
    harfbuzz \
    ca-certificates \
    ttf-freefont \
    libcap

# Remove capabilities from frankenphp binary so it can run in unprivileged containers (Render, etc.)
RUN setcap -r /usr/local/bin/frankenphp

# Install puppeteer globally (skip Chromium download — we use system Chromium above)
# spatie/browsershot requires puppeteer to be available globally via NODE_PATH
ENV PUPPETEER_SKIP_DOWNLOAD=true
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium
RUN npm install -g puppeteer --unsafe-perm

# Install required PHP extensions for Laravel & PostgreSQL & Excel
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    gd \
    zip \
    bcmath \
    intl \
    opcache

# Copy Composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application source code
COPY . /app

# Copy compiled frontend assets from Stage 1
COPY --from=frontend /app/public/build /app/public/build

# Ensure required storage and cache directories exist before composer scripts run
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# Install production PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Re-ensure permissions after composer install
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# Normalize line endings (CRLF -> LF) and make entrypoint executable
RUN tr -d '\r' < /app/docker/entrypoint.sh > /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
