<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Agrega campo phone_number_used para trackear qué número se usó en cada envío (sistema de failover)
 */
final class Version20251106021500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega campo phone_number_used a whatsapp_messages para sistema de failover';
    }

    public function up(Schema $schema): void
    {
        // Agregar columna phone_number_used para trackear qué phone_number_id se usó
        $this->addSql('ALTER TABLE whatsapp_messages ADD COLUMN phone_number_used VARCHAR(50) DEFAULT NULL COMMENT \'ID del phone number usado (primary/backup)\'');

        // Agregar índice para queries de métricas
        $this->addSql('CREATE INDEX idx_phone_number_used ON whatsapp_messages(phone_number_used)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_phone_number_used ON whatsapp_messages');
        $this->addSql('ALTER TABLE whatsapp_messages DROP COLUMN phone_number_used');
    }
}
