<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
exige_login();

$id_quiz = (int) (($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST['id_quiz'] ?? 0 : $_GET['id_quiz'] ?? 0));
$stmt = $pdo->prepare('SELECT * FROM quiz WHERE id_quiz = :id');
$stmt->bindValue(':id', $id_quiz, PDO::PARAM_INT);
$stmt->execute();
$quiz = $stmt->fetch();
$titulo_pagina = 'Cadastrar perguntas';
$erros = [];
$enunciado = '';
$alternativas = ['a' => '', 'b' => '', 'c' => '', 'd' => ''];
$resposta_correta = 'a';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enunciado = trim((string) ($_POST['enunciado'] ?? ''));
    foreach (array_keys($alternativas) as $letra) $alternativas[$letra] = trim((string) ($_POST['alternativa_' . $letra] ?? ''));
    $resposta_correta = strtolower((string) ($_POST['resposta_correta'] ?? ''));
    if (!verificar_csrf()) $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    if (!$quiz) $erros[] = 'Selecione um quiz válido antes de cadastrar perguntas.';
    if (mb_strlen($enunciado) < 5) $erros[] = 'O enunciado deve ter pelo menos 5 caracteres.';
    foreach ($alternativas as $letra => $alternativa) {
        if ($alternativa === '' || mb_strlen($alternativa) > 255) $erros[] = 'A alternativa ' . strtoupper($letra) . ' deve ter entre 1 e 255 caracteres.';
    }
    if (!in_array($resposta_correta, ['a', 'b', 'c', 'd'], true)) $erros[] = 'Selecione a resposta correta.';
    if (count(array_unique($alternativas)) !== 4) $erros[] = 'As quatro alternativas devem ser diferentes.';
    if (!$erros) {
        $stmt = $pdo->prepare('INSERT INTO pergunta (id_quiz, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta) VALUES (:quiz, :enunciado, :a, :b, :c, :d, :correta)');
        $stmt->bindValue(':quiz', $id_quiz, PDO::PARAM_INT);
        $stmt->bindValue(':enunciado', $enunciado);
        foreach (['a', 'b', 'c', 'd'] as $letra) $stmt->bindValue(':' . $letra, $alternativas[$letra]);
        $stmt->bindValue(':correta', $resposta_correta);
        $stmt->execute();
        header('Location: cadastro_pergunta.php?id_quiz=' . $id_quiz . '&sucesso=pergunta#perguntas');
        exit;
    }
}

$perguntas = [];
if ($quiz) {
    $stmt = $pdo->prepare('SELECT * FROM pergunta WHERE id_quiz = :id ORDER BY id_pergunta');
    $stmt->bindValue(':id', $id_quiz, PDO::PARAM_INT);
    $stmt->execute();
    $perguntas = $stmt->fetchAll();
}
require __DIR__ . '/../includes/header.php';
?>
<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/arvore.jpg" alt="Árvore em meio à natureza">
    <span class="crumb">Banco de perguntas</span>
    <h1 class="mt-2">Cadastrar perguntas</h1>
    <p class="mt-2"><?php echo $quiz ? 'Quiz: ' . htmlspecialchars($quiz['titulo']) : 'Quiz não encontrado.'; ?></p>
</section>
<?php if (($_GET['sucesso'] ?? '') === 'quiz'): ?><div class="alert alert-success mt-4">Quiz criado. Agora cadastre a primeira pergunta.</div><?php endif; ?>
<?php if (($_GET['sucesso'] ?? '') === 'pergunta'): ?><div class="alert alert-success mt-4">Pergunta cadastrada com sucesso.</div><?php endif; ?>
<?php if (!$quiz): ?>
    <div class="alert alert-warning mt-4">O quiz informado não existe.</div><a href="cadastro_quiz.php" class="btn btn-success mt-3">Voltar aos quizzes</a>
<?php else: ?>
<div class="row justify-content-center mt-5 reveal"><div class="col-lg-9">
    <div class="card shadow-sm"><div class="card-body p-4 p-lg-5">
        <?php if ($erros): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?php echo htmlspecialchars($erro); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="cadastro_pergunta.php?id_quiz=<?php echo $id_quiz; ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id_quiz" value="<?php echo $id_quiz; ?>">
            <div class="mb-3"><label for="enunciado" class="form-label">Enunciado</label><textarea class="form-control" id="enunciado" name="enunciado" rows="3" required minlength="5"><?php echo htmlspecialchars($enunciado); ?></textarea></div>
            <div class="row g-3">
                <?php foreach (['a', 'b', 'c', 'd'] as $letra): ?>
                    <div class="col-md-6"><label for="alternativa_<?php echo $letra; ?>" class="form-label">Alternativa <?php echo strtoupper($letra); ?></label><input class="form-control" id="alternativa_<?php echo $letra; ?>" name="alternativa_<?php echo $letra; ?>" value="<?php echo htmlspecialchars($alternativas[$letra]); ?>" required maxlength="255"></div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 mb-4"><label for="resposta_correta" class="form-label">Resposta correta</label><select class="form-select" id="resposta_correta" name="resposta_correta" required><?php foreach (['a', 'b', 'c', 'd'] as $letra): ?><option value="<?php echo $letra; ?>" <?php echo $resposta_correta === $letra ? 'selected' : ''; ?>>Alternativa <?php echo strtoupper($letra); ?></option><?php endforeach; ?></select></div>
            <button type="submit" class="btn btn-success">Cadastrar pergunta</button>
            <a href="quiz.php?id=<?php echo $id_quiz; ?>" class="btn btn-outline-success">Visualizar quiz</a>
        </form>
    </div></div>
</div></div>
<section class="mt-5" id="perguntas">
    <h2 class="h4">Perguntas cadastradas (<?php echo count($perguntas); ?>)</h2>
    <?php if (!$perguntas): ?><div class="alert alert-info">Este quiz ainda não tem perguntas.</div><?php endif; ?>
    <?php foreach ($perguntas as $indice => $pergunta): ?>
        <article class="info-card mb-3"><div class="card-body p-4">
            <h3 class="h6"><?php echo $indice + 1; ?>. <?php echo htmlspecialchars($pergunta['enunciado']); ?></h3>
            <ol type="A" class="small mb-2"><?php foreach (['a', 'b', 'c', 'd'] as $letra): ?><li><?php echo htmlspecialchars($pergunta['alternativa_' . $letra]); ?><?php if ($pergunta['resposta_correta'] === $letra): ?> <span class="badge-soft">correta</span><?php endif; ?></li><?php endforeach; ?></ol>
        </div></article>
    <?php endforeach; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
