<?php
/**
 * Busca de resíduos tolerante a erros de digitação.
 *
 * A pessoa não precisa digitar certinho. A busca:
 *  - ignora acentos, maiúsculas e plural ("Lâmpadas" = "lampada");
 *  - entende grafias de mesmo som (ss/ç/s/z, ch/x, lh/li, nh/ni, qu/c/k, letras dobradas):
 *    "jeladeira" -> geladeira, "izopor" -> isopor, "oleo de cosinha" -> óleo de cozinha;
 *  - aceita erros de digitação: letra a mais, a menos, trocada ou fora de lugar
 *    ("garrfa", "pilhaa", "pilja", "plihá" -> pilha);
 *  - junta e separa palavras ("garrafapet" -> garrafa pet, "pen drive" = "pendrive");
 *  - ignora palavras de frase ("onde jogo", "o que fazer com", "como descartar");
 *  - usa sinônimos e nomes populares das palavras-chave ("remédio", "latinha", "durex").
 * Quando corrige o que foi digitado, devolve a busca corrigida ("Mostrando resultados para...").
 *
 * Usa: normalizar_texto() de config/residuos.php.
 */

// Palavras de frase que não ajudam a encontrar o resíduo
const BUSCA_PALAVRAS_VAZIAS = [
    'a', 'o', 'as', 'os', 'e', 'ou', 'de', 'da', 'do', 'das', 'dos', 'em', 'no', 'na', 'nos', 'nas',
    'um', 'uma', 'uns', 'umas', 'com', 'sem', 'para', 'pra', 'pro', 'por', 'pelo', 'pela', 'ao', 'aos', 'que', 'se',
    'onde', 'aonde', 'como', 'qual', 'quais', 'quando', 'porque', 'pq', 'posso', 'devo', 'pode', 'deve', 'tem', 'ter',
    'jogar', 'jogo', 'joga', 'jogo', 'colocar', 'coloco', 'botar', 'boto', 'levar', 'levo', 'deixar', 'deixo',
    'descartar', 'descarto', 'descarte', 'fazer', 'faco', 'faz', 'reciclar', 'reciclo', 'separar', 'separo',
    'entregar', 'entrego', 'fora', 'lixo', 'certo', 'certa', 'correto', 'correta', 'corretamente', 'jeito', 'forma', 'maneira',
    'eu', 'meu', 'minha', 'meus', 'minhas', 'seu', 'sua', 'esse', 'essa', 'este', 'esta', 'isso', 'isto', 'coisa', 'coisas',
    'velho', 'velha', 'velhos', 'velhas', 'antigo', 'antiga', 'quero', 'preciso', 'ajuda', 'favor', 'oi', 'ola',
];

/** Quebra um texto em palavras normalizadas (sem acentos, minúsculas). */
function busca_palavras($texto)
{
    $texto = preg_replace('/[^a-z0-9]+/', ' ', normalizar_texto($texto));
    return array_values(array_filter(explode(' ', $texto), fn($p) => $p !== ''));
}

/** Tira as palavras de frase ("onde", "jogo", "de"...). */
function busca_sem_vazias(array $palavras)
{
    return array_values(array_filter($palavras, fn($p) => !in_array($p, BUSCA_PALAVRAS_VAZIAS, true)));
}

/**
 * "Som" da palavra: reduz grafias diferentes do mesmo som a uma forma só.
 * Ex.: cozinha/cosinha -> kosinia; garrafa/garafa -> garafa; pilha/pilia -> pilia.
 */
