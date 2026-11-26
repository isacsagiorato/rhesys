# Rhesys — Base do Projeto

Estrutura inicial do sistema, em PHP puro + MySQL + Bootstrap (conforme RNF01, RNF02, RNF03).

## Estrutura de pastas

```
rhesys/
├── config/       -> conexão PDO, sessão, OAuth, CSRF e validações
├── includes/     -> header/footer reutilizáveis com Bootstrap
├── css/          -> identidade visual e layouts responsivos
├── js/           -> interações gerais, chat, notificações e autocompletar
├── pages/        -> telas públicas, autenticação e gestão dos módulos
├── database/     -> modelo completo, migrações e lotes de resíduos
├── docs/         -> escopo final para o TCC e roteiro de apresentação
├── tests/        -> suíte automatizada HTTP + banco de dados
└── index.php     -> página inicial
```

### Módulos implementados

- Cadastro/login tradicional e Google, sessão, logout e proteção de rotas.
- Resíduos e classificações: cadastro, listagem, busca e detalhes (RF03–RF08/RF13).
- Compartilhamentos: oferta, filtro, interesse, painel do ofertante, aceite, conclusão e cancelamentos.
- Conteúdos educativos: cadastro com imagem, listagem e detalhe (RF09).
- Quizzes: cadastro de quiz/perguntas, respostas, revisão e pontuação salva (RF10–RF12).

## Cadastro e login (RF11)

- Em `pages/cadastro.php`, a senha **nunca** é salva em texto puro: o sistema grava
  apenas o hash gerado por `password_hash($senha, PASSWORD_DEFAULT)` (bcrypt).
- Em `pages/login.php`, a senha informada é conferida com `password_verify()`
  contra o hash armazenado na tabela `usuario`.
- Usuário admin de teste criado pelo SQL: `admin@rhesys.com` / senha `admin123`
  (também armazenada como hash bcrypt no script do banco).

## Cadastro somente com e-mail existente

- O cadastro (`pages/cadastro.php`) valida o e-mail em três camadas
  (`config/validacao_email.php`):
  1. **Formato** — `filter_var(..., FILTER_VALIDATE_EMAIL)`;
  2. **Não descartável** — bloqueia provedores temporários
     (10minutemail, mailinator etc. — lista em `DOMINIOS_DESCARTAVEIS`);
  3. **Domínio existente** — consulta o DNS do domínio (`checkdnsrr`):
     precisa ter registro **MX** (servidores de e-mail) ou A/AAAA.
     Endereços com domínio inexistente (digitados/inventados) são recusados.
- Limitação: a consulta DNS prova que o **domínio** existe e aceita e-mail,
  mas não que a **caixa** específica existe — para isso seria necessário
  enviar um e-mail de confirmação (SMTP), o que exige um servidor de e-mail.

## Login com Google (OAuth 2.0)

- Botões **"Entrar com Google"** / **"Criar conta com Google"** em
  `pages/login.php` e `pages/cadastro.php` (fluxo em PHP puro, sem
  bibliotecas — `config/google.php`, `pages/google_login.php`,
  `pages/google_callback.php`).
- Contas Google têm o **e-mail sempre verificado** pelo Google, o que
  complementa a validação de e-mail existente acima: a conta é criada
  automaticamente (tipo `comum`) na primeira vez que o usuário entra.
- Se o e-mail já foi cadastrado pelo fluxo tradicional, a conta Google é
  **vinculada** ao usuário existente (coluna `google_id`).
- Segurança do fluxo: `state` (CSRF do OAuth) gerado por sessão e conferido
  com `hash_equals()` no callback + `session_regenerate_id(true)` ao entrar.
