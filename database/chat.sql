-- Chat entre quem ofereceu e o interessado (compartilhamentos).
-- Para bancos que já existiam antes desta versão. Pode rodar mais de uma vez.

USE rhesys;

-- ------------------------------------------------------------
-- Tabela: MENSAGEM
-- Chat entre quem ofereceu o material e cada interessado.
-- Cada conversa pertence a um interesse (oferta + interessado).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mensagem (
    id_mensagem INT AUTO_INCREMENT PRIMARY KEY,
    id_interesse INT NOT NULL,
    id_remetente INT NOT NULL,
    texto TEXT NOT NULL,
    imagem VARCHAR(255) DEFAULT NULL,
    apagada BOOLEAN NOT NULL DEFAULT FALSE,
    lida BOOLEAN NOT NULL DEFAULT FALSE,
    data_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mensagem_conversa (id_interesse, id_mensagem),
    INDEX idx_mensagem_nao_lidas (id_interesse, lida, id_remetente),
    CONSTRAINT fk_mensagem_interesse
        FOREIGN KEY (id_interesse) REFERENCES interesse_compartilhamento(id_interesse)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_mensagem_remetente
        FOREIGN KEY (id_remetente) REFERENCES usuario(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fotos no chat (bancos criados antes das fotos)
ALTER TABLE mensagem ADD COLUMN IF NOT EXISTS imagem VARCHAR(255) DEFAULT NULL AFTER texto;

-- Mensagens apagadas (bancos criados antes desta opção)
ALTER TABLE mensagem ADD COLUMN IF NOT EXISTS apagada BOOLEAN NOT NULL DEFAULT FALSE AFTER imagem;
