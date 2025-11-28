-- Optimización de queries para monitor SOS
-- Fecha: 2025-10-27
-- Módulo: SOS Monitor - Actualización en tiempo real

-- Optimizar query de alarmas pendientes
-- Usado en sosindexStatusAction() para detectar nuevas alarmas
ALTER TABLE tbl_cot_09_alarmas_sensores_dispositivos
ADD INDEX idx_pending_alarms (aceptado, updated_at, finished_at, created_at)
COMMENT 'Optimiza query de alarmas pendientes para popups';

-- Optimizar JOIN con dispositivos
ALTER TABLE tbl_cot_09_alarmas_sensores_dispositivos
ADD INDEX idx_dispositivo (id_dispositivo)
COMMENT 'Optimiza JOIN con tabla dispositivos';

-- Optimizar query de dispositivos por tipo
ALTER TABLE tbl_cot_02_dispositivos
ADD INDEX idx_tipo_status (id_tipo, reg_status)
COMMENT 'Optimiza filtro por tipo y estado';

-- Verificar índices creados
SHOW INDEX FROM tbl_cot_09_alarmas_sensores_dispositivos WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM tbl_cot_02_dispositivos WHERE Key_name = 'idx_tipo_status';
