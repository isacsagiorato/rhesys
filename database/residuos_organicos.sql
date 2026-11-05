-- Resíduos orgânicos: pode ser executado mais de uma vez sem duplicar itens.
USE rhesys;

INSERT INTO residuo (id_classificacao, nome, descricao, forma_descarte, reciclagem, palavras_chave, possui_logistica_reversa)
SELECT 2, 'Resíduos orgânicos',
 'Lixo orgânico, também chamado de resíduo orgânico, é basicamente todo o material que descartamos que tem origem animal ou vegetal (além dos fungos) e seus derivados. Dentro de nossas residências, a maior parte é o que sobra das nossas refeições.',
 'Separe restos de alimentos e materiais de origem vegetal ou animal para composteira doméstica, comunitária ou coleta de orgânicos. Não misture com recicláveis.',
 'A compostagem transforma boa parte dos orgânicos em adubo. Verifique as regras locais para carnes, laticínios e alimentos cozidos.',
 'orgânico, organico, lixo orgânico, origem vegetal, origem animal, fungos', FALSE
WHERE NOT EXISTS (SELECT 1 FROM residuo WHERE nome = 'Resíduos orgânicos');

INSERT INTO residuo (id_classificacao, nome, descricao, forma_descarte, reciclagem, palavras_chave, possui_logistica_reversa)
SELECT 2, 'Casca de banana',
 'Cascas de banana são restos de frutas de origem vegetal que se decompõem rapidamente.',
 'Corte em pedaços menores e coloque na composteira ou encaminhe à coleta de resíduos orgânicos.',
 'Na compostagem, as cascas viram matéria orgânica e nutrientes para o adubo.',
 'banana, casca, cascas, fruta, orgânico, organico, resto de comida', FALSE
WHERE NOT EXISTS (SELECT 1 FROM residuo WHERE nome = 'Casca de banana');

INSERT INTO residuo (id_classificacao, nome, descricao, forma_descarte, reciclagem, palavras_chave, possui_logistica_reversa)
SELECT 2, 'Cenoura e cascas de legumes',
 'Cenouras, suas cascas e outros restos de legumes são materiais de origem vegetal aproveitáveis na compostagem.',
 'Retire embalagens e adicione os restos à composteira ou à coleta de orgânicos, preferencialmente em pedaços pequenos.',
 'Os restos vegetais contribuem com nutrientes e umidade para a produção de composto orgânico.',
 'cenoura, casca, cascas, legumes, verduras, orgânico, organico', FALSE
WHERE NOT EXISTS (SELECT 1 FROM residuo WHERE nome = 'Cenoura e cascas de legumes');

INSERT INTO residuo (id_classificacao, nome, descricao, forma_descarte, reciclagem, palavras_chave, possui_logistica_reversa)
SELECT 2, 'Queijo e laticínios',
 'Restos de queijo e outros laticínios têm origem animal e se deterioram rapidamente.',
 'Use somente composteiras que aceitem laticínios, em pequenas quantidades. Caso contrário, embale bem e encaminhe à coleta de orgânicos ou de rejeitos, conforme o serviço local.',
 'Algumas composteiras controladas processam laticínios, mas o excesso pode causar odor e atrair animais.',
 'queijo, laticínio, laticinio, leite, orgânico, organico, resto de comida', FALSE
WHERE NOT EXISTS (SELECT 1 FROM residuo WHERE nome = 'Queijo e laticínios');

INSERT INTO residuo (id_classificacao, nome, descricao, forma_descarte, reciclagem, palavras_chave, possui_logistica_reversa)
SELECT 2, 'Restos de comida',
 'Sobras das refeições e alimentos estragados formam grande parte do lixo orgânico produzido nas residências.',
 'Retire embalagens e excesso de líquido. Encaminhe para uma composteira que aceite esse material ou para a coleta de orgânicos disponível.',
 'Restos adequados à compostagem viram adubo; carnes, alimentos cozidos e muito tempero exigem sistemas preparados.',
 'resto de comida, restos de comida, sobra, alimento, refeição, orgânico, organico', FALSE
