<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Agrega configuración para URL de Grafana en app_settings
 */
final class Version20251105202508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega configuración grafana.url al sistema de configuración';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            INSERT INTO app_settings (`key`, value, type, category, description, is_public, created_at)
            VALUES (
                'grafana.url',
                'http://127.0.0.1:3000',
                'string',
                'integrations',
                'URL del servidor Grafana para proxy HTTP',
                0,
                NOW()
            )
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_settings WHERE `key` = 'grafana.url'");
    }
}
