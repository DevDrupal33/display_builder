# This Image target usage in Drupal Gitlab-CI.
FROM registry.gitlab.com/drupal-infrastructure/drupalci/drupalci-environments/php-8.3-apache:production

RUN mkdir -p /app/

WORKDIR /app

RUN npx -y playwright@1.56.1 install --with-deps
