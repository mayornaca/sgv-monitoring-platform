<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sistema de gestión de WhatsApp con Meta Business API
 * - Destinatarios y grupos
 * - Templates de mensajes
 * - Tracking de mensajes enviados
 */
final class Version20251023184500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crear tablas para sistema de WhatsApp con Meta Business API';
    }

    public function up(Schema $schema): void
    {
        // Tabla de destinatarios
        $this->addSql('CREATE TABLE whatsapp_recipients (
            id INT AUTO_INCREMENT NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            telefono VARCHAR(20) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            notas VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            INDEX idx_telefono (telefono),
            INDEX idx_activo (activo)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Tabla de grupos de destinatarios
        $this->addSql('CREATE TABLE whatsapp_recipient_groups (
            id INT AUTO_INCREMENT NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            descripcion LONGTEXT DEFAULT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            UNIQUE INDEX UNIQ_slug (slug)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Tabla pivot grupos-destinatarios
        $this->addSql('CREATE TABLE whatsapp_group_recipients (
            recipient_group_id INT NOT NULL,
            recipient_id INT NOT NULL,
            INDEX IDX_group (recipient_group_id),
            INDEX IDX_recipient (recipient_id),
            PRIMARY KEY(recipient_group_id, recipient_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Tabla de templates
        $this->addSql('CREATE TABLE whatsapp_templates (
            id INT AUTO_INCREMENT NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            meta_template_id VARCHAR(255) NOT NULL,
            descripcion LONGTEXT DEFAULT NULL,
            parametros_count SMALLINT NOT NULL DEFAULT 0,
            parametros_descripcion JSON DEFAULT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            language VARCHAR(10) NOT NULL DEFAULT \'es\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            UNIQUE INDEX UNIQ_nombre (nombre)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Tabla de mensajes
        $this->addSql('CREATE TABLE whatsapp_messages (
            id INT AUTO_INCREMENT NOT NULL,
            recipient_id INT NOT NULL,
            template_id INT DEFAULT NULL,
            mensaje_texto LONGTEXT DEFAULT NULL,
            parametros JSON DEFAULT NULL,
            meta_message_id VARCHAR(255) DEFAULT NULL,
            estado VARCHAR(20) NOT NULL DEFAULT \'pending\',
            error_message LONGTEXT DEFAULT NULL,
            meta_response JSON DEFAULT NULL,
            sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            retry_count SMALLINT NOT NULL DEFAULT 0,
            context VARCHAR(50) DEFAULT NULL,
            INDEX IDX_recipient (recipient_id),
            INDEX IDX_template (template_id),
            INDEX idx_meta_message_id (meta_message_id),
            INDEX idx_estado (estado),
            INDEX idx_created_at (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Foreign keys
        $this->addSql('ALTER TABLE whatsapp_group_recipients
            ADD CONSTRAINT FK_group_recipient_group
            FOREIGN KEY (recipient_group_id) REFERENCES whatsapp_recipient_groups (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE whatsapp_group_recipients
            ADD CONSTRAINT FK_group_recipient
            FOREIGN KEY (recipient_id) REFERENCES whatsapp_recipients (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE whatsapp_messages
            ADD CONSTRAINT FK_message_recipient
            FOREIGN KEY (recipient_id) REFERENCES whatsapp_recipients (id)');

        $this->addSql('ALTER TABLE whatsapp_messages
            ADD CONSTRAINT FK_message_template
            FOREIGN KEY (template_id) REFERENCES whatsapp_templates (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE whatsapp_group_recipients DROP FOREIGN KEY FK_group_recipient_group');
        $this->addSql('ALTER TABLE whatsapp_group_recipients DROP FOREIGN KEY FK_group_recipient');
        $this->addSql('ALTER TABLE whatsapp_messages DROP FOREIGN KEY FK_message_recipient');
        $this->addSql('ALTER TABLE whatsapp_messages DROP FOREIGN KEY FK_message_template');

        $this->addSql('DROP TABLE whatsapp_messages');
        $this->addSql('DROP TABLE whatsapp_templates');
        $this->addSql('DROP TABLE whatsapp_group_recipients');
        $this->addSql('DROP TABLE whatsapp_recipient_groups');
        $this->addSql('DROP TABLE whatsapp_recipients');
    }
}
