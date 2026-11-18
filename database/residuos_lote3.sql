-- ============================================================
-- Rhesys - Lote 3 de resíduos + tipo de material
-- ------------------------------------------------------------
-- 1) Cria a coluna residuo.tipo_material (se ainda não existir);
-- 2) Classifica TODOS os resíduos por tipo de material;
-- 3) Corrige a classe de "Garrafas e potes de vidro" (vidro é o exemplo
--    clássico de resíduo inerte, Classe II B, como o vidro plano);
-- 4) Cadastra 36 resíduos novos do dia a dia.
--
-- Classificação conforme ABNT NBR 10004:
--   1 = Classe I (perigosos), 2 = Classe II A (não inertes), 3 = Classe II B (inertes)
-- Logística reversa conforme a Lei 12.305/2010 (PNRS), art. 33, e os
-- Decretos 10.240/2020 (eletroeletrônicos) e 10.388/2020 (medicamentos).
--
-- Script IDEMPOTENTE: pode ser executado várias vezes sem duplicar nada.
-- Execute DEPOIS de rhesys_banco_dados.sql, residuos_organicos.sql e
-- residuos_lote2.sql:   mysql -u root -p < database/residuos_lote3.sql
-- ============================================================
USE rhesys;

-- ------------------------------------------------------------
-- 1) Coluna tipo_material
-- ------------------------------------------------------------
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'residuo' AND column_name = 'tipo_material') = 0,
    'ALTER TABLE residuo ADD COLUMN tipo_material VARCHAR(30) NULL AFTER possui_logistica_reversa',
    'SELECT ''coluna tipo_material ja existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'residuo' AND index_name = 'idx_residuo_tipo') = 0,
    'ALTER TABLE residuo ADD INDEX idx_residuo_tipo (tipo_material)',
    'SELECT ''indice idx_residuo_tipo ja existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 2) Tipo de material dos resíduos já cadastrados
-- ------------------------------------------------------------
UPDATE residuo SET tipo_material = 'metal' WHERE nome IN
    ('Lata de alumínio', 'Lata de aço', 'Papel alumínio');
UPDATE residuo SET tipo_material = 'plastico' WHERE nome IN
    ('Garrafa PET', 'Brinquedo de plástico quebrado', 'Isopor', 'Pote de iogurte e margarina',
     'Sacola plástica', 'CD, DVD e fitas');
UPDATE residuo SET tipo_material = 'papel' WHERE nome IN
    ('Papelão', 'Jornal e revista', 'Papel branco (sulfite)', 'Embalagem cartonada (Tetra Pak)');
UPDATE residuo SET tipo_material = 'vidro' WHERE nome IN
    ('Garrafas e potes de vidro', 'Vidro plano e espelho');
UPDATE residuo SET tipo_material = 'organico' WHERE nome IN
    ('Resíduos orgânicos', 'Casca de banana', 'Cascas de legumes', 'Queijo e laticínios',
     'Restos de comida', 'Mato e podas de jardim', 'Borra de café', 'Cascas de ovo',
     'Frutas e verduras estragadas', 'Folhas secas');
UPDATE residuo SET tipo_material = 'eletronico' WHERE nome IN
    ('Pilha', 'Bateria de celular', 'Celular', 'Computador e notebook', 'Televisão e monitor',
     'Eletrodoméstico (geladeira, máquina de lavar)', 'Toner e cartucho de impressora');
UPDATE residuo SET tipo_material = 'especial' WHERE nome IN
    ('Lâmpada fluorescente', 'Medicamento vencido', 'Embalagem de agrotóxico', 'Óleo lubrificante usado',
     'Pneu', 'Bateria automotiva', 'Restos de tinta e solvente', 'Aerossol (spray)',
     'Óleo de cozinha usado', 'Termômetro de mercúrio');
UPDATE residuo SET tipo_material = 'construcao' WHERE nome IN
    ('Entulho de construção', 'Cerâmica e azulejo quebrado', 'Louça quebrada (pratos e canecas)',
     'Areia e terra de vaso', 'Pedra e mármore (resíduo de reforma)');
UPDATE residuo SET tipo_material = 'textil' WHERE nome IN
    ('Roupa e tecido velho', 'Calçado velho');
