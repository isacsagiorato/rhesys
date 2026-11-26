-- ============================================================
-- Rhesys - Script de criação do banco de dados
-- SGBD: MySQL 8.0+
-- Baseado no modelo conceitual (BRModeloWeb) e requisitos (RF01-RF13)
-- ============================================================
--
-- Entidades do modelo conceitual:
--   Usuario, Residuo, Classificacao,
--   Compartilhamento_Residuo, Interesse_Compartilhamento,
--   Conteudo_Educativo, Quiz, Pergunta, Resultado_Quiz
--
-- Relacionamentos:
--   classifica    (1,1)-(1,n)  Classificacao -> Residuo
--   Oferta        (0,n)-(1,1)  Usuario -> Compartilhamento_Residuo
--   demonstra     (0,n)-(0,n)  Usuario -> Interesse_Compartilhamento
--   aceita        (0,n)-(0,n)  Compartilhamento_Residuo -> Interesse_Compartilhamento
--   contem        (0,n)-(1,1)  Quiz -> Pergunta
--   realiza/gera  (0,n)-(0,n)  Usuario/Quiz -> Resultado_Quiz
-- ============================================================

CREATE DATABASE IF NOT EXISTS rhesys
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;
USE rhesys;

-- ------------------------------------------------------------
-- Limpa tabelas existentes (ordem reversa de dependência)
-- ------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS tentativa_login;
DROP TABLE IF EXISTS resultado_quiz;
DROP TABLE IF EXISTS pergunta;
DROP TABLE IF EXISTS quiz;
DROP TABLE IF EXISTS conteudo_educativo;
DROP TABLE IF EXISTS interesse_compartilhamento;
DROP TABLE IF EXISTS compartilhamento_residuo;
DROP TABLE IF EXISTS residuo_ponto_coleta; -- tabela antiga (pontos de coleta, removidos)
DROP TABLE IF EXISTS ponto_coleta;         -- tabela antiga (pontos de coleta, removidos)
DROP TABLE IF EXISTS residuo;
DROP TABLE IF EXISTS classificacao;
DROP TABLE IF EXISTS usuario;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Tabela: USUARIO
-- Armazena dados de acesso e perfil dos usuários
-- ------------------------------------------------------------
CREATE TABLE usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    -- Colunas do antigo login com Google (removido). Ficam no banco por
    -- compatibilidade com bancos já existentes, mas não são mais usadas.
    google_id VARCHAR(255) NULL UNIQUE,
    email_verificado TINYINT(1) NOT NULL DEFAULT 0,
    avatar VARCHAR(500) NULL,
    tipo_usuario VARCHAR(50) NOT NULL DEFAULT 'comum',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabela: CLASSIFICACAO
