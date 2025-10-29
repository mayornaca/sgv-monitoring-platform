-- =================================================================
-- SCRIPT DE DEPLOYMENT PARA PRODUCCIÓN
-- Fecha: 2025-10-29
-- Descripción: Crea todas las tablas nuevas del sistema
-- =================================================================

-- IMPORTANTE: Ejecutar este script en la base de datos de PRODUCCIÓN
-- Hacer BACKUP antes de ejecutar

SET FOREIGN_KEY_CHECKS=0;

-- =================================================================
-- 1. TABLA: audit_log
-- Descripción: Sistema de auditoría para tracking de acciones
-- =================================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    username VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    level VARCHAR(20) DEFAULT 'INFO' NOT NULL,
    source VARCHAR(100) DEFAULT NULL,
    INDEX idx_audit_created_at (created_at),
    INDEX idx_audit_user_id (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity_type (entity_type),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- =================================================================
-- 2. TABLA: alerts
-- Descripción: Sistema de alertas del monitoreo
-- =================================================================
CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT NOT NULL,
    source_type VARCHAR(50) NOT NULL,
    source_id VARCHAR(100) DEFAULT NULL,
    alert_type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    data JSON DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    acknowledged_by INT DEFAULT NULL,
    acknowledged_at DATETIME DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    notification_count INT NOT NULL DEFAULT 0,
    escalation_level INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    INDEX idx_source (source_type, source_id),
    INDEX idx_alert_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- =================================================================
-- 3. TABLA: alert_rules
-- Descripción: Reglas de notificación para alertas
-- =================================================================
CREATE TABLE IF NOT EXISTS alert_rules (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(255) NOT NULL,
    source_type VARCHAR(50) NOT NULL,
    alert_type VARCHAR(50) NOT NULL,
    severity_levels JSON NOT NULL,
    notification_channels JSON NOT NULL,
    escalation_config JSON DEFAULT NULL,
    recipients JSON NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    description LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    INDEX idx_source_type (source_type),
    INDEX idx_alert_type (alert_type),
    INDEX idx_is_active (is_active),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- =================================================================
-- 4. TABLA: notification_logs
-- Descripción: Log de notificaciones enviadas
-- =================================================================
CREATE TABLE IF NOT EXISTS notification_logs (
    id INT AUTO_INCREMENT NOT NULL,
    alert_id INT DEFAULT NULL,
    channel VARCHAR(50) NOT NULL,
    recipient VARCHAR(255) DEFAULT NULL,
    message LONGTEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    error_message LONGTEXT DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_alert_id (alert_id),
    INDEX idx_channel (channel),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- =================================================================
-- 5. TABLA: otp_codes
-- Descripción: Códigos OTP para autenticación de dos factores
-- =================================================================
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    code VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_user_id (user_id),
    INDEX idx_code (code),
    INDEX idx_expires_at (expires_at),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- =================================================================
-- 6. TABLAS DE WHATSAPP
-- =================================================================

-- 6.1. Tabla de destinatarios
CREATE TABLE IF NOT EXISTS whatsapp_recipients (
    id INT AUTO_INCREMENT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    notas VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY(id),
    INDEX idx_telefono (telefono),
    INDEX idx_activo (activo)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- 6.2. Tabla de grupos de destinatarios
CREATE TABLE IF NOT EXISTS whatsapp_recipient_groups (
    id INT AUTO_INCREMENT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    descripcion LONGTEXT DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY(id),
    UNIQUE INDEX UNIQ_slug (slug)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- 6.3. Tabla pivot grupos-destinatarios
CREATE TABLE IF NOT EXISTS whatsapp_group_recipients (
    recipient_group_id INT NOT NULL,
    recipient_id INT NOT NULL,
    INDEX IDX_group (recipient_group_id),
    INDEX IDX_recipient (recipient_id),
    PRIMARY KEY(recipient_group_id, recipient_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- 6.4. Tabla de templates
CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id INT AUTO_INCREMENT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    meta_template_id VARCHAR(255) NOT NULL,
    descripcion LONGTEXT DEFAULT NULL,
    parametros_count SMALLINT NOT NULL DEFAULT 0,
    parametros_descripcion JSON DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    language VARCHAR(10) NOT NULL DEFAULT 'es',
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY(id),
    UNIQUE INDEX UNIQ_nombre (nombre)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- 6.5. Tabla de mensajes
CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id INT AUTO_INCREMENT NOT NULL,
    recipient_id INT NOT NULL,
    template_id INT DEFAULT NULL,
    mensaje_texto LONGTEXT DEFAULT NULL,
    parametros JSON DEFAULT NULL,
    meta_message_id VARCHAR(255) DEFAULT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'pending',
    error_message LONGTEXT DEFAULT NULL,
    meta_response JSON DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    read_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    retry_count SMALLINT NOT NULL DEFAULT 0,
    context VARCHAR(50) DEFAULT NULL,
    INDEX IDX_recipient (recipient_id),
    INDEX IDX_template (template_id),
    INDEX idx_meta_message_id (meta_message_id),
    INDEX idx_estado (estado),
    INDEX idx_created_at (created_at),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- =================================================================
-- 7. FOREIGN KEYS DE WHATSAPP
-- =================================================================
-- Nota: Usamos IF NOT EXISTS simulado verificando primero

-- Verificar si las FK ya existen antes de crearlas
SET @exist := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'whatsapp_group_recipients'
    AND CONSTRAINT_NAME = 'FK_group_recipient_group'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE whatsapp_group_recipients ADD CONSTRAINT FK_group_recipient_group FOREIGN KEY (recipient_group_id) REFERENCES whatsapp_recipient_groups (id) ON DELETE CASCADE',
    'SELECT "FK FK_group_recipient_group already exists" AS Status');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'whatsapp_group_recipients'
    AND CONSTRAINT_NAME = 'FK_group_recipient'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE whatsapp_group_recipients ADD CONSTRAINT FK_group_recipient FOREIGN KEY (recipient_id) REFERENCES whatsapp_recipients (id) ON DELETE CASCADE',
    'SELECT "FK FK_group_recipient already exists" AS Status');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'whatsapp_messages'
    AND CONSTRAINT_NAME = 'FK_message_recipient'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE whatsapp_messages ADD CONSTRAINT FK_message_recipient FOREIGN KEY (recipient_id) REFERENCES whatsapp_recipients (id)',
    'SELECT "FK FK_message_recipient already exists" AS Status');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'whatsapp_messages'
    AND CONSTRAINT_NAME = 'FK_message_template'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE whatsapp_messages ADD CONSTRAINT FK_message_template FOREIGN KEY (template_id) REFERENCES whatsapp_templates (id)',
    'SELECT "FK FK_message_template already exists" AS Status');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =================================================================
-- 8. AGREGAR COLUMNAS 2FA A security_user
-- =================================================================

-- 8.1. Columna totp_secret
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'security_user'
    AND COLUMN_NAME = 'totp_secret');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE security_user ADD COLUMN totp_secret VARCHAR(255) DEFAULT NULL',
    'SELECT "Column totp_secret already exists" AS Status');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 8.2. Columna two_factor_enabled
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'security_user'
    AND COLUMN_NAME = 'two_factor_enabled');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE security_user ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT "Column two_factor_enabled already exists" AS Status');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 8.3. Columna preferred2fa_method
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'security_user'
    AND COLUMN_NAME = 'preferred2fa_method');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE security_user ADD COLUMN preferred2fa_method VARCHAR(20) DEFAULT NULL',
    'SELECT "Column preferred2fa_method already exists" AS Status');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 8.4. Columna login_count
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'security_user'
    AND COLUMN_NAME = 'login_count');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE security_user ADD COLUMN login_count INT NOT NULL DEFAULT 0',
    'SELECT "Column login_count already exists" AS Status');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 8.5. Columna reset_token
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'security_user'
    AND COLUMN_NAME = 'reset_token');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE security_user ADD COLUMN reset_token VARCHAR(100) DEFAULT NULL',
    'SELECT "Column reset_token already exists" AS Status');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 8.6. Columna reset_token_expires_at
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'security_user'
    AND COLUMN_NAME = 'reset_token_expires_at');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE security_user ADD COLUMN reset_token_expires_at DATETIME DEFAULT NULL',
    'SELECT "Column reset_token_expires_at already exists" AS Status');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 8.7. Columna must_change_password
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'security_user'
    AND COLUMN_NAME = 'must_change_password');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE security_user ADD COLUMN must_change_password TINYINT(1) DEFAULT NULL',
    'SELECT "Column must_change_password already exists" AS Status');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS=1;

-- =================================================================
-- FIN DEL SCRIPT
-- =================================================================

SELECT 'Deployment script ejecutado exitosamente' AS Status;
SELECT 'Verificar que todas las tablas se crearon correctamente' AS Nota;
