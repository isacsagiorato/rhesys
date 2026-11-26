#!/bin/bash
# Roda toda vez que o Codespace abre: liga o site na porta 8000.

# Se já tiver um servidor rodando, encerra antes
pkill -f "php -S 0.0.0.0:8000" > /dev/null 2>&1 || true
sleep 1

nohup php -S 0.0.0.0:8000 -t . > /tmp/rhesys-servidor.log 2>&1 &
sleep 2

echo ""
echo "================================================"
echo ""
echo "   RHESYS NO AR"
echo ""
echo "   O site abre sozinho em uma aba nova."
echo ""
echo "   Se nao abrir, clique na aba PORTAS (PORTS)"
echo "   aqui embaixo e depois no desenho de globo"
echo "   da linha 8000."
echo ""
echo "================================================"
echo ""