UPDATE residuo SET tipo_material = 'rejeito' WHERE nome IN
    ('Fralda descartável usada');

-- ------------------------------------------------------------
-- 3) Correção de classe: vidro é inerte (Classe II B)
-- ------------------------------------------------------------
UPDATE residuo SET id_classificacao = 3 WHERE nome = 'Garrafas e potes de vidro';

-- ------------------------------------------------------------
-- 4) Novos resíduos (INSERT IGNORE + UNIQUE em nome = sem duplicar)
-- Colunas: classe, nome, descrição, descarte, reciclagem, palavras-chave,
--          logística reversa, tipo de material
-- ------------------------------------------------------------
INSERT IGNORE INTO residuo
    (id_classificacao, nome, descricao, forma_descarte, reciclagem, palavras_chave, possui_logistica_reversa, tipo_material)
VALUES
-- ---------- METAIS ----------
(2, 'Panela velha',
 'Panelas, frigideiras e assadeiras de alumínio, inox ou ferro são feitas de metal reciclável, mesmo quando amassadas ou sem cabo.',
 'Retire cabos de plástico ou madeira, se possível, e entregue em ferro-velho, cooperativa de reciclagem ou ecoponto. Panelas antiaderentes também são aceitas pela maioria das sucatas.',
 'O metal é derretido e vira matéria-prima para novas peças; o alumínio pode ser reciclado infinitas vezes sem perder qualidade.',
 'panela, frigideira, assadeira, aluminio, inox, ferro, metal, sucata', FALSE, 'metal'),
(2, 'Tampinha de metal',
 'Tampas metálicas de garrafas de vidro (cerveja, refrigerante) e de potes de conserva são feitas de aço.',
 'Junte as tampinhas em uma lata ou garrafa PET fechada (soltas, se perdem na triagem) e descarte na coleta seletiva de metais.',
 'O aço é separado por ímã nas centrais de triagem e volta para a siderurgia como sucata.',
 'tampinha, tampa, chapinha, garrafa, aco, metal', FALSE, 'metal'),
(2, 'Sucata de ferro (pregos, parafusos e ferramentas)',
 'Pregos, parafusos, arames, ferramentas quebradas e pequenas peças de ferro são sucata metálica de alto valor para reciclagem.',
 'Guarde em caixa ou pote fechado para evitar cortes e entregue em ferro-velho, sucateiro ou ecoponto. Não misture com o lixo comum.',
 'A sucata ferrosa é fundida em siderúrgicas e vira aço novo, economizando minério de ferro e energia.',
 'sucata, ferro, prego, parafuso, arame, ferramenta, metal', FALSE, 'metal'),

-- ---------- PLÁSTICOS ----------
(2, 'Frasco de produto de limpeza',
 'Frascos de detergente, amaciante, desinfetante e sabão líquido são de plástico reciclável (geralmente PEAD ou PET).',
 'Use todo o produto, enxágue o frasco, deixe secar e descarte na coleta seletiva de plásticos, com a tampa.',
 'O plástico é triturado, lavado e transformado em novos frascos, baldes, conduítes e mangueiras.',
 'frasco, detergente, amaciante, desinfetante, produto de limpeza, pead, plastico', FALSE, 'plastico'),
(2, 'Tampa plástica de garrafa',
 'Tampinhas de garrafas PET, de leite e de produtos de limpeza são de plástico (PP ou PEAD) e recicláveis.',
 'Descarte na coleta seletiva de plásticos, de preferência rosqueadas na própria garrafa. Muitas campanhas sociais também recolhem tampinhas.',
 'As tampas viram grânulos de plástico usados na fabricação de vassouras, utensílios e peças técnicas.',
 'tampa, tampinha, plastico, garrafa, pp, pead', FALSE, 'plastico'),
(2, 'Copo descartável de plástico',
 'Copos descartáveis costumam ser de poliestireno (PS) ou polipropileno (PP), plásticos leves e frágeis.',
 'Esvazie, empilhe os copos e descarte na coleta seletiva de plásticos. Prefira copos e garrafas reutilizáveis.',
 'O PS e o PP podem ser reciclados quando chegam limpos às cooperativas, virando réguas, cabides e peças plásticas.',
 'copo, descartavel, plastico, ps, pp, festa', FALSE, 'plastico'),
