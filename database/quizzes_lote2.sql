-- ============================================================
-- Rhesys - Lote 2 de quizzes (educação ambiental)
-- 4 novos quizzes com 5 perguntas cada, com gabarito.
-- Idempotente: o quiz só é criado se o título ainda não existir e
-- as perguntas só são inseridas se o quiz estiver sem perguntas.
-- ============================================================
USE rhesys;

-- ------------------------------------------------------------
-- QUIZ: Classificação de Resíduos (ABNT NBR 10004)
-- ------------------------------------------------------------
INSERT INTO quiz (titulo, descricao)
SELECT 'Classificação de Resíduos (ABNT NBR 10004)',
       'Aprenda a diferença entre resíduos Classe I (perigosos), Classe II A (não inertes) e Classe II B (inertes).'
WHERE NOT EXISTS (SELECT 1 FROM quiz WHERE titulo = 'Classificação de Resíduos (ABNT NBR 10004)');

SET @q_abnt := (SELECT id_quiz FROM quiz WHERE titulo = 'Classificação de Resíduos (ABNT NBR 10004)');

INSERT INTO pergunta (id_quiz, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta)
SELECT * FROM (
    SELECT @q_abnt AS id_quiz,
           'Segundo a ABNT NBR 10004, os resíduos Classe I são aqueles que:' AS enunciado,
           'Não sofrem transformações em contato com a água' AS alternativa_a,
           'Apresentam periculosidade, como inflamabilidade, toxicidade ou corrosividade' AS alternativa_b,
           'São sempre biodegradáveis' AS alternativa_c,
           'Podem ser descartados no lixo comum sem cuidados' AS alternativa_d,
           'b' AS resposta_correta
    UNION ALL
    SELECT @q_abnt, 'Restos de alimentos, papel e papelão são exemplos de resíduos:',
           'Classe II A - Não Inertes', 'Classe I - Perigosos', 'Classe II B - Inertes', 'Resíduos radioativos', 'a'
    UNION ALL
    SELECT @q_abnt, 'Entulhos de construção civil, concreto e tijolos classificam-se como:',
           'Classe I - Perigosos', 'Classe II A - Não Inertes', 'Classe II B - Inertes', 'Resíduos orgânicos compostáveis', 'c'
    UNION ALL
    SELECT @q_abnt, 'Pilhas, baterias e lâmpadas fluorescentes devem ser tratadas como:',
           'Inertes, pois não se degradam', 'Recicláveis comuns, junto com o plástico',
           'Rejeitos sem qualquer risco', 'Perigosos (Classe I), com descarte em pontos específicos', 'd'
    UNION ALL
    SELECT @q_abnt, 'A principal diferença entre resíduos não inertes e inertes é que os não inertes:',
           'Podem sofrer alterações ao longo do tempo, como biodegradação e solubilidade em água',
           'Nunca podem ser reciclados', 'São sempre tóxicos', 'Não reagem de nenhuma forma ao ambiente', 'a'
) AS lote
WHERE NOT EXISTS (SELECT 1 FROM pergunta WHERE id_quiz = @q_abnt);

-- ------------------------------------------------------------
-- QUIZ: Descarte Consciente no Dia a Dia
-- ------------------------------------------------------------
INSERT INTO quiz (titulo, descricao)
SELECT 'Descarte Consciente no Dia a Dia',
       'Situações práticas do cotidiano: aprenda o destino correto de óleo, embalagens, medicamentos e do lixo de casa.'
WHERE NOT EXISTS (SELECT 1 FROM quiz WHERE titulo = 'Descarte Consciente no Dia a Dia');

SET @q_descarte := (SELECT id_quiz FROM quiz WHERE titulo = 'Descarte Consciente no Dia a Dia');

INSERT INTO pergunta (id_quiz, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta)
SELECT * FROM (
    SELECT @q_descarte AS id_quiz,
           'O óleo de cozinha usado deve ser:' AS enunciado,
           'Despejado na pia com água quente' AS alternativa_a,
           'Armazenado em uma garrafa e levado a um ponto de coleta' AS alternativa_b,
           'Jogado no vaso sanitário' AS alternativa_c,
           'Despejado no solo do quintal' AS alternativa_d,
           'b' AS resposta_correta
    UNION ALL
    SELECT @q_descarte, 'Antes de reciclar embalagens plásticas e metálicas, o ideal é:',
           'Higienizá-las rapidamente e deixá-las secas', 'Amassá-las com restos de comida dentro',
           'Queimá-las para reduzir o volume', 'Pintá-las de verde para identificação', 'a'
    UNION ALL
    SELECT @q_descarte, 'O que NÃO deve ir para a coleta de recicláveis?',
           'Garrafas PET limpas', 'Jornais e revistas', 'Papel higiênico usado e fraldas descartáveis', 'Latas de alumínio', 'c'
    UNION ALL
    SELECT @q_descarte, 'Medicamentos vencidos devem ser descartados:',
           'No lixo comum, triturados', 'Na pia ou no vaso sanitário',
           'Em farmácias e postos de saúde com coleta específica', 'No lixo reciclável, junto com as caixas', 'c'
    UNION ALL
    SELECT @q_descarte, 'Ao separar o lixo em casa, a atitude mais correta é:',
           'Misturar tudo, pois a cooperativa separa depois',
           'Separar recicláveis, orgânicos e rejeitos em recipientes diferentes',
           'Guardar pilhas e baterias junto com os plásticos', 'Descartar eletrônicos no lixo comum', 'b'
) AS lote
WHERE NOT EXISTS (SELECT 1 FROM pergunta WHERE id_quiz = @q_descarte);

