# Rhesys — Base do Projeto

Estrutura inicial do sistema, em PHP puro + MySQL + Bootstrap (conforme RNF01, RNF02, RNF03).

## Estrutura de pastas

```
rhesys/
├── config/       -> conexão PDO, sessão, OAuth, CSRF e validações
├── includes/     -> header/footer reutilizáveis com Bootstrap
├── css/          -> identidade visual e layouts responsivos
├── js/           -> interações gerais e mapa Leaflet/OpenStreetMap
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
- Pontos de coleta: cadastro/edição, vínculos N:N, filtro, mapa e integração com o resíduo.
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

- `pages/residuos.php`: listagem de todos os resíduos, busca com `LIKE` em
  `nome`, `palavras_chave` e `descricao` (RF03) e página de detalhes com
  classificação, descarte, reciclagem e logística reversa (RF04–RF08).
- `pages/cadastro_residuo.php` (RF13): formulário com nome, descrição, forma
  de descarte, reciclagem, palavras-chave e checkbox de logística reversa;
  select de classificação vindo do banco. Exige login e CSRF. Após gravar,
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

## Compartilhamentos (Sprint 3)

- `pages/cadastro_compartilhamento.php`: publica uma oferta vinculada a um resíduo.
- `pages/compartilhamentos.php`: filtra ofertas e registra/cancela interesses sem duplicidade ou auto-interesse.
- `pages/meus_compartilhamentos.php`: mostra os interessados ao ofertante e permite aceitar, concluir ou tornar a oferta indisponível.
- Aceitar um interesse reserva a oferta e recusa os demais pendentes; concluir exige um interessado aceito. As alterações usam transações e `FOR UPDATE`.

## Pontos de coleta (Sprint 4)

- `pages/cadastro_ponto_coleta.php`: cadastra/edita endereço, coordenadas, contato, horário e resíduos aceitos em uma única transação.
- `pages/pontos_coleta.php`: lista e filtra por resíduo, exibe mapa Leaflet/OpenStreetMap e mantém links externos como fallback.
- `pages/residuos.php?id=N`: apresenta somente os pontos que aceitam o resíduo aberto.
- `js/mapa.js`: cria marcadores e popups usando nós DOM, sem injetar HTML dos dados.

## Educação e quizzes (Sprint 5)

- `pages/conteudos.php` e `pages/cadastro_conteudo.php`: listagem/detalhe públicos e cadastro autenticado com imagem de até 5 MB (JPG, PNG, WEBP ou GIF), fonte e data.
- `pages/cadastro_quiz.php`: cria quizzes e acessa o banco de perguntas.
- `pages/cadastro_pergunta.php`: cadastra enunciado, quatro alternativas distintas e gabarito.
- `pages/quiz.php`: executa o quiz, revisa respostas e grava a pontuação de usuários autenticados. Alternativas inválidas são tratadas como não respondidas, sem warnings.

## Como rodar localmente

1. Instale XAMPP/Laragon com PHP 8+ e MySQL/MariaDB, ou use `php -S localhost:8000` na raiz.
2. Crie o banco executando `database/rhesys_banco_dados.sql`. **Atenção:** esse script recria as tabelas e deve ser usado em uma instalação nova.
3. Em um banco antigo, preserve os dados e execute apenas as migrações necessárias: `database/migracao_google.sql` (uma vez, se ainda não tiver as colunas do Google) e `database/migracao_sprints_3_a_6.sql` (idempotente).
4. Execute `database/residuos_organicos.sql` para incluir os 10 orgânicos (script idempotente, também indicado para bancos existentes) e, opcionalmente, `database/residuos_lote2.sql` para acrescentar mais 35 itens. Com todos os lotes, a base fica com 50 resíduos.
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