(2, 'Canudo e talheres descartáveis',
 'Canudos, garfos e facas descartáveis são pequenos, leves e quase sempre sujos de comida, o que dificulta a reciclagem.',
 'Se estiverem limpos, junte em um saquinho e descarte na coleta seletiva de plásticos; sujos, vão para o lixo comum. O ideal é evitar e usar talheres reutilizáveis.',
 'Quando limpos e em volume, podem ser reciclados como polipropileno ou poliestireno, mas a maior parte acaba em aterros.',
 'canudo, talher, garfo, faca, colher, descartavel, plastico', FALSE, 'plastico'),
(2, 'Embalagem metalizada (salgadinho e biscoito)',
 'Pacotes de salgadinho, biscoito e café são feitos de plástico com uma camada fina de alumínio (BOPP metalizado).',
 'Descarte na coleta seletiva de plásticos, limpa e sem restos. Se a cooperativa da sua cidade não aceitar, vai para o lixo comum.',
 'É de reciclagem difícil por misturar materiais, mas existem empresas que a transformam em placas e peças plásticas.',
 'embalagem, metalizada, salgadinho, biscoito, bopp, pacote, plastico', FALSE, 'plastico'),
(2, 'Escova de dente',
 'Escovas de dente misturam plásticos diferentes no cabo com cerdas de náilon, o que dificulta a reciclagem comum.',
 'Descarte no lixo comum ou entregue em programas de coleta de produtos de higiene bucal (algumas farmácias e marcas recolhem). Antes, reaproveite para limpeza.',
 'Programas específicos separam cabo e cerdas e transformam o plástico em novos produtos, como bancos e lixeiras.',
 'escova, dente, higiene bucal, plastico, nailon', FALSE, 'plastico'),

-- ---------- PAPEL E PAPELÃO ----------
(2, 'Caixa de pizza',
 'A caixa de pizza é de papelão, mas a parte suja de gordura e queijo não pode ser reciclada.',
 'Recorte e separe: a parte limpa vai para a coleta seletiva de papel; a parte engordurada vai para a compostagem ou para o lixo comum.',
 'O papelão limpo é desfeito em água e volta como novas caixas; a gordura contamina o lote inteiro de papel.',
 'pizza, caixa, papelao, gordura, delivery, papel', FALSE, 'papel'),
(2, 'Papel toalha e guardanapo usados',
 'Papel toalha, guardanapos e lenços de papel usados estão sujos e têm fibras curtas demais para reciclar.',
 'Não vão para a coleta seletiva. Se estiverem sujos só de alimentos ou água, podem ir para a compostagem; caso contrário, lixo comum.',
 'Não são recicláveis, mas se decompõem rapidamente na compostagem como material rico em carbono.',
 'papel toalha, guardanapo, lenco de papel, papel, sujo', FALSE, 'papel'),
(2, 'Caderno e livro velho',
 'Cadernos, apostilas e livros são feitos de papel e papelão de boa qualidade para reciclagem.',
 'Livros em bom estado podem ser doados a bibliotecas, sebos e escolas. Para reciclar, retire a espiral de metal e descarte o papel na coleta seletiva.',
 'O papel é transformado em pasta de celulose e vira papel reciclado, papelão e embalagens.',
 'caderno, livro, apostila, espiral, papel, doacao, escola', FALSE, 'papel'),
(2, 'Rolo de papel higiênico',
 'O tubo de papelão que sobra do papel higiênico e do papel toalha é limpo e totalmente reciclável.',
 'Descarte na coleta seletiva de papel. Também pode ir para a compostagem ou ser reaproveitado em brinquedos e artesanato.',
 'O papelão é reciclado com outros papéis, virando novas caixas e embalagens.',
 'rolo, tubo, papel higienico, papelao, papel', FALSE, 'papel'),

-- ---------- VIDROS ----------
(3, 'Copo de vidro quebrado',
 'Copos de vidro comum são recicláveis, mas os cacos cortam e machucam quem faz a coleta.',
 'Embrulhe os cacos em jornal ou coloque em caixa ou garrafa PET, feche bem e escreva "vidro quebrado". Descarte na coleta seletiva de vidros.',
 'O vidro de copos e embalagens é derretido e vira novas garrafas e potes, sem perder qualidade.',
 'copo, vidro, caco, quebrado, cristal', FALSE, 'vidro'),
