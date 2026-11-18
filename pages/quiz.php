<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';

// RNF11 — Proteção de rotas: o RESULTADO do quiz só pode ser visto por
// usuário logado. O teste é feito ANTES do require do header (que já
// imprime HTML), permitindo o redirect com header('Location: ...').
// O POST do formulário de respostas cai aqui mesmo: ele gera o resultado.
// O parâmetro ?aviso=resultado_quiz faz o login.php exibir a mensagem
// "é necessário fazer login para ver o resultado do quiz".
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && (int) ($_GET['id'] ?? 0) > 0
    && !usuario_logado()
) {
    header('Location: ' . BASE_URL . 'pages/login.php?aviso=resultado_quiz');
    exit;
}

$titulo_pagina = 'Quizzes';
require __DIR__ . '/../includes/header.php';

// Parâmetros
$id_quiz = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$respostas = isset($_POST['respostas']) && is_array($_POST['respostas']) ? $_POST['respostas'] : [];

// ============================================================
// ETAPA 3: Resultado do quiz (formulário enviado)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_quiz > 0) {
    // CSRF: recusa requisição forjada (token ausente ou de outra sessão)
    if (!verificar_csrf()) {
        echo '<div class="alert alert-danger">Sessão expirada ou formulário inválido. '
            . '<a href="quiz.php?id=' . $id_quiz . '">Recarregue a página</a> e tente novamente.</div>';
        require __DIR__ . '/../includes/footer.php';
        exit;
    }

    // Buscar o quiz
    $sql_quiz = "SELECT * FROM quiz WHERE id_quiz = :id";
    $stmt_quiz = $pdo->prepare($sql_quiz);
    $stmt_quiz->bindValue(':id', $id_quiz, PDO::PARAM_INT);
    $stmt_quiz->execute();
    $quiz = $stmt_quiz->fetch();

    if (!$quiz) {
        echo '<div class="alert alert-warning">Quiz não encontrado.</div>';
        echo '<a href="quiz.php" class="btn btn-success">Voltar</a>';
        require __DIR__ . '/../includes/footer.php';
        exit;
    }

    // Buscar perguntas
    $sql_perguntas = "SELECT * FROM pergunta WHERE id_quiz = :id ORDER BY id_pergunta";
    $stmt_perguntas = $pdo->prepare($sql_perguntas);
    $stmt_perguntas->bindValue(':id', $id_quiz, PDO::PARAM_INT);
    $stmt_perguntas->execute();
    $perguntas = $stmt_perguntas->fetchAll();

    // Calcular pontuação
    $acertos = 0;
    $total = count($perguntas);

    foreach ($perguntas as $p) {
        $id_pergunta = $p['id_pergunta'];
        $resposta_usuario = $respostas[$id_pergunta] ?? null;
        if (!is_string($resposta_usuario) || !in_array($resposta_usuario, ['a', 'b', 'c', 'd'], true)) {
            $resposta_usuario = null;
        }
        $respostas[$id_pergunta] = $resposta_usuario;
        if ($resposta_usuario === $p['resposta_correta']) {
            $acertos++;
        }
    }

    $pontuacao = $total > 0 ? round(($acertos / $total) * 100) : 0;

    // RNF11: login já é exigido pela proteção no topo deste arquivo,
    // então o resultado é gravado sempre (tabela resultado_quiz).
    $sql_resultado = "INSERT INTO resultado_quiz (id_usuario, id_quiz, pontuacao)
                      VALUES (:id_usuario, :id_quiz, :pontuacao)";
    $stmt_resultado = $pdo->prepare($sql_resultado);
    $stmt_resultado->bindValue(':id_usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
    $stmt_resultado->bindValue(':id_quiz', $id_quiz, PDO::PARAM_INT);
    $stmt_resultado->bindValue(':pontuacao', $pontuacao, PDO::PARAM_INT);
    $stmt_resultado->execute();
    ?>

    <a href="quiz.php" class="btn btn-outline-success mb-3"><i class="bi bi-arrow-left me-1"></i>Voltar aos quizzes</a>

    <div class="card shadow-sm mb-4">
        <div class="card-body text-center p-4 p-lg-5">
            <p class="section-tag mb-3"><?php echo htmlspecialchars($quiz['titulo']); ?></p>
            <div class="score-anel" style="--p: <?php echo (int) $pontuacao; ?>;" role="img" aria-label="Pontuação: <?php echo (int) $pontuacao; ?>%">
                <div><span class="score-big"><?php echo $pontuacao; ?>%</span></div>
            </div>
            <p class="fs-5">
                Você acertou <strong><?php echo $acertos; ?></strong> de <strong><?php echo $total; ?></strong> perguntas
            </p>
            <?php if ($pontuacao >= 70): ?>
                <div class="alert alert-success">
                    <strong>Parabéns!</strong> Você tem um bom conhecimento sobre gestão de resíduos.
                </div>
            <?php elseif ($pontuacao >= 50): ?>
                <div class="alert alert-warning">
                    <strong>Bom esforço!</strong> Continue aprendendo sobre descarte de resíduos.
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <strong>Não desanime!</strong> Revise o conteúdo sobre gestão de resíduos e tente novamente.
                </div>
            <?php endif; ?>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a href="quiz.php?id=<?php echo $id_quiz; ?>" class="btn btn-success"><i class="bi bi-arrow-repeat me-1"></i>Tentar novamente</a>
                <a href="conteudos.php" class="btn btn-outline-success"><i class="bi bi-book me-1"></i>Revisar conteúdos</a>
            </div>
        </div>
    </div>

    <!-- Revisão das respostas -->
    <h2 class="h4 mb-3">Revisão das respostas</h2>
    <div class="list-group mb-4">
        <?php foreach ($perguntas as $index => $p): 
            $id_pergunta = $p['id_pergunta'];
            $resposta_usuario = isset($respostas[$id_pergunta]) ? $respostas[$id_pergunta] : null;
            $correta = $resposta_usuario === $p['resposta_correta'];
        ?>
            <div class="list-group-item revisao-item <?php echo $correta ? 'revisao-certa' : 'revisao-errada'; ?> p-3">
                <p class="mb-2">
                    <strong><?php echo ($index + 1); ?>.</strong>
                    <?php echo htmlspecialchars($p['enunciado']); ?>
                </p>
                <p class="mb-1 small">
                    Sua resposta:
                    <?php if ($resposta_usuario): ?>
                        <strong><?php echo strtoupper($resposta_usuario); ?></strong> —
                        <?php echo htmlspecialchars($p['alternativa_' . $resposta_usuario]); ?>
                    <?php else: ?>
                        <em>Não respondida</em>
                    <?php endif; ?>
                    <?php if ($correta): ?>
                        <span class="badge-soft ms-1"><i class="bi bi-check-lg"></i> Correta</span>
                    <?php else: ?>
                        <span class="badge-classe badge-classe-sm classe-1 ms-1"><i class="bi bi-x-lg"></i> Incorreta</span>
                    <?php endif; ?>
                </p>
                <?php if (!$correta): ?>
                    <p class="mb-0 small">
                        Resposta correta:
                        <strong><?php echo strtoupper($p['resposta_correta']); ?></strong> —
                        <?php echo htmlspecialchars($p['alternativa_' . $p['resposta_correta']]); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="form-acoes">
        <a href="quiz.php?id=<?php echo $id_quiz; ?>" class="btn btn-success">Refazer quiz</a>
        <a href="quiz.php" class="btn btn-outline-success">Outros quizzes</a>
    </div>

    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// ============================================================
// ETAPA 2: Exibir perguntas do quiz selecionado
// ============================================================
if ($id_quiz > 0) {
    $sql_quiz = "SELECT * FROM quiz WHERE id_quiz = :id";
    $stmt_quiz = $pdo->prepare($sql_quiz);
    $stmt_quiz->bindValue(':id', $id_quiz, PDO::PARAM_INT);
    $stmt_quiz->execute();
    $quiz = $stmt_quiz->fetch();

    if (!$quiz) {
        echo '<div class="alert alert-warning">Quiz não encontrado.</div>';
        echo '<a href="quiz.php" class="btn btn-success">Voltar</a>';
        require __DIR__ . '/../includes/footer.php';
        exit;
    }

    $sql_perguntas = "SELECT * FROM pergunta WHERE id_quiz = :id ORDER BY id_pergunta";
    $stmt_perguntas = $pdo->prepare($sql_perguntas);
    $stmt_perguntas->bindValue(':id', $id_quiz, PDO::PARAM_INT);
    $stmt_perguntas->execute();
    $perguntas = $stmt_perguntas->fetchAll();
    ?>

    <a href="quiz.php" class="btn btn-outline-success mb-3"><i class="bi bi-arrow-left me-1"></i>Voltar aos quizzes</a>

    <div class="card shadow-sm">
        <div class="card-body p-4 p-lg-5">
            <span class="section-tag">Quiz · <?php echo count($perguntas); ?> <?php echo count($perguntas) === 1 ? 'pergunta' : 'perguntas'; ?></span>
            <h1 class="fs-2 mt-1 mb-2"><?php echo htmlspecialchars($quiz['titulo']); ?></h1>
            <p class="text-muted mb-4"><?php echo htmlspecialchars($quiz['descricao']); ?></p>

            <?php if (!usuario_logado()): ?>
                <!-- Aviso prévio: sem login o resultado do quiz não é exibido -->
                <div class="alert alert-warning d-flex align-items-start gap-2">
                    <i class="bi bi-exclamation-triangle fs-5 mt-1"></i>
                    <div>
                        <strong>Atenção:</strong> é necessário fazer login para ter o resultado do quiz.
                        <a href="login.php">Entrar</a> ou <a href="cadastro.php">criar conta</a> antes de responder —
                        se responder sem login, você será levado à tela de login e precisará responder novamente.
                    </div>
                </div>
            <?php endif; ?>

            <?php if (count($perguntas) === 0): ?>
                <div class="alert alert-info">Este quiz ainda não possui perguntas cadastradas.</div>
            <?php else: ?>
                <form method="POST" action="quiz.php?id=<?php echo $id_quiz; ?>" id="form-quiz">
                    <?php echo csrf_field(); ?>
                    <div class="quiz-progresso" aria-live="polite">
                        <div class="d-flex justify-content-between small fw-semibold mb-1">
                            <span>Progresso</span>
                            <span><span id="quiz-respondidas">0</span> de <?php echo count($perguntas); ?> respondidas</span>
                        </div>
                        <div class="progress" role="progressbar" aria-label="Perguntas respondidas" aria-valuemin="0" aria-valuemax="<?php echo count($perguntas); ?>" aria-valuenow="0">
                            <div class="progress-bar" id="quiz-barra" style="width: 0%"></div>
                        </div>
                    </div>
                    <?php foreach ($perguntas as $index => $p): ?>
                        <fieldset class="info-card mb-4">
                            <div class="card-body p-3 p-md-4">
                                <legend class="quiz-pergunta-titulo">
                                    <span class="q-num"><?php echo $index + 1; ?></span>
                                    <h2><?php echo htmlspecialchars($p['enunciado']); ?></h2>
                                </legend>
                                <div class="quiz-opcoes">
                                    <?php foreach (['a', 'b', 'c', 'd'] as $alt): ?>
                                        <label class="quiz-opcao" for="p<?php echo (int) $p['id_pergunta']; ?>_<?php echo $alt; ?>">
                                            <input type="radio"
                                                   name="respostas[<?php echo (int) $p['id_pergunta']; ?>]"
                                                   id="p<?php echo (int) $p['id_pergunta']; ?>_<?php echo $alt; ?>"
                                                   value="<?php echo $alt; ?>" required>
                                            <span class="letra"><?php echo strtoupper($alt); ?></span>
                                            <span><?php echo htmlspecialchars($p['alternativa_' . $alt]); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-success btn-lg w-100 quiz-enviar">
                        <i class="bi bi-send me-1"></i>Enviar respostas
                    </button>
                </form>
                <script>
                // Atualiza a barra de progresso conforme as perguntas são respondidas
                (function () {
                    var form = document.getElementById('form-quiz');
                    var total = <?php echo count($perguntas); ?>;
                    var barra = document.getElementById('quiz-barra');
                    var texto = document.getElementById('quiz-respondidas');
                    form.addEventListener('change', function () {
                        var nomes = {};
                        form.querySelectorAll('input[type=radio]:checked').forEach(function (r) { nomes[r.name] = true; });
                        var n = Object.keys(nomes).length;
                        texto.textContent = n;
                        barra.style.width = (n / total * 100) + '%';
                        barra.parentNode.setAttribute('aria-valuenow', n);
                    });
                })();
                </script>
            <?php endif; ?>
        </div>
    </div>

    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// ============================================================
// ETAPA 1: Listar quizzes disponíveis
// ============================================================
$sql = "SELECT q.*, COUNT(p.id_pergunta) AS total_perguntas
        FROM quiz q
        LEFT JOIN pergunta p ON q.id_quiz = p.id_quiz
        GROUP BY q.id_quiz
        ORDER BY q.titulo";
$stmt = $pdo->query($sql);
$quizzes = $stmt->fetchAll();

// Melhor resultado do usuário logado em cada quiz
$melhores = [];
if (usuario_logado()) {
    $stmt = $pdo->prepare('SELECT id_quiz, MAX(pontuacao) AS melhor FROM resultado_quiz WHERE id_usuario = :u GROUP BY id_quiz');
    $stmt->bindValue(':u', $_SESSION['id_usuario'], PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll() as $r) $melhores[(int) $r['id_quiz']] = (int) $r['melhor'];
}

/** Ícone e cor da capa conforme o tema do quiz (pelo título). */
function capa_quiz($titulo)
{
    $t = mb_strtolower((string) $titulo, 'UTF-8');
    if (strpos($t, 'classifica') !== false) return ['bi-shield-exclamation', 'quiz-capa-1'];
    if (strpos($t, 'compost') !== false || strpos($t, 'orgân') !== false) return ['bi-flower1', 'quiz-capa-2'];
    if (strpos($t, 'reversa') !== false || strpos($t, 'recicl') !== false) return ['bi-arrow-repeat', 'quiz-capa-3'];
    if (strpos($t, 'dia a dia') !== false || strpos($t, 'descarte') !== false) return ['bi-house-heart', 'quiz-capa-4'];
    return ['bi-patch-question', 'quiz-capa-3'];
}
?>

<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/arvore.jpg" alt="Árvore com a luz do sol entre as folhas">
    <span class="crumb">Aprenda brincando</span>
    <h1 class="mt-2">Quizzes educativos</h1>
    <p class="mt-2">
        Teste seus conhecimentos sobre gestão de resíduos sólidos, classificação,
        descarte correto e sustentabilidade.
    </p>
</section>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
    <p class="text-muted mb-0">
        <i class="bi bi-patch-check me-1"></i>
        <?php echo count($quizzes); ?> <?php echo count($quizzes) === 1 ? 'quiz disponível' : 'quizzes disponíveis'; ?>
        <?php if (!usuario_logado()): ?> · <a href="login.php">entre</a> para salvar sua pontuação<?php endif; ?>
    </p>
    <?php if (usuario_logado()): ?>
        <a href="cadastro_quiz.php" class="btn btn-outline-success btn-sm"><i class="bi bi-plus-circle me-1"></i>Cadastrar quiz e perguntas</a>
    <?php endif; ?>
</div>

<?php if (count($quizzes) === 0): ?>
    <div class="alert alert-info mt-4">Nenhum quiz disponível no momento.</div>
<?php else: ?>
    <div class="row g-4 mt-1 reveal">
        <?php foreach ($quizzes as $quiz):
            [$icone_quiz, $cor_quiz] = capa_quiz($quiz['titulo']);
            $melhor = $melhores[(int) $quiz['id_quiz']] ?? null;
            $tem_perguntas = (int) $quiz['total_perguntas'] > 0; ?>
            <div class="col-sm-6 col-lg-4">
                <article class="card card-residuo quiz-card">
                    <div class="quiz-capa <?php echo $cor_quiz; ?>" aria-hidden="true"><i class="bi <?php echo $icone_quiz; ?>"></i></div>
                    <div class="card-body d-flex flex-column">
                        <h2 class="h5 card-title"><?php echo htmlspecialchars($quiz['titulo']); ?></h2>
                        <p class="card-text text-muted small flex-grow-1">
                            <?php echo htmlspecialchars($quiz['descricao']); ?>
                        </p>
                        <?php if ($melhor !== null): ?>
                            <p class="quiz-meu-resultado mb-2"><i class="bi bi-trophy me-1"></i>Sua melhor pontuação: <?php echo $melhor; ?>%</p>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                            <span class="badge-soft small"><i class="bi bi-list-check me-1"></i><?php echo (int) $quiz['total_perguntas']; ?> <?php echo (int) $quiz['total_perguntas'] === 1 ? 'pergunta' : 'perguntas'; ?></span>
                            <?php if ($tem_perguntas): ?>
                                <a href="quiz.php?id=<?php echo (int) $quiz['id_quiz']; ?>" class="btn btn-sm btn-success">
                                    <?php echo $melhor !== null ? 'Jogar de novo' : 'Começar'; ?> <i class="bi bi-arrow-right"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">Em breve</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
