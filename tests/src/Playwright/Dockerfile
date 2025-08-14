FROM registry.gitlab.com/drupal-infrastructure/drupalci/drupalci-environments/php-8.3-apache:production

RUN mkdir -p /app/

WORKDIR /app

RUN npm init playwright@latest -y

RUN npx playwright install --with-deps