function busca_fonetica($palavra)
{
    static $cache = [];
    if (isset($cache[$palavra])) return $cache[$palavra];
    $p = $palavra;
    $p = preg_replace('/(?<=[a-z]{2})(oes|aes)$/', 'ao', $p);       // plural em -ões/-ães
    $p = preg_replace('/(?<=[a-z]{3})(es|s)$/', '', $p);           // plural simples
    $p = strtr($p, ['ph' => 'f', 'sh' => 'x', 'ch' => 'x', 'lh' => 'li', 'nh' => 'ni', 'th' => 't']);
    $p = preg_replace('/qu(?=[ei])/', 'k', $p);
    $p = preg_replace('/gu(?=[ei])/', '#', $p);                        // "gue/gui": som de g
    $p = str_replace('qu', 'k', $p);
    $p = preg_replace('/g(?=[ei])/', 'j', $p);                       // "ge"/"je": geladeira = jeladeira
    $p = str_replace('#', 'g', $p);
    $p = preg_replace('/[sx]c(?=[ei])/', 's', $p);
    $p = preg_replace('/c(?=[ei])/', 's', $p);
    $p = strtr($p, ['c' => 'k', 'q' => 'k', 'z' => 's', 'w' => 'v', 'y' => 'i']);
    $p = str_replace('h', '', $p);
    $p = preg_replace('/(.)\1+/', '$1', $p);                          // letras dobradas
    $p = preg_replace('/m(?=[pb])/', 'n', $p);                        // lampada = lanpada
    $p = preg_replace('/(?<=[a-z]{3})u$/', 'o', $p);                  // amiantu = amianto
    $p = preg_replace('/(?<=[a-z]{3})(?<!r)l(?=[^aeiou]|$)/', 'u', $p); // frauda = fralda (l antes de consoante)
    return $cache[$palavra] = $p;
}

/**
 * Distância entre duas palavras contando letra a mais, a menos, trocada
 * ou duas letras vizinhas fora de lugar (Damerau–Levenshtein restrita).
 */
function busca_distancia($a, $b)
{
    $la = strlen($a); $lb = strlen($b);
    if ($la === 0) return $lb;
    if ($lb === 0) return $la;
    $d = [];
    for ($i = 0; $i <= $la; $i++) $d[$i][0] = $i;
    for ($j = 0; $j <= $lb; $j++) $d[0][$j] = $j;
    for ($i = 1; $i <= $la; $i++) {
        for ($j = 1; $j <= $lb; $j++) {
            $custo = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
            $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $custo);
            if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
            }
        }
    }
    return $d[$la][$lb];
}

/** Quantos erros de digitação aceitamos conforme o tamanho da palavra. */
function busca_tolerancia($tamanho)
{
    if ($tamanho <= 3) return 0;
    if ($tamanho <= 5) return 1;
    if ($tamanho <= 8) return 2;
    return 3;
}

/**
 * Compara uma palavra digitada com uma palavra do índice.
 * Devolve uma nota (0 = não combina; 100 = igual).
 */
function busca_nota_palavra($digitada, $indice)
{
    static $cache = [];
    $chave = $digitada . '|' . $indice;
    if (isset($cache[$chave])) return $cache[$chave];
    return $cache[$chave] = busca_nota_palavra_calc($digitada, $indice);
}

function busca_nota_palavra_calc($digitada, $indice)
{
    if ($digitada === $indice) return 100;
    $fd = busca_fonetica($digitada);
    $fi = busca_fonetica($indice);
    if ($fd === $fi) return 92;                                        // mesmo som / plural
    $ld = strlen($digitada);
    if ($ld >= 3 && strpos($indice, $digitada) === 0) return 80;      // começo da palavra: "pil" -> pilha
    $tol = busca_tolerancia(min(strlen($fd), strlen($fi)));
    // quem digita errado quase nunca erra a 1ª letra (pelo som): evita dinheiro -> banheiro, chuva -> luva
    $ini = fn($c) => strtr($c, 'eu', 'io');                     // esqueiro = isqueiro, oleo = uleo
    $mesmo_inicio = $ini($fd[0]) === $ini($fi[0]) || (strlen($fd) > 1 && strlen($fi) > 1 && $fd[0] === $fi[1] && $fd[1] === $fi[0]);
    if (!$mesmo_inicio) $tol = 0;
    if ($tol > 0 && abs(strlen($fd) - strlen($fi)) <= $tol) {
        $dist = busca_distancia($fd, $fi);
        if ($dist <= $tol) {
            // a distância na grafia original desempata (shampo -> shampoo, não tampa),
            // e palavra do índice mais curta que a digitada perde um pouco (papelo -> papelão, não papel)
            $dist_original = busca_distancia($digitada, $indice);
            if ($dist_original > $tol + 1) return 0;                   // grafia diferente demais (cachorro ≠ caibro)
            $nota = 75 - 10 * $dist - 3 * min(4, $dist_original);
            if (strlen($indice) < $ld) $nota -= 4;
            return max(41, $nota);
        }
    }
    if ($ld >= 5 && strpos($indice, $digitada) !== false) return 55;  // parte da palavra
    // começo da palavra com erro: "garaf" ~ "garrafa"
    if ($mesmo_inicio && $ld >= 5 && strlen($fi) > strlen($fd)) {
        $dist = busca_distancia($fd, substr($fi, 0, strlen($fd)));
        if ($dist <= busca_tolerancia(strlen($fd)) - 1) return 50;
    }
    return 0;
}

