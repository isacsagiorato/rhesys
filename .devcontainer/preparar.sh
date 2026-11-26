#!/bin/bash
# Roda uma vez, quando o Codespace é criado.
# Instala o que o PHP precisa e monta o banco com os 500 resíduos.

echo ""
echo "============================================"
echo "   Preparando o Rhesys (so na primeira vez)"
echo "============================================"
echo ""

echo "[1/3] Conferindo o PHP..."

# Instala as extensões do PHP que faltarem (cada imagem vem com um conjunto)
faltando=""
php -m | grep -qx "pdo_mysql" || faltando="$faltando pdo_mysql"
php -m | grep -qx "mysqli"    || faltando="$faltando mysqli"

if [ -n "$faltando" ]; then
    echo "      instalando:$faltando"
    if command -v docker-php-ext-install > /dev/null 2>&1; then
        sudo docker-php-ext-install $faltando > /dev/null 2>&1
    fi
    # Se ainda faltar, tenta pelo gerenciador de pacotes
    if ! php -m | grep -qx "mysqli"; then
        sudo apt-get update -qq > /dev/null 2>&1
        sudo apt-get install -y -qq php-mysql > /dev/null 2>&1
    fi
fi

for ext in pdo_mysql mysqli; do
    if php -m | grep -qx "$ext"; then
        echo "      $ext ok"
    else
        echo "      !! $ext nao instalou - o site nao vai conectar no banco"
    fi
done

echo "[2/3] Criando o banco e cadastrando os residuos..."
php .devcontainer/montar-banco.php
if [ $? -ne 0 ]; then
    echo ""
    echo "  !! Nao consegui montar o banco (veja o erro acima)."
    echo "     Depois que o Codespace abrir, rode no terminal:"
    echo "       php .devcontainer/montar-banco.php"
    echo ""
    exit 1
fi

echo "[3/3] Preparando as pastas de arquivos..."
mkdir -p storage/chat storage/emails img/uploads
chmod -R 777 storage img/uploads 2>/dev/null
echo "      ok"

echo ""
echo "  Tudo pronto!"
echo ""
