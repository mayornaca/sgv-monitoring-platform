<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates webhook_log table for audit tracking of incoming webhooks
 */
final class Version20251127225133 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create webhook_log table for Alertmanager/Prometheus and WhatsApp webhook audit tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE webhook_log (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                source VARCHAR(50) NOT NULL,
                endpoint VARCHAR(255) NOT NULL,
                method VARCHAR(10) NOT NULL,
                headers JSON DEFAULT NULL,
                raw_payload LONGTEXT NOT NULL,
                parsed_data JSON DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(500) DEFAULT NULL,
                processing_status VARCHAR(20) NOT NULL DEFAULT \'received\',
                processing_result JSON DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                retry_count SMALLINT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                processed_at DATETIME DEFAULT NULL,
                concession_code VARCHAR(20) DEFAULT NULL,
                related_entity_type VARCHAR(50) DEFAULT NULL,
                related_entity_id INT DEFAULT NULL,
                meta_message_id VARCHAR(255) DEFAULT NULL,
                INDEX idx_source_created (source, created_at),
                INDEX idx_status (processing_status),
                INDEX idx_concession (concession_code),
                INDEX idx_created_at (created_at),
                INDEX idx_meta_message_id (meta_message_id),
                INDEX idx_related_entity (related_entity_type, related_entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS webhook_log');
    }
}