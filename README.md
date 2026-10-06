# Rhesys — Base do Projeto

Estrutura inicial do sistema, em PHP puro + MySQL + Bootstrap (conforme RNF01, RNF02, RNF03).

## Estrutura de pastas

```
rhesys/
├── config/
│   ├── conexao.php      -> conexão PDO com o banco (ajuste usuário/senha do MySQL)
│   ├── config.php       -> configurações gerais (nome do site, sessão, BASE_URL)
│   └── seguranca.php     -> CSRF (token por sessão) + rate limiting de login
├── includes/
│   ├── header.php       -> topo + menu de navegação, incluído em toda página
│   └── footer.php       -> rodapé, incluído em toda página
├── css/
│   └── style.css        -> estilos próprios, complementares ao Bootstrap
├── js/
│   └── script.js        -> scripts próprios
├── pages/
│   ├── sobre.php         -> RF02 (pronta)
│   ├── residuos.php      -> RF03/04/05/06/07/08 (placeholder)
│   ├── compartilhamentos.php -> compartilhamento entre usuários (placeholder)
│   ├── quiz.php           -> RF10/11/12 (placeholder)
│   ├── pontos_coleta.php  -> pontos de coleta no mapa (placeholder)
│   ├── cadastro.php       -> criação de conta (RF11: senha salva com password_hash)
│   ├── login.php          -> autenticação (password_verify contra o hash salvo)
│   └── logout.php         -> encerra a sessão
├── database/
│   └── rhesys_banco_dados.sql -> script de criação das 12 tabelas
└── index.php             -> RF01, página inicial (pronta)
```

## Cadastro e login (RF11)

- Em `pages/cadastro.php`, a senha **nunca** é salva em texto puro: o sistema grava
  apenas o hash gerado por `password_hash($senha, PASSWORD_DEFAULT)` (bcrypt).
- Em `pages/login.php`, a senha informada é conferida com `password_verify()`
  contra o hash armazenado na tabela `usuario`.
- Usuário admin de teste criado pelo SQL: `admin@rhesys.com` / senha `admin123`
  (também armazenada como hash bcrypt no script do banco).

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
- Logout total em `pages/logout.php` (limpa a sessão e o cookie).

## Como rodar localmente

1. Suba um servidor PHP local (XAMPP, Laragon, ou `php -S localhost:8000` na raiz do projeto).
2. Crie o banco executando `database/rhesys_banco_dados.sql` no MySQL.
3. Ajuste `config/conexao.php` com o usuário/senha do seu MySQL local.
4. Ajuste `BASE_URL` em `config/config.php` conforme a pasta onde o projeto está hospedado
   (ex: se acessar via `http://localhost/rhesys/`, deixe `BASE_URL` como `/rhesys/`).
5. Acesse `index.php` no navegador.

## Próximos passos sugeridos

- Implementar `pages/residuos.php`: listagem + busca (RF03/04) usando `nome LIKE` e `descricao LIKE`.
- Implementar `pages/quiz.php`: listar quizzes, exibir perguntas, calcular pontuação (RF10/11/12).
- Implementar `pages/compartilhamentos.php`: listar itens ofertados, permitir demonstrar interesse.
- Implementar `pages/pontos_coleta.php`: listar pontos e (opcional) integrar com mapa via coordenadas.
- Sistema de cadastro/login já implementado (`pages/cadastro.php`, `pages/login.php`,
  `pages/logout.php`) com hash de senha via `password_hash()` conforme RF11.
