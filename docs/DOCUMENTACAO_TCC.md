# Documentação do escopo final — Rhesys

## 1. Visão geral

O Rhesys é uma aplicação web em PHP 8, MySQL/MariaDB, Bootstrap 5 e JavaScript para orientar o descarte de resíduos, incentivar o reaproveitamento e apoiar a educação ambiental. O modelo contém 11 tabelas de negócio e a tabela técnica `tentativa_login`, usada no bloqueio temporário de tentativas de acesso.

## 2. Requisitos implementados

- **RF01 — Página inicial:** apresenta o sistema e os acessos às funcionalidades.
- **RF02 — Sobre:** descreve objetivos, público e orientações do projeto.
- **RF03 — Consulta de resíduos:** lista e pesquisa por nome, descrição e palavras-chave com prepared statements e escape dos curingas do `LIKE`. A seção de orgânicos inclui explicação, imagem e filtros rápidos para alimentos e materiais de jardinagem.
- **RF04–RF08 — Orientação de descarte:** apresenta classificação, descrição, descarte, reciclagem, logística reversa e pontos de coleta compatíveis.
- **RF09 — Educação ambiental:** lista e detalha conteúdos; usuários autenticados cadastram título, texto, imagem, fonte e data.
- **RF10–RF12 — Quiz:** cadastro de quiz e perguntas, apresentação das alternativas, cálculo da pontuação, revisão e gravação do resultado vinculado ao usuário autenticado.
- **RF13 — Cadastro de resíduos:** formulário autenticado com classificação, descrição, descarte, reciclagem, palavras-chave e logística reversa.

### Redação sugerida para os requisitos complementares

A numeração abaixo deve ser adequada ao relatório do TCC, pois o plano de desenvolvimento informa que o compartilhamento ainda não estava no documento original.

- **Compartilhamento de resíduos:** o sistema deve permitir que um usuário autenticado publique uma oferta vinculada a um resíduo cadastrado e informe sua descrição.
- **Consulta de compartilhamentos:** o sistema deve listar ofertas e permitir filtrá-las pelos status disponível, reservado, concluído e indisponível.
- **Manifestação de interesse:** o sistema deve permitir que um usuário manifeste interesse em uma oferta de outro usuário, sem duplicidades, e cancele seu interesse.
- **Gestão pelo ofertante:** o sistema deve apresentar ao ofertante os interessados, permitir aceitar um interesse, concluir o compartilhamento ou tornar a oferta indisponível.
- **Pontos de coleta:** o sistema deve cadastrar pontos com coordenadas, contato e horário, associar os resíduos aceitos, filtrar por resíduo e exibir os pontos em mapa.

## 3. Regras de negócio do compartilhamento

1. O autor de uma oferta não pode demonstrar interesse nela.
2. Um usuário possui no máximo um registro de interesse por oferta; um interesse cancelado/recusado pode ser reaberto.
3. Ao aceitar um interesse, a oferta fica reservada e os demais interesses pendentes são recusados.
4. Somente uma oferta reservada com interessado aceito pode ser concluída.
5. Cancelar um interesse aceito torna a oferta novamente disponível.
6. Tornar uma oferta indisponível cancela seus interesses ativos.
7. As transições usam transações e bloqueio de registro (`FOR UPDATE`) para evitar concorrência inconsistente.

## 4. Segurança

- Senhas com `password_hash()` e verificação com `password_verify()`.
- Sessões com cookies `HttpOnly`, `SameSite=Lax`, expiração e regeneração de ID.
- CSRF em todos os novos formulários e nos formulários existentes.
- Consultas parametrizadas e saída HTML escapada contra XSS.
- Autorização no servidor para ações do ofertante e do interessado.
- Upload limitado a 5 MB, com validação de upload, MIME e assinatura da imagem; nomes aleatórios e extensões permitidas.
- OAuth 2.0 com `state` e verificação do e-mail pelo Google.
- Bloqueio temporário de tentativas repetidas de login.

## 5. Evidências de validação

A suíte `tests/integracao.php` executa 50 verificações HTTP e no banco, em um banco isolado recriado a cada execução. Abrange o fluxo cadastro → busca → oferta → interesse → aceite → conclusão, além de filtros orgânicos, cancelamentos, CSRF, XSS, autorização, N:N, upload, cadastro de quiz/pergunta e pontuação.

A interface também deve ser conferida nas larguras de 390 px, 768 px e 1440 px. O checklist de apresentação contém a verificação final em um celular físico, que não pode ser automatizada pelo repositório.

## 6. Limitações operacionais

- O mapa depende da disponibilidade do Leaflet e dos tiles do OpenStreetMap; endereços e links externos permanecem disponíveis como alternativa.
- A validação tradicional de e-mail confirma domínio, não a existência da caixa; o fluxo Google usa e-mail verificado pelo provedor.
- Os cadastros administrativos seguem a regra já adotada pelo projeto: exigem autenticação, sem separação adicional de permissões por perfil.