- **Como ativar**: criar credenciais OAuth no
  [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
  (tipo "Aplicativo da Web", URI de redirecionamento
  `http://localhost:8000/pages/google_callback.php`) e preencher
  `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET` em `config/google.php`.
  Instruções detalhadas estão no comentário do próprio arquivo.
- Bancos já existentes: executar `database/migracao_google.sql` **uma vez**
  (adiciona `google_id`, `email_verificado` e `avatar` à tabela `usuario`).

## Segurança (config/seguranca.php)

- **CSRF**: todo formulário POST (login, cadastro e quiz) envia um token
  gerado por sessão (`csrf_field()`); o servidor confere com `hash_equals()`
  em `verificar_csrf()` e recusa a requisição se o token for inválido.
- **Rate limiting de login**: após 5 tentativas falhas (chave = hash
  SHA-256 de IP + e-mail), o acesso é bloqueado por 15 minutos
  (tabela `tentativa_login`). Um login bem-sucedido zera o contador.
  Constantes `LOGIN_MAX_TENTATIVAS` e `LOGIN_BLOQUEIO_MINUTOS` em
  `config/seguranca.php`.
- **Mostrar/ocultar senha**: botão com ícone nos campos de senha de
  `login.php` e `cadastro.php` (JS em `js/script.js`, sem bibliotecas).
  Ao **digitar**, os caracteres ficam visíveis por 1,5 s e voltam a
  virar bolinhas (pré-visualização); se o usuário clicar no olho, a
  escolha manual trava o estado até ele clicar de novo.

## Controle de sessão ($_SESSION)

- `config/config.php` inicia a sessão em **todas** as páginas e centraliza
  o controle; helpers `usuario_logado()`, `exige_login()` e `encerrar_login()`.
- No login/cadastro são gravados `id_usuario`, `nome_usuario`, `tipo_usuario`,
  `sessao_iniciada` e `ultimo_acesso`; o `header.php` exibe "Olá, {nome}"
  enquanto houver sessão — o usuário permanece logado ao navegar.
- **Expiração**: 8 h sem visitar nenhuma página (`SESSAO_INATIVIDADE`) ou
  24 h após o login (`SESSAO_TEMPO_MAXIMO`); ao expirar, o GET é redirecionado
  para `login.php?expirada=1` com aviso. Cada página visitada renova o relógio
  de inatividade.
- **Cookies endurecidos**: `HttpOnly`, `SameSite=Lax`, `use_strict_mode`,
  `use_only_cookies` (ID nunca aparece na URL) e `session_regenerate_id(true)`
  no login (anti fixation).
- Páginas restritas: chamar `exige_login()` logo após o require de `config.php`.
  **Proteção ativa** em `pages/compartilhamentos.php` e no **resultado do quiz**
  (`pages/quiz.php`): sem login, o POST que gera o resultado redireciona para o login.
- Logout total em `pages/logout.php` (limpa a sessão e o cookie).

## Resíduos (Sprint 2 — consulta e cadastro)

- Busca tolerante a erros (`config/busca.php`): ignora acentos, maiúsculas, plural e
  letras dobradas, entende grafias de mesmo som (ss/ç/s/z, ch/x, lh/li...) e aceita pequenos
  erros de digitação. Quando corrige, mostra "Mostrando resultados para...".
- Busca automática (`js/autocompletar.js` + `pages/sugestoes.php`): sugestões a partir da
  primeira letra, com foto, tipo e classe; destaca o trecho digitado; "Você quis dizer…"
  clicável; atalho "Ver todos" quando a busca é um tipo de material ("eletrônicos", "metais");
  buscas recentes e mais buscados ao clicar no campo vazio; navegação por teclado.
  Ex.: "jeladeira" → geladeira, "garafa pet" → Garrafa PET, "oleo de cosinha" → óleo de cozinha.
- `database/palavras_chave_ampliadas.sql`: amplia as palavras-chave de todos os resíduos com
  sinônimos, nomes populares, marcas e exemplos ("remédio", "latinha", "durex"...), em média
  13 termos por resíduo. Idempotente; execute depois dos lotes.
- `pages/residuos.php`: listagem agrupada por **tipo de material** (metais, plásticos,
  papel, vidros, orgânicos, eletroeletrônicos, descarte especial, construção, roupas e
  rejeitos), busca com `LIKE` em `nome`, `palavras_chave` e `descricao` (RF03) e filtros
  combináveis por tipo, classe ABNT NBR 10004 e logística reversa (todos na URL, sem
  JavaScript). A página de detalhes mostra classe, como descartar, reciclagem, logística
  reversa, onde entregar e outros resíduos do mesmo tipo (RF04–RF08).
- `config/residuos.php`: tipos de material, selos/cores das classes (I vermelho,
  II A âmbar, II B cinza, como em Conteúdos) e escolha da foto de cada resíduo.
- `pages/cadastro_residuo.php` (RF13): formulário com nome, descrição, forma
  de descarte, reciclagem, palavras-chave, tipo de material e checkbox de logística
  reversa; select de classificação vindo do banco. Exige login e CSRF. Após gravar,
  redireciona para a página de detalhes do resíduo criado.
- `pages/cadastro_classificacao.php`: tela simples para cadastrar as classes
  (Classe I - Perigosos, Classe II A - Não Inertes, Classe II B - Inertes).
  Exige login e CSRF; lista as classes já cadastradas com a contagem de resíduos.
- Lote inicial de dados: `database/rhesys_banco_dados.sql` já insere as
  3 classificações e 5 resíduos de exemplo (RF13).
- Lote orgânico: `database/residuos_organicos.sql` insere 10 resíduos com
  orientações de compostagem e palavras-chave para os filtros. É idempotente
  e também pode ser executado em um banco existente.
- Lote 2 (popular o banco): `database/residuos_lote2.sql` insere mais 35
  resíduos comuns do dia a dia (Classe I, II A e II B). Com os dados iniciais
  e o lote orgânico, o total é 50 itens. Execute-o **depois** do script principal:
  `mysql -u root -p < database/residuos_lote2.sql`.
- Lote 3: `database/residuos_lote3.sql` cria a coluna `residuo.tipo_material`,
  classifica todos os resíduos por tipo, corrige "Garrafas e potes de vidro" para
  Classe II B (inerte) e insere mais 36 resíduos (caixa de pizza, cabos e
  carregadores, gesso, telha de amianto, seringas, bitucas etc.). Idempotente;
  execute **depois** dos lotes anteriores.
- Lote 4: `database/residuos_lote4.sql` insere mais 36 resíduos (marmitex, cápsula de café,
  tablet, extintor, radiografia, máscara descartável, nota fiscal etc.), já com classe e tipo
  de material. Idempotente; execute depois do lote 3.
- Lote 5: `database/residuos_lote5.sql` insere mais 90 resíduos (botijão de gás, colchão,
  videogame, cigarro eletrônico, painel solar, fio de cobre, lã de vidro, cocô de animal etc.),
  com classe, tipo e palavras-chave ricas. Idempotente; execute depois do lote 4.
- Lote 6: `database/residuos_lote6.sql` insere mais 288 resíduos e completa **50 resíduos em
  cada um dos 10 tipos de material** (vidro, roupas e tecidos, construção, papel, metal,
  rejeito, especial, orgânico, plástico e eletrônico). Idempotente; execute depois do lote 5.
  Total: 500 resíduos.
- Fotos dos resíduos: `img/fotos/residuos/` traz uma foto real para cada um dos 500 itens,
  todas do Wikimedia Commons sob licenças livres. O crédito (autor + licença) aparece
  na página de detalhes; a lista completa de fontes está em `docs/FONTES_FOTOS_RESIDUOS.md`
  (dados usados pelo site em `config/fontes_fotos_residuos.php`).

## Compartilhamentos (Sprint 3)

- `pages/cadastro_compartilhamento.php`: publica uma oferta vinculada a um resíduo.
- `pages/compartilhamentos.php`: filtra ofertas e registra/cancela interesses sem duplicidade ou auto-interesse.
- `pages/meus_compartilhamentos.php`: mostra os interessados ao ofertante e permite aceitar, concluir ou tornar a oferta indisponível.
- Aceitar um interesse reserva a oferta e recusa os demais pendentes; concluir exige um interessado aceito. As alterações usam transações e `FOR UPDATE`.

## Notificações (sino + e-mail)

- O sino no topo mostra as notificações não lidas e se atualiza sozinho a cada 30 segundos;
  a lista completa fica em `pages/notificacoes.php` (marcar como lida, excluir, limpar lidas).
- Avisos dos compartilhamentos chegam no site **e no e-mail cadastrado**: novo interesse,
  interesse cancelado, aceito, recusado, material retirado, indisponível ou excluído, além das
  boas-vindas no cadastro. Novidades do site (conteúdo e quiz novos) e edições
  de material aparecem só no sino, para não lotar a caixa de entrada.
- Os textos de todas as notificações ficam em `config/notificacoes.php` (uma função por evento).
- Banco existente: execute `database/notificacoes.sql` para criar a tabela `notificacao`.
- **Envio de e-mail:** copie `config/email_local.exemplo.php` para `config/email_local.php` e
  preencha os dados SMTP (no Gmail, use uma *senha de app*). Sem esse arquivo, os e-mails não
  são enviados: ficam salvos em `storage/emails/` (arquivos .eml) para conferência.

## Doação ou venda

- Ao oferecer um material, a pessoa escolhe **Doação** (grátis) ou **Venda** (com preço e a opção "aceito negociar").
- Etiqueta "Grátis" ou o preço nos cards, no detalhe, em "Meus compartilhamentos" e no chat; filtro Tudo / Doações / À venda.
- Material vendido aparece como "Vendido"; o chat de venda traz sugestões de negociação e um aviso de segurança.
- O Rhesys **não intermedeia pagamentos**: valor e forma de pagamento são combinados pelo chat.
- Banco existente: execute `database/venda.sql` (os materiais já cadastrados ficam como doação).

## Chat entre quem oferece e o interessado

- Cada interesse em um material abre uma conversa entre a pessoa interessada e quem ofereceu
  (`pages/conversas.php`): lista de conversas à esquerda e mensagens à direita (no celular, uma tela por vez).
- Dá para conversar enquanto o interesse está pendente ou aceito (e depois da retirada, para quem foi
  aceito); interesse recusado/cancelado ou material indisponível deixa a conversa só para leitura.
- As mensagens chegam sozinhas (consulta a cada 3,5 s), com "visto", sugestões de mensagem, Enter para
  enviar e Shift+Enter para quebrar linha. Quem ofereceu pode aceitar o interesse direto pelo chat.
- Ícone de balão no topo com as mensagens não lidas; cada nova mensagem gera um aviso no sino e no
  e-mail, agrupado por conversa (várias mensagens seguidas = um aviso só).
- **Fotos no chat:** botão de anexo com *Galeria* (foto salva) e *Câmera* (no celular abre a câmera do aparelho;
  no computador usa a webcam e, se não houver webcam, avisa e sugere a Galeria). Também dá para colar (Ctrl+V)
  ou arrastar uma imagem. O navegador reduz a foto para até 1600 px antes de enviar e o servidor remove os
  dados EXIF (como a localização GPS). As fotos ficam em `storage/chat/` (sem acesso direto) e só são entregues
  por `pages/chat_imagem.php` para os dois participantes da conversa.
- Banco existente: execute `database/chat.sql` para criar a tabela `mensagem` (e a coluna da foto).


## Educação e quizzes (Sprint 5)

- `pages/conteudos.php` e `pages/cadastro_conteudo.php`: listagem/detalhe públicos e cadastro autenticado com imagem de até 5 MB (JPG, PNG, WEBP ou GIF), fonte e data.
- `pages/cadastro_quiz.php`: cria quizzes e acessa o banco de perguntas.
- `pages/cadastro_pergunta.php`: cadastra enunciado, quatro alternativas distintas e gabarito.
- `pages/quiz.php`: executa o quiz, revisa respostas e grava a pontuação de usuários autenticados. Alternativas inválidas são tratadas como não respondidas, sem warnings.

## Como rodar localmente

1. Instale XAMPP/Laragon com PHP 8+ e MySQL/MariaDB, ou use `php -S localhost:8000` na raiz.
2. Crie o banco executando `database/rhesys_banco_dados.sql`. **Atenção:** esse script recria as tabelas e deve ser usado em uma instalação nova.
3. Em um banco antigo, preserve os dados e execute apenas as migrações necessárias: `database/migracao_google.sql` (uma vez, se ainda não tiver as colunas do Google) e `database/migracao_sprints_3_a_6.sql` (idempotente).
4. Execute `database/residuos_organicos.sql` para incluir os 10 orgânicos (script idempotente, também indicado para bancos existentes) e, opcionalmente, `database/residuos_lote2.sql` para acrescentar mais 35 itens `database/residuos_lote3.sql` (tipo de material + 36 itens) `database/residuos_lote4.sql` (mais 36 itens) , `database/palavras_chave_ampliadas.sql` (termos de busca) , `database/residuos_lote5.sql` (mais 90 itens) e, por fim, `database/residuos_lote6.sql` (mais 288 itens). Com todos os lotes, a base fica com 500 resíduos (50 por tipo de material).
5. A conexão usa `root` sem senha e banco `rhesys` por padrão. Ajuste `config/conexao.php` ou defina `RHESYS_DB_HOST`, `RHESYS_DB_PORT`, `RHESYS_DB_NAME`, `RHESYS_DB_USER` e `RHESYS_DB_PASSWORD`.
6. `BASE_URL` é detectada automaticamente. Acesse `index.php`; com o comando acima, use `http://localhost:8000`.

## Testes HTTP com Postman

- Coleção pronta em `Rhesys_Testes.postman_collection.json` (na raiz do projeto):
  17 requisições que testam listagem, busca LIKE (nome, descrição, palavra-chave,
  curinga `%`), página de detalhes, proteção de rota, login com CSRF e o cadastro
  de resíduo (RF13), incluindo casos negativos (CSRF inválido e nome duplicado).
- Importe o arquivo no Postman (Import → File) e rode com **Collection Runner**,
  **em ordem**: os testes T10 extraem automaticamente o token CSRF e o cookie de
  sessão das respostas anteriores.
- Variável `base_url` (padrão `http://localhost:8000`) pode ser ajustada em
  Collection → Variables se o servidor estiver em outra porta/pasta.

## Testes automatizados de integração

Execute `php tests/integracao.php` (no XAMPP Windows, use `C:\xampp\php\php.exe tests\integracao.php`).

A suíte cria um banco isolado, inicia um servidor PHP em porta livre e realiza **50 verificações** de cadastro, login, busca, filtros orgânicos, compartilhamento, autorização, coleta, conteúdo e quiz. **O banco `rhesys_test` é apagado e recriado a cada execução**; o banco principal não é usado. É possível trocar esse nome por `RHESYS_TEST_DB_NAME`, sempre com sufixo `_test`. As credenciais usam as mesmas variáveis de ambiente da aplicação.

## Documentação final e apresentação

- `docs/DOCUMENTACAO_TCC.md`: escopo final, requisitos complementares, regras de negócio, segurança e evidências.
- `docs/ROTEIRO_APRESENTACAO.md`: roteiro de demonstração e checklist para celular físico.
- A validação visual automatizada usa 390 px, 768 px e 1440 px; a confirmação em um aparelho físico permanece como checklist manual da apresentação.