(3, 'Frasco de perfume vazio',
 'Frascos de perfume são de vidro, mas costumam ter válvula de metal e plástico presa ao gargalo.',
 'Use todo o perfume, separe a tampa e, se possível, a válvula. Descarte o vidro na coleta seletiva; muitas marcas de cosméticos também recolhem frascos em suas lojas.',
 'O vidro é reciclado como o de outras embalagens; as peças de metal e plástico seguem para as respectivas reciclagens.',
 'perfume, frasco, vidro, cosmetico, colonia', FALSE, 'vidro'),

-- ---------- ORGÂNICOS ----------
(2, 'Erva de chimarrão e saquinho de chá',
 'A erva-mate usada e as folhas de chá são restos vegetais que se decompõem rapidamente.',
 'Descarte na compostagem ou no lixo orgânico. Saquinhos de chá com grampo de metal ou de náilon: retire o grampo e jogue o saquinho no lixo comum.',
 'Na compostagem, a erva e o chá viram adubo rico em nitrogênio para hortas e jardins.',
 'erva, chimarrao, mate, cha, saquinho, organico, compostagem', FALSE, 'organico'),
(2, 'Pão e bolo velhos',
 'Pães, bolos e biscoitos amanhecidos são restos de alimento que se decompõem e podem atrair animais.',
 'Antes de descartar, reaproveite (torradas, farinha de rosca, pudim). Se estragados, vão para a compostagem ou lixo orgânico.',
 'Na compostagem, viram adubo; evite grandes quantidades de uma vez para não atrair insetos.',
 'pao, bolo, biscoito, amanhecido, alimento, organico, resto de comida', FALSE, 'organico'),
(2, 'Ossos e restos de carne',
 'Ossos, peles e restos de carne, frango e peixe são orgânicos, mas apodrecem com cheiro forte e atraem animais.',
 'Embale bem e descarte no lixo orgânico ou comum. Não coloque na composteira doméstica comum, que não atinge temperatura suficiente.',
 'Em compostagem industrial e biodigestores, viram adubo e biogás com segurança.',
 'osso, carne, frango, peixe, pele, gordura, organico, resto de comida', FALSE, 'organico'),
(2, 'Serragem e lascas de madeira',
 'Serragem, maravalha e pequenas lascas de madeira natural (sem tinta ou verniz) são orgânicos e se decompõem devagar.',
 'Use na compostagem como material seco, para cobrir canteiros ou como cama de animais. Madeira pintada ou envernizada vai para o ecoponto.',
 'Na compostagem, equilibra os restos de comida e evita mau cheiro; em indústrias, vira briquetes e painéis de madeira.',
 'serragem, maravalha, madeira, lasca, marcenaria, organico, compostagem', FALSE, 'organico'),

-- ---------- ELETROELETRÔNICOS ----------
(1, 'Cabos e carregadores',
 'Carregadores, fios, extensões e cabos USB têm cobre, plástico e componentes eletrônicos que não podem ir para o lixo comum.',
 'Entregue em pontos de coleta de lixo eletrônico (lojas de eletrônicos, supermercados participantes e ecopontos).',
 'O cobre é recuperado e reciclado, e o plástico dos fios é separado para reaproveitamento.',
 'cabo, carregador, fio, extensao, usb, cobre, eletronico, lixo eletronico', TRUE, 'eletronico'),
(1, 'Fone de ouvido',
 'Fones com ou sem fio têm pequenos ímãs, fios de cobre e, nos modelos sem fio, baterias de lítio.',
 'Entregue em pontos de coleta de eletroeletrônicos. Nunca jogue fones sem fio no lixo comum: a bateria pode pegar fogo na compactação.',
 'Os metais, ímãs e baterias são separados e reciclados por empresas especializadas.',
 'fone, fone de ouvido, headphone, bluetooth, bateria, eletronico', TRUE, 'eletronico'),