/**
 * Índice de busca de um resíduo: palavras do nome e das palavras-chave
 * (peso alto), da descrição (peso baixo) e as expressões inteiras (para desempate).
 */
function busca_indice(array $res)
{
    static $cache = [];
    $id = $res['id_residuo'] ?? md5($res['nome']);
    if (isset($cache[$id])) return $cache[$id];
    $nome = busca_palavras($res['nome']);
    $principais = array_values(array_unique(array_merge($nome, busca_palavras((string) ($res['palavras_chave'] ?? '')))));
    $descricao = array_values(array_diff(array_unique(busca_palavras((string) ($res['descricao'] ?? ''))), $principais));
    $expressoes = [implode(' ', busca_sem_vazias($nome))];
    foreach (explode(',', (string) ($res['palavras_chave'] ?? '')) as $kw) {
        $e = implode(' ', busca_sem_vazias(busca_palavras($kw)));
        if ($e !== '') $expressoes[] = $e;
    }
    // palavras juntas ("pendrive", "garrafapet") também valem como palavra do índice
    foreach ($expressoes as $e) {
        if (strpos($e, ' ') !== false) $principais[] = str_replace(' ', '', $e);
    }
    return $cache[$id] = [
        'id' => (string) $id,
        'principais' => array_values(array_unique($principais)),
        'descricao' => $descricao,
        'nome' => $nome,
        'expressoes' => array_values(array_unique($expressoes)),
        'expressoes_som' => array_map(fn($e) => busca_fonetica_frase(explode(' ', $e)), array_values(array_unique($expressoes))),
    ];
}

/** Melhor combinação de uma palavra digitada com as palavras do índice: [nota, palavra]. */
function busca_melhor_palavra($p, array $indice)
{
    static $cache = [];
    $chave = $p . '|' . $indice['id'];
    return $cache[$chave] ??= busca_melhor_palavra_calc($p, $indice);
}

function busca_melhor_palavra_calc($p, array $indice)
{
    $melhor = 0; $termo = null;
    foreach ($indice['principais'] as $w) {
        $n = busca_nota_palavra($p, $w);
        if ($n > $melhor || ($n === $melhor && $n > 0 && in_array($w, $indice['nome'], true))) { $melhor = $n; $termo = $w; }
        if ($melhor === 100) break;
    }
    // palavras grudadas: "garrafapet" = "garrafa" + "pet"
    if ($melhor < 80 && strlen($p) >= 6) {
        for ($i = 3; $i <= strlen($p) - 2; $i++) {
            $a = substr($p, 0, $i); $b = substr($p, $i);
            [$na, $ta] = busca_melhor_palavra_simples($a, $indice);
            if ($na < 80) continue;
            [$nb, $tb] = busca_melhor_palavra_simples($b, $indice);
            if ($nb >= 80 && min($na, $nb) - 5 > $melhor) { $melhor = min($na, $nb) - 5; $termo = $ta . ' ' . $tb; }
        }
    }
    return [$melhor, $termo];
}

function busca_melhor_palavra_simples($p, array $indice)
{
    $melhor = 0; $termo = null;
    foreach ($indice['principais'] as $w) {
        $n = strlen($p) <= 3 ? ($p === $w ? 100 : 0) : busca_nota_palavra($p, $w);
        if ($n > $melhor) { $melhor = $n; $termo = $w; }
    }
    return [$melhor, $termo];
}

/**
 * Nota do resíduo para a busca (0 = não aparece).
 *
 * Cada palavra digitada tem um peso ($pesos): palavras raras ("ventilador") valem
 * muito, palavras comuns ("quebrado", "lata") valem pouco. O resíduo precisa
 * combinar com a maior parte do peso da busca; assim "ventilador quebrado" acha o
 * Ventilador, e "lata de sardinha" não traz a Lata de alumínio.
 * $correcoes recebe, para cada palavra digitada, a palavra do índice que melhor combinou.
 */
