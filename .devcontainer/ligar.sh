#!/bin/bash
# Roda toda vez que o Codespace abre: liga o site na porta 8000.

# Se já tiver um servidor rodando, encerra antes
pkill -f "php -S 0.0.0.0:8000" > /dev/null 2>&1 || true
sleep 1

nohup php -S 0.0.0.0:8000 -t . > /tmp/rhesys-servidor.log 2>&1 &
sleep 2

echo ""
echo "============================================"
echo "   RHESYS NO AR"
echo ""
echo "   Abra a aba PORTAS (ou PORTS) aqui embaixo"
echo "   e clique no cadeado da porta 8000 para"
echo "   abrir o site no navegador."
echo "============================================"
echo ""