(1, 'Controle remoto',
 'Controles remotos são eletrônicos com placa de circuito e costumam ficar com as pilhas dentro.',
 'Retire as pilhas (elas têm descarte próprio) e entregue o controle em um ponto de coleta de lixo eletrônico.',
 'A placa tem metais recuperáveis e o plástico da carcaça pode ser reciclado.',
 'controle, controle remoto, tv, pilha, eletronico', TRUE, 'eletronico'),
(1, 'Impressora',
 'Impressoras e multifuncionais misturam plástico, metal, placas eletrônicas e restos de tinta ou toner.',
 'Retire os cartuchos e entregue o aparelho em ponto de coleta de eletroeletrônicos ou devolva ao fabricante pelo programa de logística reversa.',
 'Os componentes são desmontados: metais e plásticos vão para reciclagem e as placas para recuperação de metais.',
 'impressora, multifuncional, scanner, eletronico, lixo eletronico', TRUE, 'eletronico'),
(1, 'Lâmpada LED',
 'Lâmpadas LED não têm mercúrio, mas possuem placa eletrônica e componentes que exigem descarte separado.',
 'Entregue em pontos de coleta de lâmpadas ou de eletroeletrônicos (lojas de material de construção e supermercados). Não jogue no lixo comum.',
 'O vidro ou plástico, o alumínio da base e a placa eletrônica são separados e reciclados.',
 'lampada, led, luz, eletronico, lampada led', TRUE, 'eletronico'),
(1, 'Bateria de relógio (pilha botão)',
 'Pilhas botão de relógios, calculadoras e controles de carro são pequenas, mas podem conter lítio, prata ou mercúrio.',
 'Entregue nos mesmos coletores de pilhas e baterias. Guarde longe de crianças: se engolidas, causam queimaduras graves.',
 'As empresas de reciclagem recuperam os metais (como prata e lítio) com segurança.',
 'pilha botao, bateria, relogio, calculadora, litio, pilha, logistica reversa', TRUE, 'eletronico'),

-- ---------- DESCARTE ESPECIAL ----------
(1, 'Esmalte e acetona',
 'Esmaltes, acetona e removedores são inflamáveis e têm solventes tóxicos.',
 'Não despeje na pia nem no ralo. Use até o fim; frascos com restos devem ir para ecopontos que recebem produtos químicos. Frascos vazios e secos podem ir para a coleta de vidro.',
 'Os restos químicos são tratados em locais licenciados; o vidro limpo do frasco é reciclável.',
 'esmalte, acetona, removedor, manicure, solvente, inflamavel, cosmetico', FALSE, 'especial'),
(1, 'Seringa e agulha (uso doméstico)',
 'Seringas, agulhas e lancetas usadas em casa (insulina, por exemplo) são perfurocortantes e podem transmitir doenças.',
 'Coloque em recipiente rígido e fechado (garrafa PET grossa ou coletor próprio) e entregue em uma Unidade Básica de Saúde ou farmácia que receba. Nunca jogue solto no lixo.',
 'Não são recicladas: passam por tratamento térmico (incineração ou autoclave) para eliminar os riscos.',
 'seringa, agulha, lanceta, insulina, perfurocortante, saude, farmacia', FALSE, 'especial'),
(1, 'Produto de limpeza vencido',
 'Água sanitária, desinfetantes, limpa-forno e desentupidores vencidos são corrosivos ou tóxicos.',
 'Não misture produtos nem despeje grandes quantidades no ralo. Leve os restos em embalagem fechada a um ecoponto que receba produtos químicos; frascos vazios e enxaguados vão para a coleta de plásticos.',
 'Os restos são neutralizados e tratados por empresas licenciadas; as embalagens limpas são recicladas.',
 'produto de limpeza, agua sanitaria, cloro, desinfetante, soda caustica, quimico, vencido', FALSE, 'especial'),
(1, 'Isqueiro',
 'Isqueiros descartáveis contêm gás inflamável sob pressão e podem explodir na compactação do lixo.',
 'Use até acabar o gás. Isqueiros vazios vão para o lixo comum; com gás, entregue em ecopontos que recebam produtos perigosos.',
 'Isqueiros descartáveis quase não são reciclados; prefira modelos recarregáveis.',
 'isqueiro, gas, inflamavel, cigarro, fogo', FALSE, 'especial'),

