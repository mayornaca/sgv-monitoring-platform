<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Agrega configuración para URLs de Prometheus y Alertmanager en app_settings
 */
final class Version20251124085903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega configuración prometheus.url y alertmanager.url al sistema de configuración';
    }

    public function up(Schema $schema): void
    {
        // Prometheus URL
        $this->addSql("
            INSERT INTO app_settings (`key`, value, type, category, description, is_public, created_at)
            VALUES (
                'prometheus.url',
                'http://127.0.0.1:9090',
                'string',
                'integrations',
                'URL del servidor Prometheus',
                0,
                NOW()
            )
        ");

        // Alertmanager URL
        $this->addSql("
            INSERT INTO app_settings (`key`, value, type, category, description, is_public, created_at)
            VALUES (
                'alertmanager.url',
                'http://127.0.0.1:9093',
                'string',
                'integrations',
                'URL del servidor Alertmanager',
                0,
                NOW()
            )
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_settings WHERE `key` = 'prometheus.url'");
        $this->addSql("DELETE FROM app_settings WHERE `key` = 'alertmanager.url'");
    }
}
