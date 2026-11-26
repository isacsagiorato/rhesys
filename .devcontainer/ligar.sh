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
echo "   Clique na aba PORTAS (PORTS) aqui embaixo"
echo "   e depois no desenho de globo da linha 8000."
echo ""
echo "   Se der erro 401, clique com o botao direito"
echo "   na linha 8000, escolha Port Visibility e"
echo "   depois Public."
echo ""
echo "================================================"
echo ""
