<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crea tabla app_settings para gestionar configuraciones del sistema de forma omnipotente
 */
final class Version20251105190516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea tabla app_settings para sistema de configuración omnipotente con soporte JSON, encriptación y categorización';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE app_settings (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(255) UNIQUE NOT NULL COMMENT \'Clave única de configuración\',
                value LONGTEXT COMMENT \'Valor de la configuración\',
                type VARCHAR(20) NOT NULL DEFAULT \'string\' COMMENT \'Tipo de dato: string, integer, boolean, json, encrypted\',
                category VARCHAR(100) DEFAULT \'general\' COMMENT \'Categoría de agrupación\',
                description TEXT COMMENT \'Descripción del parámetro\',
                is_public BOOLEAN DEFAULT 0 COMMENT \'Si es visible públicamente (API)\',
                created_at DATETIME NOT NULL COMMENT \'Fecha de creación\',
                updated_at DATETIME COMMENT \'Fecha de última actualización\',
                INDEX idx_category (category),
                INDEX idx_is_public (is_public),
                INDEX idx_key (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT=\'Configuraciones del sistema con soporte para múltiples tipos de datos\'
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS app_settings');
    }
}