-- ------------------------------------------------------------
-- QUIZ: Reciclagem e Logística Reversa
-- ------------------------------------------------------------
INSERT INTO quiz (titulo, descricao)
SELECT 'Reciclagem e Logística Reversa',
       'Entenda como funcionam a logística reversa, a PNRS (Lei 12.305/2010) e o papel das cooperativas de catadores.'
WHERE NOT EXISTS (SELECT 1 FROM quiz WHERE titulo = 'Reciclagem e Logística Reversa');

SET @q_reciclagem := (SELECT id_quiz FROM quiz WHERE titulo = 'Reciclagem e Logística Reversa');

INSERT INTO pergunta (id_quiz, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta)
SELECT * FROM (
    SELECT @q_reciclagem AS id_quiz,
           'Logística reversa é:' AS enunciado,
           'O processo de transportar lixo para aterros distantes' AS alternativa_a,
           'A devolução de produtos e embalagens pós-consumo para reaproveitamento ou destinação adequada' AS alternativa_b,
           'A troca de caminhões de coleta por bicicletas' AS alternativa_c,
           'A venda de resíduos para outros países' AS alternativa_d,
           'b' AS resposta_correta
    UNION ALL
    SELECT @q_reciclagem, 'A Política Nacional de Resíduos Sólidos (Lei 12.305/2010) define que a responsabilidade pelo descarte correto é:',
           'Somente do governo', 'Apenas das cooperativas de catadores',
           'Compartilhada entre empresas, poder público e consumidores', 'Exclusiva do consumidor final', 'c'
    UNION ALL
    SELECT @q_reciclagem, 'Qual destes materiais tem um dos ciclos de reciclagem mais eficientes no Brasil?',
           'Isopor', 'Alumínio (latas)', 'Cerâmica', 'Papel higiênico', 'b'
    UNION ALL
    SELECT @q_reciclagem, 'Reciclar alumínio economiza aproximadamente quanta energia em relação à produção a partir da bauxita?',
           'Cerca de 5%', 'Cerca de 25%', 'Cerca de 50%', 'Cerca de 95%', 'd'
    UNION ALL
    SELECT @q_reciclagem, 'As cooperativas de catadores são importantes porque:',
           'Aumentam o volume de lixo enviado aos aterros',
           'Recolhem, separam e destinam recicláveis, gerando renda e reduzindo impactos ambientais',
           'Trabalham apenas com resíduos hospitalares', 'Substituem a necessidade de políticas públicas', 'b'
) AS lote
WHERE NOT EXISTS (SELECT 1 FROM pergunta WHERE id_quiz = @q_reciclagem);

-- ------------------------------------------------------------
-- QUIZ: Compostagem e Resíduos Orgânicos
-- ------------------------------------------------------------
INSERT INTO quiz (titulo, descricao)
SELECT 'Compostagem e Resíduos Orgânicos',
       'Descubra como transformar restos de alimentos em adubo e reduzir o lixo enviado aos aterros sanitários.'
WHERE NOT EXISTS (SELECT 1 FROM quiz WHERE titulo = 'Compostagem e Resíduos Orgânicos');

SET @q_compostagem := (SELECT id_quiz FROM quiz WHERE titulo = 'Compostagem e Resíduos Orgânicos');

INSERT INTO pergunta (id_quiz, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta)
SELECT * FROM (
    SELECT @q_compostagem AS id_quiz,
           'Compostagem é:' AS enunciado,
           'A queima controlada de resíduos orgânicos' AS alternativa_a,
           'Um processo biológico que transforma resíduos orgânicos em adubo' AS alternativa_b,
           'A mistura de plásticos com terra' AS alternativa_c,
           'Um tipo de aterro sanitário' AS alternativa_d,
           'b' AS resposta_correta
    UNION ALL
    SELECT @q_compostagem, 'Qual destes materiais é adequado para a composteira doméstica?',
           'Cascas de frutas e borra de café', 'Pilhas e baterias', 'Vidro quebrado', 'Latas de tinta', 'a'
    UNION ALL
    SELECT @q_compostagem, 'O que deve ser evitado na composteira doméstica?',
           'Folhas secas', 'Cascas de ovo', 'Carnes, laticínios e óleos em excesso', 'Restos de legumes', 'c'
    UNION ALL
    SELECT @q_compostagem, 'O resultado final da compostagem é:',
           'Um gás tóxico inflamável', 'Composto orgânico (adubo) rico em nutrientes',
           'Água potável', 'Plástico biodegradável', 'b'
    UNION ALL
    SELECT @q_compostagem, 'Além de gerar adubo, a compostagem ajuda o meio ambiente porque:',
           'Reduz o volume de orgânicos enviados a aterros e as emissões de metano',
           'Elimina a necessidade de coleta seletiva',
           'Transforma todo resíduo em energia elétrica', 'Dispensa o uso de água nas plantas', 'a'
) AS lote
WHERE NOT EXISTS (SELECT 1 FROM pergunta WHERE id_quiz = @q_compostagem);
