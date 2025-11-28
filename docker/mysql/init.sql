-- MySQL Initialization Script for SGV
-- Creates default database and user if not exists

CREATE DATABASE IF NOT EXISTS gesvial_sgv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant privileges to sgv_user
GRANT ALL PRIVILEGES ON gesvial_sgv.* TO 'sgv_user'@'%';
FLUSH PRIVILEGES;

-- Note: Schema will be created by Doctrine migrations
-- Run: docker-compose exec app php bin/console doctrine:migrations:migrate
