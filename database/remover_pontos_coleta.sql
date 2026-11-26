-- Remove as tabelas dos pontos de coleta (a função foi retirada do site).
-- Para bancos criados em versões anteriores. Pode rodar mais de uma vez.

USE rhesys;

DROP TABLE IF EXISTS residuo_ponto_coleta;
DROP TABLE IF EXISTS ponto_coleta;