function busca_nota_residuo(array $indice, array $palavras, array $pesos, &$correcoes = [])
{
    $soma = 0; $peso_total = 0; $peso_achado = 0; $forte = false;
    foreach ($palavras as $p) {
        $w = $pesos[$p] ?? 1;
        $peso_total += $w;
        [$melhor, $termo] = busca_melhor_palavra($p, $indice);
        if ($melhor < 60) {
            // Na descrição só valem palavras iguais ou de mesmo som (evita resultados soltos)
            foreach ($indice['descricao'] as $d) {
                if (busca_nota_palavra($p, $d) >= 92) { $melhor = max($melhor, 40); $termo = $termo ?? $d; break; }
            }
        }
        if ($melhor === 0) continue;
        if ($melhor >= 50) $forte = true;
        $correcoes[$p] = [$termo, $melhor];
        $peso_achado += $w;
        $nota = $melhor;
        if ($termo !== null && in_array($termo, $indice['nome'], true)) $nota += $melhor >= 80 ? 15 : 1;   // bônus: está no nome (só desempata se for aproximado)
        $soma += $w * $nota;
    }
    // precisa combinar com a parte importante da busca e ter ao menos uma palavra bem parecida
    if (!$forte || $peso_achado / $peso_total < 0.6) return 0;
    $total = $soma / $peso_total;

    // Desempate pela expressão inteira (comparada pelo som): "sapato" é exatamente um termo
    // do Calçado (e só parte de "caixa de sapato" no Papelão); "impresora" = "Impressora"
    $frase = busca_fonetica_frase($palavras);
    $bonus = 0;
    foreach ($indice['expressoes_som'] as $k => $e) {
        if ($e === $frase) { $bonus = max($bonus, $k === 0 ? 25 : 20); break; }
        if (strpos($e, $frase . ' ') === 0) $bonus = max($bonus, 8);
    }
    // Desempate: o nome do resíduo COMEÇA com a primeira palavra buscada
    // ("cabelo" -> Cabelo e pelos antes de Secador e chapinha de cabelo; "talheres" -> Talheres de metal)
    $primeira_nome = busca_sem_vazias($indice['nome'])[0] ?? null;
    if ($primeira_nome !== null && busca_nota_palavra($palavras[0], $primeira_nome) >= 92) $bonus += 6;

    // Uma palavra só, digitada com erro, que corresponde a um termo inteiro do resíduo:
    // "remdeio" -> "remedio" é um termo do Medicamento vencido (na Caixa de remédio só aparece em "caixa de remedio")
    if (!$bonus && count($palavras) === 1 && isset($correcoes[$palavras[0]])) {
        $termo = $correcoes[$palavras[0]][0];
        if ($termo !== null && in_array($termo, $indice['expressoes'], true)) $bonus = 10;
    }
    return $total + $bonus;
}

/** "Som" do começo de uma palavra (sem as regras de fim de palavra, como o plural). */
function busca_fonetica_prefixo($palavra)
{
    static $cache = [];
    if (isset($cache[$palavra])) return $cache[$palavra];
    $som = rtrim(busca_fonetica($palavra . '9'), '9');   // o "9" impede as regras de fim de palavra
    if ($som !== '') $som = strtr($som[0], 'eu', 'io') . substr($som, 1);   // esqueiro = isqueiro
    return $cache[$palavra] = $som;
}

/** Frase pelo "som": cada palavra passada por busca_fonetica(). */
function busca_fonetica_frase(array $palavras)
{
    return implode(' ', array_map('busca_fonetica', $palavras));
}

/** Peso de cada palavra: quanto menos resíduos ela descreve, mais ela importa. */
function busca_pesos(array $palavras, array $indices)
{
    $n = count($indices);
    $pesos = [];
    foreach ($palavras as $p) {
        $df = 0;
        foreach ($indices as $ix) if (busca_melhor_palavra($p, $ix)[0] >= 80) $df++;
        $pesos[$p] = log(1 + $n / (1 + $df));
    }
    return $pesos;
}

