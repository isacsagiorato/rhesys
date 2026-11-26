#!/bin/bash
# Roda uma vez, quando o Codespace é criado.
# Instala o que o PHP precisa e monta o banco com os 500 resíduos.
set -e

echo ""
echo "============================================"
echo "   Preparando o Rhesys (so na primeira vez)"
echo "============================================"
echo ""

# --- Extensões do PHP usadas pelo sistema ---
echo "[1/4] Conferindo o PHP..."
if ! php -m | grep -q '^pdo_mysql$'; then
    sudo docker-php-ext-install pdo_mysql > /dev/null 2>&1 || true
fi
php -m | grep -q '^pdo_mysql$' && echo "      pdo_mysql ok" || echo "      !! pdo_mysql faltando"
php -m | grep -q '^mysqli$'    && echo "      mysqli ok"    || echo "      !! mysqli faltando"

# --- Espera o banco aceitar conexão ---
echo "[2/4] Esperando o banco subir..."
for i in $(seq 1 60); do
    if mysqladmin ping -h banco -u root -proot --silent > /dev/null 2>&1; then
        echo "      banco pronto"
        break
    fi
    sleep 2
done

# --- Monta o banco na mesma ordem dos testes ---
echo "[3/4] Criando o banco e cadastrando os resíduos..."
importar() {
    echo "      - $1"
    mysql -h banco -u root -proot --default-character-set=utf8mb4 < "database/$1"
}
importar rhesys_banco_dados.sql
importar residuos_organicos.sql
importar residuos_lote2.sql
importar residuos_lote3.sql
importar residuos_lote4.sql
importar palavras_chave_ampliadas.sql
importar residuos_lote5.sql
importar residuos_lote6.sql
importar quizzes_lote2.sql

# O usuário "rhesys" (o que o site usa) precisa de acesso ao banco
mysql -h banco -u root -proot -e "GRANT ALL PRIVILEGES ON rhesys.* TO 'rhesys'@'%'; FLUSH PRIVILEGES;"

TOTAL=$(mysql -h banco -u root -proot -N -e "SELECT COUNT(*) FROM rhesys.residuo")
echo "      $TOTAL resíduos cadastrados"

# --- Pastas das fotos do chat e dos e-mails ---
echo "[4/4] Preparando as pastas de arquivos..."
mkdir -p storage/chat storage/emails img/uploads
chmod -R 777 storage img/uploads

echo ""
echo "  Tudo pronto!"
echo ""
