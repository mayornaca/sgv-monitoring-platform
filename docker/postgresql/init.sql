-- PostgreSQL Initialization Script for SGV
-- Creates dbpuente database for SIV entity manager

-- Database is created automatically by POSTGRES_DB env variable

-- Create additional databases if needed
CREATE DATABASE IF NOT EXISTS dbpuente_espiras_vs;

-- Grant privileges (postgres user has all privileges by default)

-- Note: Schema and stored functions will be created by Doctrine migrations
-- Run: docker-compose exec app php bin/console doctrine:migrations:migrate --em=siv
