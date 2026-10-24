-- ============================================================
-- Rhesys - Migração: login com Google (OAuth 2.0)
-- ------------------------------------------------------------
-- Executar UMA vez em bancos já criados com versões anteriores
-- do rhesys_banco_dados.sql (o script principal já cria estas
-- colunas em instalações novas).
--
--   mysql -u root < database/migracao_google.sql
--
-- Ou pelo phpMyAdmin: selecione o banco "rhesys" e cole o ALTER abaixo.
-- ============================================================

USE rhesys;

ALTER TABLE usuario
    ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER senha,
    ADD COLUMN email_verificado TINYINT(1) NOT NULL DEFAULT 0 AFTER google_id,
    ADD COLUMN avatar VARCHAR(500) NULL AFTER email_verificado;

    -- Índice: o UNIQUE do google_id (acima) já cria o índice
    -- de busca — nenhum índice extra é necessário.