-- ---------- ROUPAS E TECIDOS ----------
(2, 'Lençol e toalha velhos',
 'Lençóis, toalhas, cobertores e panos de prato velhos são tecidos que podem ganhar nova vida.',
 'Em bom estado, doe para abrigos e protetores de animais. Gastos, entregue em pontos de coleta de tecidos ou reaproveite como pano de limpeza.',
 'Os tecidos são desfiados e viram estopa, enchimento de almofadas, mantas e novos fios.',
 'lencol, toalha, cobertor, pano, tecido, cama, doacao, textil', FALSE, 'textil'),
(2, 'Bolsa e mochila velhas',
 'Bolsas, mochilas e malas misturam tecido, couro sintético, zíperes e partes metálicas.',
 'Se ainda servem, doe ou conserte. Rasgadas, entregue em pontos de coleta de tecidos ou de reciclagem têxtil; sem essa opção, lixo comum.',
 'Empresas de reciclagem têxtil separam tecido, metal e plástico e reaproveitam cada material.',
 'bolsa, mochila, mala, ziper, tecido, couro, doacao, textil', FALSE, 'textil'),

-- ---------- CONSTRUÇÃO E INERTES ----------
(2, 'Gesso',
 'Sobras de gesso, placas de drywall e molduras são resíduos de construção que se dissolvem lentamente na água e podem liberar sulfato no solo.',
 'Separe do entulho comum (o gesso contamina o lote) e leve a um ecoponto ou contrate caçamba que aceite gesso. Nunca descarte em terrenos baldios.',
 'O gesso pode ser reciclado e voltar para a indústria de cimento, para a agricultura (corretivo de solo) ou para novas placas.',
 'gesso, drywall, moldura, sanca, reforma, construcao', FALSE, 'construcao'),
(1, 'Telha de amianto',
 'Telhas e caixas d''água antigas de fibrocimento com amianto liberam fibras que causam doenças graves nos pulmões quando quebradas.',
 'Não quebre nem corte. Molhe as peças, embale em plástico e procure a prefeitura ou uma empresa licenciada para destinação de resíduos perigosos. Não coloque em caçamba comum.',
 'O amianto não é reciclável: vai para aterro de resíduos perigosos. Hoje o uso de amianto é proibido no Brasil.',
 'amianto, telha, fibrocimento, eternit, caixa d''agua, reforma, construcao, perigoso', FALSE, 'construcao'),

-- ---------- REJEITOS ----------
(2, 'Absorvente e papel higiênico usados',
 'Absorventes, papel higiênico, cotonetes e lenços umedecidos usados são rejeitos de higiene pessoal.',
 'Embrulhe e descarte no lixo comum (banheiro). Nunca jogue no vaso sanitário: entopem a rede de esgoto.',
 'Não são recicláveis. Coletores menstruais e absorventes de pano são alternativas reutilizáveis.',
 'absorvente, papel higienico, cotonete, lenco umedecido, higiene, banheiro, rejeito', FALSE, 'rejeito'),
(1, 'Bituca de cigarro',
 'As bitucas têm filtro de acetato de celulose (um plástico) impregnado de nicotina, alcatrão e metais pesados, substâncias tóxicas para a água e o solo.',
 'Apague bem e descarte em cinzeiro ou lixo comum, nunca no chão, em bueiros ou na natureza. Existem coletores e programas que recolhem bitucas para reciclagem.',
 'Programas específicos descontaminam os filtros e transformam o material em papel, peças plásticas ou adubo.',
 'bituca, cigarro, filtro, guimba, tabaco, nicotina, rejeito', FALSE, 'rejeito'),
(2, 'Esponja de cozinha',
 'Esponjas de lavar louça são de espuma de poliuretano com fibra abrasiva, materiais de reciclagem difícil.',
 'Descarte no lixo comum. Alguns programas de marcas recolhem esponjas usadas para reciclagem; troque por buchas vegetais, que são compostáveis.',
 'Por programas específicos, a espuma é triturada e reaproveitada em novos produtos plásticos.',
 'esponja, cozinha, louca, bucha, poliuretano, rejeito', FALSE, 'rejeito');