/**
 * Filtra e ordena os resíduos pela busca.
 * Devolve [resíduos encontrados (melhores primeiro), busca corrigida ou null].
 */
function buscar_residuos(array $residuos, $busca)
{
    $palavras = busca_palavras($busca);
    $importantes = array_values(array_filter(busca_sem_vazias($palavras), fn($p) => strlen($p) >= 2 || ctype_digit($p)));
    if (!$importantes) $importantes = array_values(array_filter($palavras, fn($p) => strlen($p) >= 2));
    if (!$importantes) return [[], null];

    $indices = [];
    foreach ($residuos as $i => $res) $indices[$i] = busca_indice($res);

    // Palavra que não combina com NENHUM resíduo é ruído de frase ("me ajuda com o remedio"): ignorada
    if (count($importantes) > 1) {
        $uteis = array_values(array_filter($importantes, function ($p) use ($indices) {
            foreach ($indices as $ix) if (busca_melhor_palavra($p, $ix)[0] >= 50) return true;
            return false;
        }));
        if ($uteis) $importantes = $uteis;
    }

    $pesos = busca_pesos($importantes, $indices);
    $notas = [];
    $todas_correcoes = [];
    foreach ($residuos as $i => $res) {
        $correcoes = [];
        $nota = busca_nota_residuo($indices[$i], $importantes, $pesos, $correcoes);
        if ($nota > 0) {
            $notas[$i] = $nota;
            $todas_correcoes[$i] = $correcoes;
        }
    }
    if (!$notas) return [[], null];
    arsort($notas);

    // Se algum resíduo combinou de verdade (igual, mesmo som ou começo da palavra),
    // os resultados só "parecidos" ficam de fora. Se todos são aproximados
    // (a pessoa digitou errado), mostra só os mais próximos.
    $melhor = reset($notas);
    if ($melhor < 50) return [[], null];   // nada parecido o bastante
    $corte = $melhor >= 80 ? 64 : $melhor - 8;
    $achados = [];
    $votos = [];   // palavra digitada => [palavra do índice => soma das notas]
    foreach ($notas as $i => $nota) {
        if ($nota < $corte) continue;
        $achados[] = $residuos[$i];
        // a correção sugerida vem do resultado mais parecido, e só de palavras que combinaram de verdade
        if (count($achados) === 1) {
            foreach ($todas_correcoes[$i] as $p => [$termo, $nota_palavra]) {
                if ($termo !== null && $nota_palavra > 55) $votos[$p][$termo] = 1;
            }
        }
    }

    // "Mostrando resultados para...": troca cada palavra digitada errada pela mais provável
    $exibicao = busca_grafias($residuos);
    $originais = busca_palavras_originais($busca);
    $corrigida = [];
    $mudou = false;
    foreach ($palavras as $k => $p) {
        if (strlen($p) > 2 && in_array($p, $importantes, true) && isset($votos[$p])) {   // não "corrige" pedaços de 1-2 letras
            arsort($votos[$p]);
            $sugestao = array_key_first($votos[$p]);
            // não "corrige" plural nem palavra incompleta (pilhas -> pilha, pil -> pilha)
            // nem masculino/feminino (preto -> preta)
            $igual = $sugestao === $p || $p === $sugestao . 's' || $p === $sugestao . 'es'
                || strpos($sugestao, $p) === 0
                || (strlen($p) > 3 && substr($p, 0, -1) === substr($sugestao, 0, -1) && strpbrk(substr($p, -1) . substr($sugestao, -1), 'ao') !== false && strlen($p) === strlen($sugestao));
            if (!$igual) {
                $corrigida[] = implode(' ', array_map(fn($w) => $exibicao[$w] ?? $w, explode(' ', $sugestao)));
                $mudou = true;
                continue;
            }
        }
        $corrigida[] = $originais[$k] ?? $p;   // palavra certa: fica como a pessoa digitou
    }
    return [$achados, $mudou ? implode(' ', $corrigida) : null];
}

/** Palavras digitadas, em minúsculas mas COM acentos (mesma divisão de busca_palavras). */
function busca_palavras_originais($texto)
{
    $partes = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim((string) $texto), 'UTF-8'));
    return array_values(array_filter($partes, fn($p) => $p !== ''));
}

