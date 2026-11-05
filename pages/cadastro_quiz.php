<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
exige_login();

$titulo_pagina = 'Cadastrar quiz';
$erros = [];
$titulo = $descricao = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $descricao = trim((string) ($_POST['descricao'] ?? ''));
    if (!verificar_csrf()) $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 150) $erros[] = 'O título deve ter entre 3 e 150 caracteres.';
    if (mb_strlen($descricao) < 10 || mb_strlen($descricao) > 2000) $erros[] = 'A descrição deve ter entre 10 e 2000 caracteres.';
    if (!$erros) {
        $stmt = $pdo->prepare('INSERT INTO quiz (titulo, descricao) VALUES (:titulo, :descricao)');
        $stmt->bindValue(':titulo', $titulo);
        $stmt->bindValue(':descricao', $descricao);
        $stmt->execute();
        header('Location: cadastro_pergunta.php?id_quiz=' . (int) $pdo->lastInsertId() . '&sucesso=quiz');
        exit;
    }
}
$quizzes = $pdo->query('SELECT q.*, COUNT(p.id_pergunta) AS total_perguntas FROM quiz q LEFT JOIN pergunta p ON p.id_quiz = q.id_quiz GROUP BY q.id_quiz ORDER BY q.titulo')->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/reciclaveis.jpg" alt="Materiais recicláveis">
    <span class="crumb">Educação e gamificação</span>
    <h1 class="mt-2">Cadastrar quiz</h1>
    <p class="mt-2">Crie o questionário e depois adicione suas perguntas.</p>
</section>
<div class="row justify-content-center mt-5 reveal"><div class="col-lg-7">
    <div class="card shadow-sm"><div class="card-body p-4 p-lg-5">
        <?php if ($erros): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?php echo htmlspecialchars($erro); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="cadastro_quiz.php">
            <?php echo csrf_field(); ?>
            <div class="mb-3"><label for="titulo" class="form-label">Título</label><input class="form-control" id="titulo" name="titulo" value="<?php echo htmlspecialchars($titulo); ?>" required minlength="3" maxlength="150"></div>
            <div class="mb-4"><label for="descricao" class="form-label">Descrição</label><textarea class="form-control" id="descricao" name="descricao" rows="5" required minlength="10" maxlength="2000"><?php echo htmlspecialchars($descricao); ?></textarea></div>
            <button type="submit" class="btn btn-success">Criar quiz e adicionar perguntas</button>
            <a href="quiz.php" class="btn btn-outline-success">Voltar</a>
        </form>
    </div></div>
</div></div>
<section class="mt-5 reveal">
    <h2 class="h4">Quizzes cadastrados</h2>
    <div class="list-group">
        <?php foreach ($quizzes as $quiz): ?>
            <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div><strong><?php echo htmlspecialchars($quiz['titulo']); ?></strong><p class="small text-muted mb-0"><?php echo (int) $quiz['total_perguntas']; ?> pergunta(s)</p></div>
                <div class="d-flex gap-2"><a href="cadastro_pergunta.php?id_quiz=<?php echo (int) $quiz['id_quiz']; ?>" class="btn btn-sm btn-outline-success">Gerenciar perguntas</a><?php if ($quiz['total_perguntas'] > 0): ?><a href="quiz.php?id=<?php echo (int) $quiz['id_quiz']; ?>" class="btn btn-sm btn-success">Abrir quiz</a><?php endif; ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
