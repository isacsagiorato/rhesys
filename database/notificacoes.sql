-- Sistema de notificações (sino no site + e-mail).
-- Para bancos que já existiam antes desta versão. Pode rodar mais de uma vez.

USE rhesys;

-- ------------------------------------------------------------
-- Tabela: NOTIFICACAO
-- Avisos do sistema para cada usuário (sino no topo do site).
-- As notificações importantes também são enviadas por e-mail;
-- email_enviado guarda se o envio já foi feito.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notificacao (
    id_notificacao INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    mensagem TEXT NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    lida BOOLEAN NOT NULL DEFAULT FALSE,
    enviar_email BOOLEAN NOT NULL DEFAULT FALSE,
    email_enviado BOOLEAN NOT NULL DEFAULT FALSE,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notificacao_usuario (id_usuario, lida, data_criacao),
    CONSTRAINT fk_notificacao_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
