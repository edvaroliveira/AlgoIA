<?php

declare(strict_types=1);

return [
  'tema' => 'Variáveis',
  'exercicios' => [
    [
      'titulo' => 'Variáveis — Lista 1: entrada, saída e atribuição',
      'descricao' => 'Leitura de dados, atribuição e exibição de resultados simples.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia o nome de uma pessoa e exiba a mensagem "Olá, <nome>!".',
          'referencia' => 'Conceitos esperados: declarar uma variável do tipo CARACTERE, ler o nome com LEIA e exibir a saudação concatenando o texto fixo com o valor lido.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia dois números inteiros e exiba a soma deles.',
          'referencia' => <<<'TXT'
Algoritmo SomaDoisNumeros
VAR
    a, b, soma: INTEIRO;
INICIO
    LEIA(a);
    LEIA(b);
    soma <- a + b;
    ESCREVA("Soma: ", soma);
FIM.
Conceitos exigidos: declaração de três variáveis inteiras, leitura das duas entradas, atribuição da soma e exibição do resultado.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia dois números inteiros A e B e troque os valores entre eles, exibindo A e B antes e depois da troca. Você deve usar uma variável auxiliar para fazer a troca.',
          'referencia' => <<<'TXT'
Algoritmo TrocaComAuxiliar
VAR
    a, b, aux: INTEIRO;
INICIO
    LEIA(a, b);
    ESCREVA("Antes: A=", a, " B=", b);
    aux <- a;
    a <- b;
    b <- aux;
    ESCREVA("Depois: A=", a, " B=", b);
FIM.
Exigido: a troca precisa passar pela variável auxiliar (aux <- a; a <- b; b <- aux). Trocas por soma e subtração não atendem ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia a idade de uma pessoa em anos e exiba quantos anos ela terá daqui a 10 anos.',
          'referencia' => 'Conceitos esperados: variável inteira para a idade, leitura do valor, cálculo idade + 10 guardado ou exibido diretamente e saída do resultado.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o preço de um produto e a quantidade comprada e exiba o valor total da compra.',
          'referencia' => <<<'TXT'
Algoritmo TotalDaCompra
VAR
    preco, total: REAL;
    quantidade: INTEIRO;
INICIO
    LEIA(preco);
    LEIA(quantidade);
    total <- preco * quantidade;
    ESCREVA("Total: R$ ", total);
FIM.
Conceitos exigidos: preço e total do tipo REAL, quantidade INTEIRO, multiplicação preço × quantidade e exibição do total.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Variáveis — Lista 2: operadores aritméticos',
      'descricao' => 'Expressões com soma, subtração, multiplicação, divisão e precedência.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia três notas de um aluno e exiba a média aritmética.',
          'referencia' => <<<'TXT'
Algoritmo MediaTresNotas
VAR
    n1, n2, n3, media: REAL;
INICIO
    LEIA(n1, n2, n3);
    media <- (n1 + n2 + n3) / 3;
    ESCREVA("Média: ", media);
FIM.
Conceitos exigidos: variáveis REAL, parênteses para somar antes de dividir por 3 e exibição da média.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia um número real e exiba o seu dobro, o seu triplo e a sua metade.',
          'referencia' => 'Conceitos esperados: uma variável REAL para o número lido e três cálculos (x * 2, x * 3 e x / 2) exibidos na saída.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o salário de um funcionário e o percentual de aumento e exiba o novo salário.',
          'referencia' => 'Conceitos esperados: variáveis REAL para salário e percentual, cálculo do aumento (salário * percentual / 100) somado ao salário original e exibição do novo valor.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia três notas e seus pesos e calcule a média ponderada. Você deve usar uma única expressão de atribuição para a média, respeitando a precedência com parênteses.',
          'referencia' => <<<'TXT'
Algoritmo MediaPonderada
VAR
    n1, n2, n3, p1, p2, p3, media: REAL;
INICIO
    LEIA(n1, p1, n2, p2, n3, p3);
    media <- (n1 * p1 + n2 * p2 + n3 * p3) / (p1 + p2 + p3);
    ESCREVA("Média ponderada: ", media);
FIM.
Exigido: a média em uma única atribuição, com a soma dos produtos dividida pela soma dos pesos entre parênteses.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma distância em quilômetros e o tempo de viagem em horas e exiba a velocidade média em km/h.',
          'referencia' => <<<'TXT'
Algoritmo VelocidadeMedia
VAR
    distancia, tempo, velocidade: REAL;
INICIO
    LEIA(distancia);
    LEIA(tempo);
    velocidade <- distancia / tempo;
    ESCREVA("Velocidade média: ", velocidade, " km/h");
FIM.
Conceitos exigidos: variáveis REAL, divisão distância / tempo e exibição com a unidade.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Variáveis — Lista 3: divisão inteira e resto',
      'descricao' => 'Uso de DIV e MOD para decompor valores.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia dois números inteiros e exiba o quociente e o resto da divisão do primeiro pelo segundo.',
          'referencia' => 'Conceitos esperados: variáveis inteiras, quociente com DIV, resto com MOD e exibição dos dois resultados.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro de três dígitos e exiba separadamente o dígito das centenas, das dezenas e das unidades. Você deve usar os operadores DIV e MOD.',
          'referencia' => <<<'TXT'
Algoritmo DecompoeTresDigitos
VAR
    numero, centena, dezena, unidade: INTEIRO;
INICIO
    LEIA(numero);
    centena <- numero DIV 100;
    dezena <- (numero MOD 100) DIV 10;
    unidade <- numero MOD 10;
    ESCREVA(centena, " ", dezena, " ", unidade);
FIM.
Exigido: extrair os dígitos com DIV e MOD. Soluções que convertem o número em texto não atendem ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma quantidade de segundos e a converta em horas, minutos e segundos.',
          'referencia' => <<<'TXT'
Algoritmo ConverteSegundos
VAR
    total, horas, minutos, segundos: INTEIRO;
INICIO
    LEIA(total);
    horas <- total DIV 3600;
    minutos <- (total MOD 3600) DIV 60;
    segundos <- total MOD 60;
    ESCREVA(horas, "h ", minutos, "min ", segundos, "s");
FIM.
Conceitos exigidos: divisão inteira por 3600 para as horas, resto por 3600 dividido por 60 para os minutos e resto por 60 para os segundos.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um valor inteiro em reais e informe a menor quantidade de notas de 100, 50, 20, 10, 5 e 2 e moedas de 1 necessária para compor esse valor.',
          'referencia' => 'Conceitos esperados: para cada valor de nota, calcular a quantidade com DIV e atualizar o restante com MOD, do maior para o menor valor, exibindo cada quantidade.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro de quatro dígitos e exiba o número com os dígitos invertidos (por exemplo, 1234 vira 4321). Obrigatoriamente, a inversão deve ser feita apenas com operações aritméticas (DIV, MOD, multiplicação e soma).',
          'referencia' => <<<'TXT'
Algoritmo InverteQuatroDigitos
VAR
    n, d1, d2, d3, d4, invertido: INTEIRO;
INICIO
    LEIA(n);
    d1 <- n DIV 1000;
    d2 <- (n MOD 1000) DIV 100;
    d3 <- (n MOD 100) DIV 10;
    d4 <- n MOD 10;
    invertido <- d4 * 1000 + d3 * 100 + d2 * 10 + d1;
    ESCREVA(invertido);
FIM.
Exigido: separar os quatro dígitos com DIV e MOD e recompor o número com multiplicações por potências de 10.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Variáveis — Lista 4: conversões de unidades',
      'descricao' => 'Fórmulas de conversão entre unidades de medida.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia uma temperatura em graus Celsius e a converta para Fahrenheit.',
          'referencia' => <<<'TXT'
Algoritmo CelsiusParaFahrenheit
VAR
    celsius, fahrenheit: REAL;
INICIO
    LEIA(celsius);
    fahrenheit <- celsius * 9 / 5 + 32;
    ESCREVA("Fahrenheit: ", fahrenheit);
FIM.
Conceitos exigidos: variáveis REAL e a fórmula F = C × 9 / 5 + 32.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia uma medida em metros e exiba o valor equivalente em centímetros e em milímetros.',
          'referencia' => 'Conceitos esperados: uma variável REAL para os metros, multiplicação por 100 para centímetros e por 1000 para milímetros, com as duas saídas.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um valor em dólares e a cotação do dólar em reais e exiba o valor convertido. Você deve usar uma variável específica para guardar o valor convertido antes de exibi-lo.',
          'referencia' => <<<'TXT'
Algoritmo ConverteDolar
VAR
    dolares, cotacao, reais: REAL;
INICIO
    LEIA(dolares);
    LEIA(cotacao);
    reais <- dolares * cotacao;
    ESCREVA("Valor em reais: ", reais);
FIM.
Exigido: o resultado precisa ser atribuído a uma variável própria (reais) antes do ESCREVA, e não calculado direto na saída.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma velocidade em km/h e a converta para metros por segundo.',
          'referencia' => <<<'TXT'
Algoritmo KmhParaMs
VAR
    kmh, ms: REAL;
INICIO
    LEIA(kmh);
    ms <- kmh / 3.6;
    ESCREVA("Velocidade: ", ms, " m/s");
FIM.
Conceitos exigidos: variáveis REAL e divisão por 3,6 (ou multiplicação por 1000 e divisão por 3600).
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o peso de uma pessoa em quilogramas e exiba o valor em libras, sabendo que 1 kg equivale a 2,2046 libras.',
          'referencia' => 'Conceitos esperados: variável REAL para o peso, multiplicação pelo fator 2,2046 e exibição do resultado em libras.',
        ],
      ],
    ],
    [
      'titulo' => 'Variáveis — Lista 5: geometria',
      'descricao' => 'Áreas, perímetros e volumes com constantes e fórmulas.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia a base e a altura de um retângulo e exiba sua área e seu perímetro.',
          'referencia' => 'Conceitos esperados: variáveis REAL para base e altura, área = base × altura, perímetro = 2 × (base + altura) e exibição dos dois valores.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o raio de um círculo e exiba a sua área, considerando PI = 3,14159.',
          'referencia' => <<<'TXT'
Algoritmo AreaDoCirculo
VAR
    raio, area: REAL;
INICIO
    LEIA(raio);
    area <- 3.14159 * raio * raio;
    ESCREVA("Área: ", area);
FIM.
Conceitos exigidos: variáveis REAL e a fórmula área = PI × raio².
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia os comprimentos dos dois catetos de um triângulo retângulo e exiba o comprimento da hipotenusa.',
          'referencia' => 'Conceitos esperados: variáveis REAL para os catetos, aplicação do teorema de Pitágoras (hipotenusa = RAIZ(a² + b²)) e exibição do resultado.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o raio e a altura de um cilindro e exiba o seu volume. Você deve usar uma constante chamada PI com o valor 3,14159 declarada no algoritmo.',
          'referencia' => <<<'TXT'
Algoritmo VolumeCilindro
CONST
    PI = 3.14159;
VAR
    raio, altura, volume: REAL;
INICIO
    LEIA(raio, altura);
    volume <- PI * raio * raio * altura;
    ESCREVA("Volume: ", volume);
FIM.
Exigido: declarar a constante PI e usá-la na fórmula volume = PI × raio² × altura. Escrever o número direto na fórmula não atende ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia os três lados de um triângulo e exiba sua área pela fórmula de Heron. Você deve usar uma variável para o semiperímetro antes de calcular a área.',
          'referencia' => <<<'TXT'
Algoritmo AreaHeron
VAR
    a, b, c, s, area: REAL;
INICIO
    LEIA(a, b, c);
    s <- (a + b + c) / 2;
    area <- RAIZ(s * (s - a) * (s - b) * (s - c));
    ESCREVA("Área: ", area);
FIM.
Exigido: calcular primeiro o semiperímetro s em uma variável e depois aplicar área = RAIZ(s(s-a)(s-b)(s-c)).
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Variáveis — Lista 6: cálculos financeiros',
      'descricao' => 'Descontos, juros e divisão de despesas.',
      'questoes' => [
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o valor de um produto e aplique um desconto de 15%, exibindo o valor do desconto e o valor final. Você deve usar uma variável para o desconto e outra para o valor final.',
          'referencia' => <<<'TXT'
Algoritmo DescontoQuinze
VAR
    valor, desconto, final: REAL;
INICIO
    LEIA(valor);
    desconto <- valor * 0.15;
    final <- valor - desconto;
    ESCREVA("Desconto: ", desconto, " Valor final: ", final);
FIM.
Exigido: duas variáveis distintas (desconto e final), com o valor final calculado a partir do desconto.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia o valor total de uma conta de restaurante e o número de pessoas e exiba quanto cada pessoa deve pagar.',
          'referencia' => 'Conceitos esperados: variável REAL para o total, variável INTEIRO para o número de pessoas, divisão do total pelo número de pessoas e exibição do valor por pessoa.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um capital, uma taxa de juros mensal (em %) e um número de meses e exiba o montante com juros simples.',
          'referencia' => <<<'TXT'
Algoritmo JurosSimples
VAR
    capital, taxa, montante: REAL;
    meses: INTEIRO;
INICIO
    LEIA(capital, taxa, meses);
    montante <- capital + capital * (taxa / 100) * meses;
    ESCREVA("Montante: ", montante);
FIM.
Conceitos exigidos: fórmula de juros simples M = C + C × i × t, com a taxa convertida de percentual para fração.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o salário bruto de um funcionário e exiba o salário líquido após descontar 11% de previdência e 7,5% de imposto, ambos sobre o salário bruto.',
          'referencia' => 'Conceitos esperados: cálculo dos dois descontos sobre o salário bruto (0,11 e 0,075), subtração do total de descontos e exibição do salário líquido.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia o valor de uma compra, o número de parcelas e a taxa de acréscimo total (em %) para o parcelamento e exiba o valor final e o valor de cada parcela.',
          'referencia' => <<<'TXT'
Algoritmo Parcelamento
VAR
    valor, taxa, total, parcela: REAL;
    parcelas: INTEIRO;
INICIO
    LEIA(valor, parcelas, taxa);
    total <- valor * (1 + taxa / 100);
    parcela <- total / parcelas;
    ESCREVA("Total: ", total, " Parcela: ", parcela);
FIM.
Conceitos exigidos: aplicar o acréscimo sobre o valor, dividir o total pelo número de parcelas e exibir os dois resultados.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Variáveis — Lista 7: tempo e datas',
      'descricao' => 'Conversões e contas com idades, horas e dias.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia o ano de nascimento de uma pessoa e o ano atual e exiba a idade aproximada dela.',
          'referencia' => 'Conceitos esperados: duas variáveis inteiras, subtração ano atual − ano de nascimento e exibição da idade.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia uma quantidade de dias e exiba quantas horas e quantos minutos esse período contém.',
          'referencia' => 'Conceitos esperados: variável inteira para os dias, horas = dias × 24, minutos = dias × 24 × 60 e exibição dos dois valores.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia a idade de uma pessoa em anos, meses e dias e exiba a idade expressa apenas em dias, considerando anos de 365 dias e meses de 30 dias.',
          'referencia' => <<<'TXT'
Algoritmo IdadeEmDias
VAR
    anos, meses, dias, total: INTEIRO;
INICIO
    LEIA(anos, meses, dias);
    total <- anos * 365 + meses * 30 + dias;
    ESCREVA("Idade em dias: ", total);
FIM.
Conceitos exigidos: variáveis inteiras e a soma ponderada anos × 365 + meses × 30 + dias.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia a hora de início e a hora de término de um evento (apenas horas inteiras, no mesmo dia) e exiba a duração em minutos. Você deve usar uma variável para a duração em horas e outra para a duração em minutos.',
          'referencia' => <<<'TXT'
Algoritmo DuracaoEvento
VAR
    inicio, termino, horas, minutos: INTEIRO;
INICIO
    LEIA(inicio, termino);
    horas <- termino - inicio;
    minutos <- horas * 60;
    ESCREVA("Duração: ", minutos, " minutos");
FIM.
Exigido: calcular primeiro a duração em horas (variável horas) e depois convertê-la em minutos (variável minutos).
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia um horário em horas, minutos e segundos e exiba quantos segundos faltam para a meia-noite.',
          'referencia' => <<<'TXT'
Algoritmo FaltaMeiaNoite
VAR
    h, m, s, decorridos, faltam: INTEIRO;
INICIO
    LEIA(h, m, s);
    decorridos <- h * 3600 + m * 60 + s;
    faltam <- 86400 - decorridos;
    ESCREVA("Faltam ", faltam, " segundos");
FIM.
Conceitos exigidos: converter o horário em segundos decorridos e subtrair do total de segundos do dia (86400).
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Variáveis — Lista 8: tipos de dados',
      'descricao' => 'Escolha de tipos (INTEIRO, REAL, CARACTERE, LOGICO) e expressões lógicas simples.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia o nome, a idade e a altura de uma pessoa e exiba os três dados em uma única linha.',
          'referencia' => <<<'TXT'
Algoritmo FichaPessoa
VAR
    nome: CARACTERE;
    idade: INTEIRO;
    altura: REAL;
INICIO
    LEIA(nome, idade, altura);
    ESCREVA(nome, " - ", idade, " anos - ", altura, " m");
FIM.
Conceitos exigidos: tipo CARACTERE para o nome, INTEIRO para a idade e REAL para a altura.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro e guarde em uma variável lógica se ele é par, exibindo o valor dessa variável. Você deve usar uma variável do tipo LOGICO e o operador MOD.',
          'referencia' => <<<'TXT'
Algoritmo GuardaParidade
VAR
    numero: INTEIRO;
    ehPar: LOGICO;
INICIO
    LEIA(numero);
    ehPar <- (numero MOD 2 = 0);
    ESCREVA("É par? ", ehPar);
FIM.
Exigido: atribuir o resultado da expressão relacional a uma variável LOGICO, sem usar estrutura de decisão.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Explique, com um pequeno algoritmo, a diferença entre declarar uma variável como INTEIRO e como REAL ao guardar o resultado da divisão 7 / 2.',
          'referencia' => 'Conceitos esperados: mostrar que em REAL o resultado é 3,5, enquanto em INTEIRO é preciso usar a divisão inteira (7 DIV 2 = 3) ou há perda da parte fracionária; escolha do tipo conforme o valor a armazenar.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia a idade de uma pessoa e guarde em uma variável lógica se ela já pode votar (16 anos ou mais), exibindo o resultado.',
          'referencia' => 'Conceitos esperados: variável INTEIRO para a idade, variável LOGICO recebendo a expressão idade >= 16 e exibição do valor lógico.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia três números inteiros e guarde em uma variável lógica se os três são iguais. Obrigatoriamente, a verificação deve ser uma única expressão com o operador E.',
          'referencia' => <<<'TXT'
Algoritmo TresIguais
VAR
    a, b, c: INTEIRO;
    iguais: LOGICO;
INICIO
    LEIA(a, b, c);
    iguais <- (a = b) E (b = c);
    ESCREVA("Os três são iguais? ", iguais);
FIM.
Exigido: uma única expressão lógica (a = b) E (b = c) atribuída à variável LOGICO.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Variáveis — Lista 9: expressões e precedência',
      'descricao' => 'Montagem de expressões matemáticas a partir de fórmulas.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia o valor de x e calcule y = 3x² − 2x + 5.',
          'referencia' => 'Conceitos esperados: variáveis REAL, expressão 3 * x * x - 2 * x + 5 respeitando a precedência da multiplicação e exibição de y.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia os valores a, b e c de uma equação do segundo grau e exiba o valor do discriminante (delta).',
          'referencia' => <<<'TXT'
Algoritmo Discriminante
VAR
    a, b, c, delta: REAL;
INICIO
    LEIA(a, b, c);
    delta <- b * b - 4 * a * c;
    ESCREVA("Delta: ", delta);
FIM.
Conceitos exigidos: fórmula delta = b² − 4ac em variáveis REAL.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia as coordenadas (x1, y1) e (x2, y2) de dois pontos e exiba a distância entre eles. Você deve usar variáveis intermediárias para as diferenças dx e dy.',
          'referencia' => <<<'TXT'
Algoritmo DistanciaEntrePontos
VAR
    x1, y1, x2, y2, dx, dy, d: REAL;
INICIO
    LEIA(x1, y1, x2, y2);
    dx <- x2 - x1;
    dy <- y2 - y1;
    d <- RAIZ(dx * dx + dy * dy);
    ESCREVA("Distância: ", d);
FIM.
Exigido: calcular dx e dy em variáveis próprias antes da raiz quadrada.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o peso (kg) e a altura (m) de uma pessoa e exiba o Índice de Massa Corporal (IMC).',
          'referencia' => <<<'TXT'
Algoritmo CalculaIMC
VAR
    peso, altura, imc: REAL;
INICIO
    LEIA(peso, altura);
    imc <- peso / (altura * altura);
    ESCREVA("IMC: ", imc);
FIM.
Conceitos exigidos: fórmula IMC = peso / altura², com parênteses no denominador.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia a nota de uma prova (peso 2) e a nota de um trabalho (peso 1) e exiba a média final ponderada.',
          'referencia' => 'Conceitos esperados: variáveis REAL, média = (prova × 2 + trabalho × 1) / 3, com parênteses para que a soma aconteça antes da divisão.',
        ],
      ],
    ],
    [
      'titulo' => 'Variáveis — Lista 10: problemas do cotidiano',
      'descricao' => 'Aplicação de variáveis e fórmulas em situações práticas.',
      'questoes' => [
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia a distância percorrida por um carro (km) e o combustível gasto (litros) e exiba o consumo médio em km/l. Você deve usar nomes de variáveis que descrevam o conteúdo (por exemplo, distancia, litros e consumo).',
          'referencia' => <<<'TXT'
Algoritmo ConsumoMedio
VAR
    distancia, litros, consumo: REAL;
INICIO
    LEIA(distancia);
    LEIA(litros);
    consumo <- distancia / litros;
    ESCREVA("Consumo: ", consumo, " km/l");
FIM.
Exigido: identificadores descritivos (distancia, litros, consumo). Nomes genéricos como a, b, c não atendem ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia a quantidade de horas trabalhadas no mês e o valor da hora e exiba o salário do mês.',
          'referencia' => 'Conceitos esperados: variável para as horas, variável REAL para o valor da hora, multiplicação das duas e exibição do salário.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Uma pintura gasta 1 litro de tinta a cada 3 m². Escreva um algoritmo que leia a largura e a altura de uma parede e exiba quantos litros de tinta serão necessários.',
          'referencia' => <<<'TXT'
Algoritmo TintaParede
VAR
    largura, altura, area, litros: REAL;
INICIO
    LEIA(largura, altura);
    area <- largura * altura;
    litros <- area / 3;
    ESCREVA("Litros necessários: ", litros);
FIM.
Conceitos exigidos: calcular a área da parede e dividi-la pelo rendimento de 3 m² por litro.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia o preço de custo de um produto e a margem de lucro desejada (em %) e exiba o preço de venda e o lucro em reais. Você deve usar uma variável para o lucro e calcular o preço de venda a partir dela.',
          'referencia' => <<<'TXT'
Algoritmo PrecoDeVenda
VAR
    custo, margem, lucro, venda: REAL;
INICIO
    LEIA(custo, margem);
    lucro <- custo * margem / 100;
    venda <- custo + lucro;
    ESCREVA("Preço de venda: ", venda, " Lucro: ", lucro);
FIM.
Exigido: calcular o lucro em uma variável e obter o preço de venda como custo + lucro.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o número de eleitores de um município, a quantidade de votos brancos, nulos e válidos e exiba o percentual de cada tipo em relação ao total de eleitores.',
          'referencia' => 'Conceitos esperados: variáveis para os totais, cálculo de cada percentual como votos / eleitores × 100 e exibição dos três percentuais.',
        ],
      ],
    ],
  ],
];
