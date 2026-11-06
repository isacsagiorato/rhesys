-- ============================================================
-- Rhesys - Migração: remove resíduos duplicados e impede novos
-- ============================================================
-- Contexto: o lote 2 (residuos_lote2.sql) usava INSERT simples e,
-- se executado mais de uma vez, duplicava resíduos (mesmo nome em
-- IDs diferentes). Esta migração:
--   1) reatribui vínculos dos registros duplicados ao ID mantido;
--   2) remove os duplicados, mantendo o MENOR id de cada nome;
--   3) cria a constraint UNIQUE em residuo.nome.
-- Pode ser executada mais de uma vez sem efeitos colaterais.
-- Faça backup antes: mysqldump -u root rhesys residuo > backup.sql
-- ============================================================
USE rhesys;

-- 1a) Compartilhamentos apontando para IDs duplicados passam a
-- apontar para o registro mantido (menor id do mesmo nome)
UPDATE compartilhamento_residuo c
INNER JOIN residuo r_dup ON c.id_residuo = r_dup.id_residuo
INNER JOIN residuo r_keep
        ON r_keep.nome = r_dup.nome AND r_keep.id_residuo < r_dup.id_residuo
SET c.id_residuo = r_keep.id_residuo;

-- 1b) Vínculos de pontos de coleta também são copiados para o ID
-- mantido (INSERT IGNORE evita duplicar o par resíduo + ponto)
INSERT IGNORE INTO residuo_ponto_coleta (id_residuo, id_ponto)
SELECT r_keep.id_residuo, rpc.id_ponto
FROM residuo_ponto_coleta rpc
INNER JOIN residuo r_dup ON rpc.id_residuo = r_dup.id_residuo
INNER JOIN residuo r_keep
        ON r_keep.nome = r_dup.nome AND r_keep.id_residuo < r_dup.id_residuo;

-- 2) Remove os resíduos duplicados (mantém o menor id de cada nome)
DELETE r FROM residuo r
INNER JOIN residuo r2 ON r.nome = r2.nome AND r.id_residuo > r2.id_residuo;

-- 3) Cria a UNIQUE em nome apenas se ainda não existir
SET @existe_unique := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'residuo'
      AND INDEX_NAME = 'uq_residuo_nome'
);
SET @sql := IF(@existe_unique = 0,
    'ALTER TABLE residuo ADD UNIQUE KEY uq_residuo_nome (nome)',
    'SELECT ''uq_residuo_nome ja existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
