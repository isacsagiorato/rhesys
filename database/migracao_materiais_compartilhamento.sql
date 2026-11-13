-- ============================================================
-- Rhesys - Migracao: materiais de compartilhamento (economia circular)
-- ------------------------------------------------------------
-- Estende a tabela compartilhamento_residuo para aceitar
-- "objetos, materiais ou residuos" independentes do catalogo de
-- residuos: nome, categoria, quantidade, estado de conservacao,
-- local de retirada, condicoes de retirada e imagem.
--
-- Script IDEMPOTENTE: pode ser executado mais de uma vez.
--
--   mysql -u root rhesys < database/migracao_materiais_compartilhamento.sql
-- ============================================================

USE rhesys;

-- 1) Torna id_residuo opcional (materiais nao precisam estar no catalogo)
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE()
        AND table_name = 'compartilhamento_residuo'
        AND column_name = 'id_residuo'
        AND is_nullable = 'YES') = 0,
    'ALTER TABLE compartilhamento_residuo MODIFY COLUMN id_residuo INT NULL',
    'SELECT ''id_residuo ja e opcional'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Colunas novas (uma a uma, sem erro se ja existirem)
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'compartilhamento_residuo' AND column_name = 'nome') = 0,
    'ALTER TABLE compartilhamento_residuo ADD COLUMN nome VARCHAR(150) NULL AFTER id_residuo',
    'SELECT ''coluna nome ja existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'compartilhamento_residuo' AND column_name = 'categoria') = 0,
    'ALTER TABLE compartilhamento_residuo ADD COLUMN categoria VARCHAR(100) NULL AFTER nome',
    'SELECT ''coluna categoria ja existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'compartilhamento_residuo' AND column_name = 'quantidade') = 0,
    'ALTER TABLE compartilhamento_residuo ADD COLUMN quantidade VARCHAR(100) NULL AFTER categoria',
    'SELECT ''coluna quantidade ja existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'compartilhamento_residuo' AND column_name = 'estado_conservacao') = 0,
    'ALTER TABLE compartilhamento_residuo ADD COLUMN estado_conservacao VARCHAR(50) NOT NULL DEFAULT ''usado'' AFTER quantidade',
    'SELECT ''coluna estado_conservacao ja existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'compartilhamento_residuo' AND column_name = 'localizacao') = 0,
    'ALTER TABLE compartilhamento_residuo ADD COLUMN localizacao VARCHAR(200) NULL AFTER estado_conservacao',
    'SELECT ''coluna localizacao ja existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'compartilhamento_residuo' AND column_name = 'condicoes_retirada') = 0,
    'ALTER TABLE compartilhamento_residuo ADD COLUMN condicoes_retirada TEXT NULL AFTER localizacao',
    'SELECT ''coluna condicoes_retirada ja existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'compartilhamento_residuo' AND column_name = 'imagem') = 0,
    'ALTER TABLE compartilhamento_residuo ADD COLUMN imagem VARCHAR(255) NULL AFTER condicoes_retirada',
    'SELECT ''coluna imagem ja existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Indices para os filtros
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'compartilhamento_residuo'
        AND index_name = 'idx_compartilhamento_categoria') = 0,
    'ALTER TABLE compartilhamento_residuo ADD INDEX idx_compartilhamento_categoria (categoria)',
    'SELECT ''indice categoria ja existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'compartilhamento_residuo'
        AND index_name = 'idx_compartilhamento_estado') = 0,
    'ALTER TABLE compartilhamento_residuo ADD INDEX idx_compartilhamento_estado (estado_conservacao)',
    'SELECT ''indice estado ja existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'compartilhamento_residuo'
        AND index_name = 'idx_compartilhamento_localizacao') = 0,
    'ALTER TABLE compartilhamento_residuo ADD INDEX idx_compartilhamento_localizacao (localizacao)',
    'SELECT ''indice localizacao ja existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;