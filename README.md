# Rhesys — Base do Projeto

Estrutura inicial do sistema, em PHP puro + MySQL + Bootstrap (conforme RNF01, RNF02, RNF03).

## Estrutura de pastas

```
rhesys/
├── config/
│   ├── conexao.php      -> conexão PDO com o banco (ajuste usuário/senha do MySQL)
│   └── config.php       -> configurações gerais (nome do site, sessão, BASE_URL)
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
│   └── pontos_coleta.php  -> pontos de coleta no mapa (placeholder)
├── database/
│   └── rhesys_banco_dados.sql -> script de criação das 11 tabelas
└── index.php             -> RF01, página inicial (pronta)
```

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
- Criar sistema de cadastro/login de usuário (necessário para compartilhamento e quiz salvarem `id_usuario`).