/** Grafia com acento das palavras dos nomes dos resíduos: "lampada" => "lâmpada". */
function busca_grafias(array $residuos)
{
    $mapa = [];
    foreach ($residuos as $res) {
        foreach (busca_palavras_originais($res['nome']) as $original) {
            $mapa[normalizar_texto($original)] = $original;
        }
    }
    return $mapa;
}

/**
 * Sugestões para o "autocompletar" enquanto a pessoa digita.
 *
 * A última palavra é tratada como ainda incompleta: "p" já sugere Pilha e Papelão,
 * "garrafa p" sugere Garrafa PET. Depois entram os resultados da busca completa
 * (que tolera erros: "jeladeira" -> geladeira).
 * Devolve [resíduos sugeridos, busca corrigida ou null, total de resultados].
 */
function sugestoes_residuos(array $residuos, $texto, $limite = 6)
{
    $palavras = busca_palavras($texto);
    if (!$palavras) return [[], null, 0];
    $digitando = !preg_match('/\s$/u', (string) $texto);           // última palavra ainda incompleta?
    $ultima = $digitando ? array_pop($palavras) : null;
    $anteriores = busca_sem_vazias($palavras);

    // 1) Começo de palavra: nome do resíduo primeiro, depois as palavras-chave.
    //    Se a pessoa está no meio de uma palavra de frase ("oleo d" -> "de"), ela não filtra nada.
    $anterior = $palavras ? end($palavras) : null;
    $ultima_de_frase = $ultima !== null && $anteriores
        && (in_array($ultima, BUSCA_PALAVRAS_VAZIAS, true)
            || (strlen($ultima) <= 2 && !in_array($anterior, BUSCA_PALAVRAS_VAZIAS, true)
                && (bool) array_filter(BUSCA_PALAVRAS_VAZIAS, fn($v) => strpos($v, $ultima) === 0)));
    $por_prefixo = []; $forte = []; $exato = [];
    if ($ultima !== null && !$ultima_de_frase) {
        $som = strlen($ultima) >= 3 ? busca_fonetica_prefixo($ultima) : null;
        foreach ($residuos as $i => $res) {
            $ix = busca_indice($res);
            foreach ($anteriores as $p) {
                if (busca_melhor_palavra($p, $ix)[0] < 80) continue 2;  // palavras anteriores precisam combinar bem
            }
            $nota = 0;
            foreach ($ix['nome'] as $k => $w) {
                if (in_array($w, BUSCA_PALAVRAS_VAZIAS, true)) continue;
                if (strpos($w, $ultima) === 0) { $nota = $k === 0 ? 120 : 100; break; }
            }
            if (!$nota) {
                foreach ($ix['principais'] as $w) {
                    if (!in_array($w, BUSCA_PALAVRAS_VAZIAS, true) && strpos($w, $ultima) === 0) { $nota = 80; break; }
                }
            }
            if (!$nota && $som !== null) {
                // começo pelo som: "jel" -> geladeira, "isq"/"esq" -> isqueiro
                foreach ($ix['nome'] as $w) {
                    if (strpos(busca_fonetica_prefixo($w), $som) === 0) { $nota = 90; break; }
                }
                if (!$nota) {
                    foreach ($ix['principais'] as $w) {
                        if (strpos(busca_fonetica_prefixo($w), $som) === 0) { $nota = 70; break; }
                    }
                }
            }
            if ($nota) {
                // palavras anteriores que estão no NOME valem mais: "garrafa de v" -> Garrafas e potes de vidro
                foreach ($anteriores as $p) {
                    foreach ($ix['nome'] as $w) { if (busca_nota_palavra($p, $w) >= 92) { $nota += 15; break; } }
                }
                $forte[$i] = $nota >= 90;
                $exato[$i] = $nota >= 100 && $nota !== 105;
                $por_prefixo[$i] = $nota - min(9, mb_strlen($res['nome'], 'UTF-8') / 8);   // nomes curtos primeiro no empate
            }
        }
        arsort($por_prefixo);
    } elseif ($ultima_de_frase) {
        $palavras[] = $ultima;   // a palavra de frase não atrapalha a busca completa
    }

    // 2) Busca completa (tolerante a erros), a partir de 3 letras
    $achados = []; $corrigida = null;
    if (strlen(implode('', busca_palavras($texto))) >= 3) {
        [$achados, $corrigida] = buscar_residuos($residuos, $texto);
    }

    // Junta as duas listas: o que bate com o começo do nome vem antes, depois a busca completa
    $lista = []; $vistos = [];
    foreach (array_keys($por_prefixo) as $i) {
        if ($forte[$i]) { $lista[] = $residuos[$i]; $vistos[$residuos[$i]['nome']] = true; }
    }
    foreach ($achados as $res) {
        if (!isset($vistos[$res['nome']])) { $lista[] = $res; $vistos[$res['nome']] = true; }
    }
    foreach (array_keys($por_prefixo) as $i) {
        if (!isset($vistos[$residuos[$i]['nome']])) { $lista[] = $residuos[$i]; $vistos[$residuos[$i]['nome']] = true; }
    }
    // a correção some só se a pessoa digitou certo o começo da palavra (não quando achamos pelo som)
    if ($por_prefixo && $corrigida !== null && $ultima !== null && !busca_tem_erro($texto, $residuos)) $corrigida = null;   // a pessoa só não terminou de digitar
    return [array_slice($lista, 0, $limite), $corrigida, count($lista)];
}