WHERE NOT EXISTS (SELECT 1 FROM residuo WHERE nome = 'Restos de comida');

INSERT INTO residuo (id_classificacao, nome, descricao, forma_descarte, reciclagem, palavras_chave, possui_logistica_reversa)
SELECT 2, 'Mato e podas de jardim',
 'Mato, grama cortada, galhos e pequenas podas são resíduos vegetais de jardinagem.',
 'Acondicione conforme as regras da coleta municipal de podas ou encaminhe para compostagem. Não abandone em terrenos ou vias públicas.',
 'Podas podem virar composto ou cobertura vegetal para proteger o solo e reduzir a perda de umidade.',
 'mato, poda, grama, galho, jardim, orgânico, organico', FALSE
WHERE NOT EXISTS (SELECT 1 FROM residuo WHERE nome = 'Mato e podas de jardim');

INSERT INTO residuo (id_classificacao, nome, descricao, forma_descarte, reciclagem, palavras_chave, possui_logistica_reversa)
SELECT 2, 'Borra de café',
 'A borra é o resíduo orgânico que resta após o preparo do café.',
 'Deixe escorrer e coloque na composteira ou na coleta de orgânicos. Não descarte na pia ou no ralo.',
 'A borra pode ser misturada a outros materiais na compostagem, contribuindo para a formação de adubo.',
 'café, cafe, borra, filtro, orgânico, organico', FALSE
WHERE NOT EXISTS (SELECT 1 FROM residuo WHERE nome = 'Borra de café');

INSERT INTO residuo (id_classificacao, nome, descricao, forma_descarte, reciclagem, palavras_chave, possui_logistica_reversa)
SELECT 2, 'Cascas de ovo',
 'Cascas de ovo são resíduos de origem animal ricos em minerais.',
 'Lave, deixe secar e triture antes de encaminhar à composteira ou à coleta de orgânicos.',
 'As cascas trituradas adicionam minerais ao composto, mas não substituem um fertilizante completo.',
 'ovo, casca, cascas, orgânico, organico, resto de comida', FALSE
WHERE NOT EXISTS (SELECT 1 FROM residuo WHERE nome = 'Cascas de ovo');

INSERT INTO residuo (id_classificacao, nome, descricao, forma_descarte, reciclagem, palavras_chave, possui_logistica_reversa)
SELECT 2, 'Frutas e verduras estragadas',
 'Frutas, legumes e verduras fora de consumo são resíduos de origem vegetal.',
 'Retire toda a embalagem e encaminhe os restos à compostagem ou à coleta de orgânicos. Material com doenças ou pragas pode exigir descarte separado.',
 'Os vegetais adequados se decompõem e ajudam a formar composto orgânico para uso no solo.',
 'fruta, verdura, legume, estragado, orgânico, organico, resto de comida', FALSE
WHERE NOT EXISTS (SELECT 1 FROM residuo WHERE nome = 'Frutas e verduras estragadas');

INSERT INTO residuo (id_classificacao, nome, descricao, forma_descarte, reciclagem, palavras_chave, possui_logistica_reversa)
SELECT 2, 'Folhas secas',
 'Folhas secas são resíduos vegetais de jardins, quintais e árvores urbanas.',
 'Junte as folhas para compostagem ou para a coleta de materiais verdes. Evite queimadas e descarte em locais proibidos.',
 'As folhas servem como material seco na compostagem e podem virar cobertura protetora para o solo.',
 'folha, folhas, seca, jardim, mato, orgânico, organico', FALSE
WHERE NOT EXISTS (SELECT 1 FROM residuo WHERE nome = 'Folhas secas');

UPDATE residuo
SET palavras_chave = 'areia, terra, gato, vaso, jardinagem'
WHERE nome = 'Areia e terra de vaso'
  AND palavras_chave = 'areia, terra, gato, vaso, jardinagem, orgânico';
