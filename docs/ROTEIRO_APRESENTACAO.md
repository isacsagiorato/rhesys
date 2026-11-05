# Roteiro de demonstração do Rhesys

## Preparação

1. Execute o SQL principal e, se desejar a demonstração mais completa, `database/residuos_lote2.sql`.
2. Confirme que há 40 resíduos e que o servidor abre `http://localhost:8000`.
3. Abra duas janelas de navegador (uma normal e uma anônima). Entre como ofertante em uma e como interessado na outra.
4. Deixe o usuário administrador `admin@rhesys.com` disponível para os cadastros. Em ambiente de demonstração, sua senha inicial é `admin123`; troque-a fora desse ambiente.
5. Rode `php tests/integracao.php` antes da apresentação.

## Demonstração sugerida (10 a 12 minutos)

### 1. Apresentação e consulta (1 minuto)

- Mostre a página inicial e o menu responsivo.
- Em **Resíduos**, pesquise “pilha”, uma palavra-chave e um trecho da descrição.
- Abra o detalhe e destaque classificação, descarte, reciclagem, logística reversa e pontos compatíveis.

### 2. Compartilhamento completo (3 minutos)

- Na janela do ofertante, abra **Compartilhamentos → Oferecer item**.
- Selecione um resíduo, descreva estado e retirada e publique.
- Na janela do interessado, filtre por disponíveis e clique em **Tenho interesse**.
- Explique que auto-interesse e duplicidade são recusados pelo servidor.
- Na janela do ofertante, abra **Minhas ofertas**, mostre o interessado e aceite o interesse.
- Mostre o status “Reservado” e clique em **Marcar como concluído**.
- Use o filtro de status para localizar a oferta concluída.

### 3. Pontos de coleta e mapa (2 minutos)

- Abra **Pontos de Coleta**, selecione um resíduo no filtro e mostre a atualização da lista e do mapa.
- Clique em um marcador e depois no link do OpenStreetMap.
- Autenticado, abra **Cadastrar ponto**, informe coordenadas e selecione vários resíduos.
- Mostre a edição dos vínculos e o detalhe de um resíduo aceito.

### 4. Educação ambiental (1 a 2 minutos)

- Abra **Conteúdos** e um artigo existente.
- Cadastre um conteúdo com texto, fonte, data e imagem.
- Explique as validações de tipo/tamanho da imagem e o escape do conteúdo HTML.

### 5. Quiz e pontuação (2 minutos)

- Em **Quizzes**, abra **Cadastrar quiz e perguntas**.
- Crie um quiz, uma pergunta com quatro alternativas e indique o gabarito.
- Responda com o usuário autenticado e mostre pontuação, revisão e acertos/erros.
- Informe que o resultado fica salvo em `RESULTADO_QUIZ` e que a submissão anônima é direcionada ao login.

### 6. Fechamento técnico (1 minuto)

- Mostre o relatório dos 46 testes automatizados.
- Cite prepared statements, CSRF, XSS, hash de senha, sessão, autorização e transações.
- Reduza a janela ou use a ferramenta de dispositivo para mostrar os layouts de tablet e celular.

## Checklist final em celular físico

- [ ] Menu abre, fecha e não ultrapassa a largura da tela.
- [ ] Busca, filtros e botões podem ser tocados sem zoom.
- [ ] Cartões ocupam uma coluna e textos não ficam cortados.
- [ ] Mapa pode ser arrastado sem bloquear a rolagem da página.
- [ ] Formulários e mensagens de erro permanecem legíveis.
- [ ] O fluxo oferta → interesse → aceite funciona também no aparelho.

## Recuperação durante a demonstração

Se os serviços externos do mapa estiverem indisponíveis, use os cartões e os links de endereço. Se o cadastro com Google não tiver credenciais locais, demonstre o login tradicional; o fluxo OAuth não é necessário para as demais funcionalidades.