/**
 * A busca é o nome de um TIPO de material? ("eletronicos", "lixo eletronico", "metal", "plasticos"...)
 * Devolve a chave do tipo (ver TIPOS_RESIDUO) ou null.
 */
function busca_tipo_correspondente($texto)
{
    $sinonimos = [
        'metal' => ['metal', 'metais', 'metalico', 'sucata', 'sucatas'],
        'plastico' => ['plastico', 'plasticos'],
        'papel' => ['papel', 'papeis', 'papel e papelao'],
        'vidro' => ['vidro', 'vidros'],
        'organico' => ['organico', 'organicos', 'lixo organico', 'residuo organico', 'residuos organicos', 'compostagem', 'lixo umido'],
        'eletronico' => ['eletronico', 'eletronicos', 'eletroeletronico', 'eletroeletronicos', 'lixo eletronico', 'e lixo', 'elixo', 'aparelho eletronico', 'aparelhos eletronicos'],
        'especial' => ['descarte especial', 'perigoso', 'perigosos', 'residuo perigoso', 'residuos perigosos', 'quimico', 'quimicos', 'produto quimico', 'produtos quimicos'],
        'construcao' => ['construcao', 'construcao civil', 'obra', 'obras', 'reforma', 'residuo de obra', 'residuos de construcao', 'inertes'],
        'textil' => ['roupa', 'roupas', 'tecido', 'tecidos', 'textil', 'texteis', 'roupas e tecidos'],
        'rejeito' => ['rejeito', 'rejeitos', 'lixo comum', 'nao reciclavel', 'nao reciclaveis'],
    ];
    $alvo = implode(' ', busca_sem_vazias(busca_palavras($texto)));
    if ($alvo === '') return null;
    $som = busca_fonetica_frase(explode(' ', $alvo));
    foreach ($sinonimos as $tipo => $lista) {
        foreach ($lista as $s) {
            if ($s === $alvo || busca_fonetica_frase(explode(' ', $s)) === $som) return $tipo;
        }
    }
    return null;
}

/** Alguma palavra completa digitada não existe em nenhum resíduo (ou seja, tem erro de digitação)? A última pode estar incompleta. */
function busca_tem_erro($texto, array $residuos)
{
    static $vocab = null;
    if ($vocab === null) {
        $vocab = [];
        foreach ($residuos as $res) foreach (busca_indice($res)['principais'] as $w) $vocab[$w] = true;
    }
    $palavras = busca_sem_vazias(busca_palavras($texto));
    $completas = preg_match('/\s$/u', (string) $texto) ? $palavras : array_slice($palavras, 0, -1);
    $ultima = preg_match('/\s$/u', (string) $texto) ? null : end($palavras);
    foreach ($completas as $p) if (!isset($vocab[$p])) return true;
    if ($ultima) {
        foreach (array_keys($vocab) as $w) if (strpos($w, $ultima) === 0) return false;
        return true;
    }
    return false;
}
