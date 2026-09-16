<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';

$titulo_pagina = 'Quizzes';
require __DIR__ . '/../includes/header.php';

// Parâmetros
$id_quiz = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$respostas = isset($_POST['respostas']) ? $_POST['respostas'] : [];

// ============================================================
// ETAPA 3: Resultado do quiz (formulário enviado)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_quiz > 0 && !empty($respostas)) {
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
        $resposta_usuario = isset($respostas[$id_pergunta]) ? $respostas[$id_pergunta] : null;
        if ($resposta_usuario === $p['resposta_correta']) {
            $acertos++;
        }
    }

    $pontuacao = $total > 0 ? round(($acertos / $total) * 100) : 0;

    // Salvar resultado se o usuário estiver logado
    if (isset($_SESSION['id_usuario'])) {
        $sql_resultado = "INSERT INTO resultado_quiz (id_usuario, id_quiz, pontuacao)
                          VALUES (:id_usuario, :id_quiz, :pontuacao)";
        $stmt_resultado = $pdo->prepare($sql_resultado);
        $stmt_resultado->bindValue(':id_usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
        $stmt_resultado->bindValue(':id_quiz', $id_quiz, PDO::PARAM_INT);
        $stmt_resultado->bindValue(':pontuacao', $pontuacao, PDO::PARAM_INT);
        $stmt_resultado->execute();
    }
    ?>

    <a href="quiz.php" class="btn btn-outline-success mb-3">&larr; Voltar aos quizzes</a>

    <div class="card shadow-sm mb-4">
        <div class="card-body text-center p-5">
            <h1 class="display-4 fw-bold text-success"><?php echo $pontuacao; ?>%</h1>
            <p class="fs-4">
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
            <a href="quiz.php?id=<?php echo $id_quiz; ?>" class="btn btn-success">Tentar novamente</a>
        </div>
    </div>

    <!-- Revisão das respostas -->
    <h3>Revisão das respostas</h3>
    <div class="list-group mb-4">
        <?php foreach ($perguntas as $index => $p): 
            $id_pergunta = $p['id_pergunta'];
            $resposta_usuario = isset($respostas[$id_pergunta]) ? $respostas[$id_pergunta] : null;
            $correta = $resposta_usuario === $p['resposta_correta'];
        ?>
            <div class="list-group-item <?php echo $correta ? 'list-group-item-success' : 'list-group-item-danger'; ?>">
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
                    <?php echo $correta ? '✅' : '❌'; ?>
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

    <a href="quiz.php?id=<?php echo $id_quiz; ?>" class="btn btn-success">Refazer quiz</a>
    <a href="quiz.php" class="btn btn-outline-success">Outros quizzes</a>

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

    <a href="quiz.php" class="btn btn-outline-success mb-3">&larr; Voltar aos quizzes</a>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="card-title text-success"><?php echo htmlspecialchars($quiz['titulo']); ?></h1>
            <p class="text-muted"><?php echo htmlspecialchars($quiz['descricao']); ?></p>
            <p class="badge bg-light text-dark mb-4"><?php echo count($perguntas); ?> perguntas</p>

            <?php if (count($perguntas) === 0): ?>
                <div class="alert alert-info">Este quiz ainda não possui perguntas cadastradas.</div>
            <?php else: ?>
                <form method="POST" action="quiz.php?id=<?php echo $id_quiz; ?>">
                    <?php foreach ($perguntas as $index => $p): ?>
                        <div class="card mb-4 border-light">
                            <div class="card-body">
                                <h5 class="mb-3">
                                    <span class="badge bg-success me-2"><?php echo $index + 1; ?></span>
                                    <?php echo htmlspecialchars($p['enunciado']); ?>
                                </h5>
                                <div class="ms-4">
                                    <?php foreach (['a', 'b', 'c', 'd'] as $alt): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio"
                                                   name="respostas[<?php echo $p['id_pergunta']; ?>]"
                                                   id="p<?php echo $p['id_pergunta']; ?>_<?php echo $alt; ?>"
                                                   value="<?php echo $alt; ?>" required>
                                            <label class="form-check-label" for="p<?php echo $p['id_pergunta']; ?>_<?php echo $alt; ?>">
                                                <strong><?php echo strtoupper($alt); })</strong>
                                                <?php echo htmlspecialchars($p['alternativa_' . $alt]); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-success btn-lg w-100">
                        Enviar respostas
                    </button>
                </form>
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
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Quizzes Educativos</h1>
    <span class="badge bg-success fs-6"><?php echo count($quizzes); ?> quiz(zes)</span>
</div>

<p class="text-muted mb-4">
    Teste seus conhecimentos sobre gestão de resíduos sólidos, classificação,
    descarte correto e sustentabilidade.
</p>

<?php if (count($quizzes) === 0): ?>
    <div class="alert alert-info">Nenhum quiz disponível no momento.</div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($quizzes as $quiz): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card card-residuo shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title text-success"><?php echo htmlspecialchars($quiz['titulo']); ?></h5>
                        <p class="card-text text-muted small flex-grow-1">
                            <?php echo htmlspecialchars($quiz['descricao']); ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="badge bg-light text-dark">
                                <?php echo $quiz['total_perguntas']; ?> pergunta(s)
                            </span>
                            <?php if ($quiz['total_perguntas'] > 0): ?>
                                <a href="quiz.php?id=<?php echo $quiz['id_quiz']; ?>" class="btn btn-sm btn-success">
                                    Iniciar &rarr;
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">Em breve</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>