-- Categorias de resíduos conforme ABNT NBR 10004/2024
-- ------------------------------------------------------------
CREATE TABLE classificacao (
    id_classificacao INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabela: RESIDUO
-- Materiais cadastrados para consulta (RF03-RF08)
-- Relacionamento "classifica": 1 classificação -> N resíduos
-- ------------------------------------------------------------
CREATE TABLE residuo (
    id_residuo INT AUTO_INCREMENT PRIMARY KEY,
    id_classificacao INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    forma_descarte TEXT,
    reciclagem TEXT,
    palavras_chave VARCHAR(1000),
    possui_logistica_reversa BOOLEAN NOT NULL DEFAULT FALSE,
    -- Tipo de material (metal, plastico, papel, vidro, organico, eletronico,
    -- especial, construcao, textil, rejeito) — ver config/residuos.php
    tipo_material VARCHAR(30) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_residuo_classificacao (id_classificacao),
    INDEX idx_residuo_tipo (tipo_material),
    -- O nome é único: impede duplicar o mesmo resíduo em cargas repetidas
    UNIQUE KEY uq_residuo_nome (nome),
    CONSTRAINT fk_residuo_classificacao
        FOREIGN KEY (id_classificacao) REFERENCES classificacao(id_classificacao)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Tabela: COMPARTILHAMENTO_RESIDUO
-- Materiais disponibilizados por usuários (relacionamento "Oferta")
-- ------------------------------------------------------------
CREATE TABLE compartilhamento_residuo (
    id_compartilhamento INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario_ofertante INT NOT NULL,
    id_residuo INT NULL,
    nome VARCHAR(150) NULL,
    categoria VARCHAR(100) NULL,
    quantidade VARCHAR(100) NULL,
    estado_conservacao VARCHAR(50) NOT NULL DEFAULT 'usado',
    localizacao VARCHAR(200) NULL,
    condicoes_retirada TEXT NULL,
    imagem VARCHAR(255) NULL,
    descricao TEXT,
    data_publicacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) NOT NULL DEFAULT 'disponivel',
    -- Doação (grátis) ou venda; na venda, preço e se aceita negociar
    tipo_oferta VARCHAR(20) NOT NULL DEFAULT 'doacao',
    preco DECIMAL(10,2) NULL,
    preco_negociavel BOOLEAN NOT NULL DEFAULT FALSE,
    INDEX idx_compartilhamento_usuario (id_usuario_ofertante),
    INDEX idx_compartilhamento_tipo (tipo_oferta),
    INDEX idx_compartilhamento_residuo (id_residuo),
    INDEX idx_compartilhamento_status (status),
    INDEX idx_compartilhamento_categoria (categoria),
    INDEX idx_compartilhamento_estado (estado_conservacao),
    INDEX idx_compartilhamento_localizacao (localizacao),
    CONSTRAINT fk_compartilhamento_usuario
        FOREIGN KEY (id_usuario_ofertante) REFERENCES usuario(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_compartilhamento_residuo
        FOREIGN KEY (id_residuo) REFERENCES residuo(id_residuo)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_compartilhamento_status
        CHECK (status IN ('disponivel', 'reservado', 'concluido', 'cancelado')),
    CONSTRAINT chk_compartilhamento_tipo
        CHECK (tipo_oferta IN ('doacao', 'venda')),
    CONSTRAINT chk_compartilhamento_preco
        CHECK (preco IS NULL OR preco > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabela: INTERESSE_COMPARTILHAMENTO
-- Usuários interessados em compartilhamentos
-- (relacionamentos "demonstra" e "aceita")
-- ------------------------------------------------------------
CREATE TABLE interesse_compartilhamento (
    id_interesse INT AUTO_INCREMENT PRIMARY KEY,
    id_compartilhamento INT NOT NULL,
    id_usuario INT NOT NULL,
    data_interesse DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) NOT NULL DEFAULT 'pendente',
    INDEX idx_interesse_compartilhamento (id_compartilhamento),
    INDEX idx_interesse_usuario (id_usuario),
    UNIQUE KEY uq_interesse_oferta_usuario (id_compartilhamento, id_usuario),
    CONSTRAINT fk_interesse_compartilhamento
        FOREIGN KEY (id_compartilhamento) REFERENCES compartilhamento_residuo(id_compartilhamento)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_interesse_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_interesse_status
        CHECK (status IN ('pendente', 'aceito', 'recusado', 'cancelado'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabela: MENSAGEM
-- Chat entre quem ofereceu o material e cada interessado.
-- Cada conversa pertence a um interesse (oferta + interessado).
-- ------------------------------------------------------------
CREATE TABLE mensagem (
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

-- ------------------------------------------------------------
-- Tabela: CONTEUDO_EDUCATIVO
-- Materiais informativos de educação ambiental (RF09)
-- ------------------------------------------------------------
CREATE TABLE conteudo_educativo (
    id_conteudo INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    texto TEXT NOT NULL,
    imagem VARCHAR(255),
    url VARCHAR(255),
    data_publicacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabela: QUIZ
-- Questionários educativos (RF10/RF11)
-- ------------------------------------------------------------
CREATE TABLE quiz (
    id_quiz INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabela: PERGUNTA
-- Questões de cada quiz (relacionamento "contem")
-- ------------------------------------------------------------
CREATE TABLE pergunta (
    id_pergunta INT AUTO_INCREMENT PRIMARY KEY,
    id_quiz INT NOT NULL,
    enunciado TEXT NOT NULL,
    alternativa_a VARCHAR(255) NOT NULL,
    alternativa_b VARCHAR(255) NOT NULL,
    alternativa_c VARCHAR(255) NOT NULL,
    alternativa_d VARCHAR(255) NOT NULL,
    resposta_correta CHAR(1) NOT NULL,
    INDEX idx_pergunta_quiz (id_quiz),
    CONSTRAINT fk_pergunta_quiz
        FOREIGN KEY (id_quiz) REFERENCES quiz(id_quiz)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_resposta_correta
        CHECK (resposta_correta IN ('a', 'b', 'c', 'd'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabela: RESULTADO_QUIZ
-- Desempenho dos usuários nos quizzes (relacionamentos "realiza"/"gera")
-- ------------------------------------------------------------
CREATE TABLE resultado_quiz (
    id_resultado INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_quiz INT NOT NULL,
    pontuacao INT NOT NULL DEFAULT 0,
    data_realizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_resultado_usuario (id_usuario),
    INDEX idx_resultado_quiz (id_quiz),
    CONSTRAINT fk_resultado_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_resultado_quiz
        FOREIGN KEY (id_quiz) REFERENCES quiz(id_quiz)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_pontuacao
        CHECK (pontuacao >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabela: TENTATIVA_LOGIN
-- Controle de tentativas de login (rate limiting).
-- A chave é um hash SHA-256 de IP + e-mail (nada sensível em texto puro).
-- Após LOGIN_MAX_TENTATIVAS falhas, bloqueado_ate é preenchido e o
-- acesso é recusado até essa data/hora (15 minutos).
-- ------------------------------------------------------------
CREATE TABLE tentativa_login (
    chave_tentativa VARCHAR(64) NOT NULL PRIMARY KEY,
    tentativas INT NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME DEFAULT NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabela: NOTIFICACAO
-- Avisos do sistema para cada usuário (sino no topo do site).
-- As notificações importantes também são enviadas por e-mail;
-- email_enviado guarda se o envio já foi feito.
-- ------------------------------------------------------------
CREATE TABLE notificacao (
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

-- ============================================================
-- DADOS INICIAIS (SEED)
-- ============================================================

-- ------------------------------------------------------------
-- Classificações conforme ABNT NBR 10004/2024
-- ------------------------------------------------------------
INSERT INTO classificacao (nome, descricao) VALUES
('Classe I - Perigosos',
 'Resíduos que apresentam características de inflamabilidade, corrosividade, toxicidade, reatividade ou patogenicidade, exigindo cuidados especiais no gerenciamento.'),
('Classe II A - Não Inertes',
 'Resíduos que podem sofrer alterações ao longo do tempo, apresentando biodegradabilidade, combustibilidade ou solubilidade em água. Ex: restos de alimentos, papel, papelão.'),
('Classe II B - Inertes',
 'Resíduos que não apresentam transformações significativas em contato com a água. Ex: entulhos da construção civil, concreto, tijolos, materiais cerâmicos.');

-- ------------------------------------------------------------
-- Usuário administrador de teste (senha: admin123)
-- RF11: a senha é armazenada APENAS como hash bcrypt, nunca em
-- texto puro. Hash gerado com password_hash('admin123', PASSWORD_DEFAULT)
-- no PHP e verificado no login com password_verify().
-- ------------------------------------------------------------
INSERT INTO usuario (nome, email, senha, tipo_usuario) VALUES
('Administrador', 'admin@rhesys.com', '$2y$10$tHJdxxXrKO8OwGAQjUQ2x.3kFNrTGHvWqCYKPJwiqwmlUT9f6sN4G', 'admin');

-- ------------------------------------------------------------
-- Resíduos de exemplo
-- ------------------------------------------------------------
INSERT INTO residuo (id_classificacao, nome, descricao, forma_descarte, reciclagem, palavras_chave, possui_logistica_reversa) VALUES
(1, 'Pilha',
 'Pilhas contêm metais pesados como mercúrio, chumbo e cádmio, que contaminam o solo e a água.',
 'Descarte em pontos de coleta específicos de logística reversa. Nunca descarte no lixo comum.',
 'As pilhas podem ser recicladas, recuperando-se os metais para reuso industrial.',
 'pilha, bateria, metal pesado, logistica reversa', TRUE),
(1, 'Bateria de celular',
 'Baterias de celular contêm lítio e outros metais que podem causar incêndios e contaminação ambiental.',
 'Entregue em pontos de coleta de logística reversa de eletrônicos ou lojas especializadas.',
 'Baterias de lítio podem ser recicladas para recuperação de metais valiosos.',
 'bateria, celular, litio, eletronico, logistica reversa', TRUE),
(2, 'Garrafa PET',
 'Garrafas de polietileno tereftalato (PET) são amplamente recicláveis e usadas na fabricação de novos produtos.',
 'Descarte na coleta seletiva (cor vermelha para plásticos) ou em pontos de coleta de recicláveis.',
 'O PET reciclado pode ser transformado em fibras têxteis, novas garrafas e embalagens.',
 'pet, plastico, garrafa, reciclavel', FALSE),
(2, 'Papelão',
 'Papelão é um material reciclável composto por fibras de celulose, amplamente utilizado em embalagens.',
 'Descarte na coleta seletiva (cor azul para papel/papelão) ou em pontos de coleta de recicláveis.',
 'O papelão reciclado é reprocessado para fabricação de novas embalagens e produtos de papel.',
 'papelao, papel, reciclavel, embalagem', FALSE),
(3, 'Entulho de construção',
 'Restos de construção civil como concreto, tijolos e cerâmicas são considerados resíduos inertes.',
 'Descarte em locais autorizados para resíduos da construção civil ou ecopontos.',
 'O entulho pode ser reciclado para produção de agregados reciclados e pavimentação.',
 'entulho, construcao civil, concreto, tijolo', FALSE);


-- ------------------------------------------------------------
-- Conteúdo educativo de exemplo
-- ------------------------------------------------------------
INSERT INTO conteudo_educativo (titulo, texto, imagem, url, data_publicacao) VALUES
('A importância da coleta seletiva',
 'A coleta seletiva é o processo de separação dos resíduos recicláveis dos orgânicos e rejeitos. Ela é fundamental para reduzir a quantidade de lixo enviado aos aterros sanitários e para promover a economia circular. A Resolução CONAMA nº 275/2001 estabelece um padrão de cores para facilitar a identificação: azul (papel), vermelho (plástico), verde (vidro), amarelo (metal), marrom (orgânico) e cinza (não reciclável).',
 NULL,
 'https://www.gov.br/mma/pt-br/temas/residuos-solidos',
 CURRENT_TIMESTAMP),
('Logística reversa: o que é e como funciona',
 'A logística reversa é um instrumento da Política Nacional de Resíduos Sólidos (Lei nº 12.305/2010) que viabiliza a coleta e restituição de resíduos sólidos ao setor empresarial para reaproveitamento. Ela se aplica principalmente a pilhas, baterias, lâmparas fluorescentes, eletroeletrônicos e seus componentes. O descarte correto desses itens evita a contaminação do solo e da água por metais pesados.',
 NULL,
 'http://www.planalto.gov.br/ccivil_03/_ato2007-2010/2010/lei/l12305.htm',
 CURRENT_TIMESTAMP);

-- ------------------------------------------------------------
-- Quiz de exemplo
-- ------------------------------------------------------------
INSERT INTO quiz (titulo, descricao) VALUES
('Quiz: Gestão de Resíduos Sólidos',
 'Teste seus conhecimentos sobre classificação, descarte e reciclagem de resíduos sólidos.');

-- ------------------------------------------------------------
-- Perguntas do quiz (id_quiz = 1)
-- ------------------------------------------------------------
INSERT INTO pergunta (id_quiz, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta) VALUES
(1,
 'Qual cor do padrão CONAMA 275/2001 identifica resíduos plásticos?',
 'Azul', 'Vermelho', 'Verde', 'Amarelo', 'b'),
(1,
 'Segundo a ABNT NBR 10004, os resíduos perigosos pertencem a qual classe?',
 'Classe I', 'Classe II A', 'Classe II B', 'Classe III', 'a'),
(1,
 'Qual lei instituiu a Política Nacional de Resíduos Sólidos?',
 'Lei nº 12.305/2010', 'Lei nº 9.605/1998', 'Lei nº 6.938/1981', 'Lei nº 11.445/2007', 'a'),
(1,
 'Qual destes resíduos NÃO deve ser descartado no lixo comum?',
 'Casca de banana', 'Papelão limpo', 'Pilha', 'Sobra de comida', 'c'),
(1,
 'O que significa "logística reversa"?',
 'Coleta de lixo domiciliar', 'Retorno de produtos/resíduos ao setor produtivo após o consumo',
 'Reciclagem de papel', 'Compostagem de resíduos orgânicos', 'b');