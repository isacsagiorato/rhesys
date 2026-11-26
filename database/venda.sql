-- Doação ou venda nos compartilhamentos.
-- Para bancos que já existiam antes desta versão. Pode rodar mais de uma vez.
-- Os materiais já cadastrados ficam como doação.

USE rhesys;

ALTER TABLE compartilhamento_residuo
    ADD COLUMN IF NOT EXISTS tipo_oferta VARCHAR(20) NOT NULL DEFAULT 'doacao' AFTER status,
    ADD COLUMN IF NOT EXISTS preco DECIMAL(10,2) NULL AFTER tipo_oferta,
    ADD COLUMN IF NOT EXISTS preco_negociavel BOOLEAN NOT NULL DEFAULT FALSE AFTER preco;

CREATE INDEX IF NOT EXISTS idx_compartilhamento_tipo ON compartilhamento_residuo (tipo_oferta);
