<?php
/**
 * Parcial do formulário de cadastro/edição de material para compartilhamento.
 * Espera a variável $valores com as chaves:
 *   nome, descricao, categoria, quantidade, estado_conservacao,
 *   localizacao, condicoes_retirada.
 */
?>
<div class="row g-3">
    <div class="col-md-6">
        <label for="nome" class="form-label">Nome do material *</label>
        <input class="form-control" id="nome" name="nome" maxlength="150" required
               value="<?php echo htmlspecialchars($valores['nome']); ?>"
               placeholder="Ex.: Cadeira de escritório, caixas de papelão...">
    </div>
    <div class="col-md-6">
        <label for="categoria" class="form-label">Categoria *</label>
        <select class="form-select" id="categoria" name="categoria" required>
            <option value="">Selecione...</option>
            <?php foreach (CATEGORIAS_MATERIAIS as $valor => $rotulo): ?>
                <option value="<?php echo htmlspecialchars($valor); ?>" <?php echo $valores['categoria'] === $valor ? 'selected' : ''; ?>><?php echo htmlspecialchars($rotulo); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label for="quantidade" class="form-label">Quantidade *</label>
        <input class="form-control" id="quantidade" name="quantidade" maxlength="100" required
               value="<?php echo htmlspecialchars($valores['quantidade']); ?>"
               placeholder="Ex.: 2 unidades, 1 saco, 3 caixas">
    </div>
    <div class="col-md-6">
        <label for="estado_conservacao" class="form-label">Estado de conservação *</label>
        <select class="form-select" id="estado_conservacao" name="estado_conservacao" required>
            <?php foreach (ESTADOS_CONSERVACAO as $valor => $rotulo): ?>
                <option value="<?php echo htmlspecialchars($valor); ?>" <?php echo $valores['estado_conservacao'] === $valor ? 'selected' : ''; ?>><?php echo htmlspecialchars($rotulo); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12">
        <label for="localizacao" class="form-label">Local de retirada *</label>
        <input class="form-control" id="localizacao" name="localizacao" maxlength="200" required
               value="<?php echo htmlspecialchars($valores['localizacao']); ?>"
               placeholder="Ex.: Centro, São João da Boa Vista - SP">
    </div>
    <div class="col-12">
        <label for="descricao" class="form-label">Descrição do material *</label>
        <textarea class="form-control" id="descricao" name="descricao" rows="5" required minlength="10" maxlength="2000"
                  placeholder="Descreva o material: dimensões, cor, marca, estado e o que mais for útil para quem vai reaproveitá-lo."><?php echo htmlspecialchars($valores['descricao']); ?></textarea>
        <div class="form-text">Não publique senhas, documentos ou outros dados sensíveis.</div>
    </div>
    <div class="col-12">
        <label for="condicoes_retirada" class="form-label">Condições e orientações para retirada (opcional)</label>
        <textarea class="form-control" id="condicoes_retirada" name="condicoes_retirada" rows="3" maxlength="1000"
                  placeholder="Ex.: retirar no local em horário comercial; levar veículo para transporte."><?php echo htmlspecialchars($valores['condicoes_retirada']); ?></textarea>
    </div>
    <div class="col-12">
        <label for="imagem" class="form-label">Imagem do material (opcional)</label>
        <input class="form-control" type="file" id="imagem" name="imagem" accept="image/jpeg,image/png,image/webp,image/gif">
        <div class="form-text">JPG, PNG, WEBP ou GIF, até 5 MB.</div>
    </div>
</div>