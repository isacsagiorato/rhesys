-- Migração não destrutiva das sprints 3 a 6.
-- As demais estruturas já existem no modelo original; este índice impede
-- que o mesmo usuário registre mais de um interesse na mesma oferta.
USE rhesys;

SET @indice_interesse := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'interesse_compartilhamento'
      AND index_name = 'uq_interesse_oferta_usuario'
);
SET @sql_interesse := IF(
    @indice_interesse = 0,
    'ALTER TABLE interesse_compartilhamento ADD UNIQUE KEY uq_interesse_oferta_usuario (id_compartilhamento, id_usuario)',
    'SELECT ''Índice uq_interesse_oferta_usuario já existe'' AS resultado'
);
PREPARE stmt FROM @sql_interesse;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
