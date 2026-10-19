<?php

declare(strict_types=1);

return [
  'tema' => 'Estruturas de Decisão',
  'exercicios' => [
    [
      'titulo' => 'Decisão — Lista 1: SE simples e composto',
      'descricao' => 'Primeiros usos de SE, ENTAO, SENAO e FIMSE.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro e informe se ele é positivo, negativo ou zero.',
          'referencia' => 'Conceitos esperados: leitura do número, comparações com zero (maior, menor e igual) em estrutura SE e uma mensagem para cada um dos três casos.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro e informe se ele é par ou ímpar.',
          'referencia' => <<<'TXT'
Algoritmo ParOuImpar
VAR
    n: INTEIRO;
INICIO
    LEIA(n);
    SE n MOD 2 = 0 ENTAO
        ESCREVA("Par");
    SENAO
        ESCREVA("Ímpar");
    FIMSE
FIM.
Conceitos exigidos: teste do resto da divisão por 2 com MOD e estrutura SE/SENAO com as duas mensagens.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia a nota final de um aluno e informe "Aprovado" se a nota for maior ou igual a 6 e "Reprovado" caso contrário. Você deve usar uma única estrutura SE...SENAO.',
          'referencia' => <<<'TXT'
Algoritmo AprovadoReprovado
VAR
    nota: REAL;
INICIO
    LEIA(nota);
    SE nota >= 6 ENTAO
        ESCREVA("Aprovado");
    SENAO
        ESCREVA("Reprovado");
    FIMSE
FIM.
Exigido: um único SE...SENAO com a condição nota >= 6. Dois SEs independentes não atendem ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia a idade de uma pessoa e informe se ela é maior ou menor de idade (maioridade aos 18 anos).',
          'referencia' => 'Conceitos esperados: comparação idade >= 18 em uma estrutura de decisão e exibição da mensagem correspondente a cada caso.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia dois números diferentes e exiba o maior deles.',
          'referencia' => <<<'TXT'
Algoritmo MaiorDeDois
VAR
    a, b: INTEIRO;
INICIO
    LEIA(a, b);
    SE a > b ENTAO
        ESCREVA("Maior: ", a);
    SENAO
        ESCREVA("Maior: ", b);
    FIMSE
FIM.
Conceitos exigidos: comparação a > b e exibição do maior valor em cada ramo.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Decisão — Lista 2: comparações entre valores',
      'descricao' => 'Maior, menor, ordenação e igualdade entre vários valores.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia três números inteiros e exiba o maior deles.',
          'referencia' => <<<'TXT'
Algoritmo MaiorDeTres
VAR
    a, b, c, maior: INTEIRO;
INICIO
    LEIA(a, b, c);
    maior <- a;
    SE b > maior ENTAO
        maior <- b;
    FIMSE
    SE c > maior ENTAO
        maior <- c;
    FIMSE
    ESCREVA("Maior: ", maior);
FIM.
Conceitos exigidos: guardar um candidato a maior e compará-lo com os demais valores, atualizando-o quando necessário.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia dois números e informe se eles são iguais ou diferentes.',
          'referencia' => 'Conceitos esperados: comparação de igualdade entre os dois valores e uma mensagem para cada resultado.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia três números inteiros distintos e exiba o menor e o maior deles.',
          'referencia' => 'Conceitos esperados: comparações entre os três valores para descobrir o menor e o maior, com exibição dos dois resultados.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia três números inteiros e os exiba em ordem crescente. Você deve usar trocas entre as variáveis com SE, sem nenhuma estrutura de repetição.',
          'referencia' => <<<'TXT'
Algoritmo OrdenaTres
VAR
    a, b, c, aux: INTEIRO;
INICIO
    LEIA(a, b, c);
    SE a > b ENTAO
        aux <- a; a <- b; b <- aux;
    FIMSE
    SE b > c ENTAO
        aux <- b; b <- c; c <- aux;
    FIMSE
    SE a > b ENTAO
        aux <- a; a <- b; b <- aux;
    FIMSE
    ESCREVA(a, " ", b, " ", c);
FIM.
Exigido: três comparações com troca por variável auxiliar, sem laços. O terceiro SE é necessário para garantir a ordem.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o ano de nascimento de duas pessoas e informe qual delas é mais velha ou se têm a mesma idade.',
          'referencia' => <<<'TXT'
Algoritmo MaisVelha
VAR
    ano1, ano2: INTEIRO;
INICIO
    LEIA(ano1, ano2);
    SE ano1 < ano2 ENTAO
        ESCREVA("A primeira pessoa é mais velha");
    SENAO
        SE ano2 < ano1 ENTAO
            ESCREVA("A segunda pessoa é mais velha");
        SENAO
            ESCREVA("Têm a mesma idade");
        FIMSE
    FIMSE
FIM.
Conceitos exigidos: menor ano de nascimento indica a pessoa mais velha, e o caso de igualdade precisa ser tratado.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Decisão — Lista 3: ESCOLHA-CASO',
      'descricao' => 'Seleção múltipla com ESCOLHA, CASO e OUTROCASO.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia um número de 1 a 7 e exiba o nome do dia da semana correspondente (1 = domingo), ou "Dia inválido" para outros valores.',
          'referencia' => 'Conceitos esperados: uma estrutura de seleção (ESCOLHA-CASO ou SEs encadeados) com os sete casos e o tratamento de valor inválido.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia dois números e um operador (+, -, * ou /) e exiba o resultado da operação. Você deve usar a estrutura ESCOLHA-CASO e tratar a divisão por zero.',
          'referencia' => <<<'TXT'
Algoritmo Calculadora
VAR
    a, b: REAL;
    op: CARACTERE;
INICIO
    LEIA(a, op, b);
    ESCOLHA op
        CASO "+": ESCREVA(a + b);
        CASO "-": ESCREVA(a - b);
        CASO "*": ESCREVA(a * b);
        CASO "/":
            SE b = 0 ENTAO
                ESCREVA("Divisão por zero");
            SENAO
                ESCREVA(a / b);
            FIMSE
        OUTROCASO: ESCREVA("Operador inválido");
    FIMESCOLHA
FIM.
Exigido: ESCOLHA-CASO sobre o operador, OUTROCASO para operador inválido e SE para evitar divisão por zero.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o número de um mês (1 a 12) e exiba quantos dias ele tem, considerando fevereiro com 28 dias.',
          'referencia' => <<<'TXT'
Algoritmo DiasDoMes
VAR
    mes: INTEIRO;
INICIO
    LEIA(mes);
    ESCOLHA mes
        CASO 2: ESCREVA("28 dias");
        CASO 4, 6, 9, 11: ESCREVA("30 dias");
        CASO 1, 3, 5, 7, 8, 10, 12: ESCREVA("31 dias");
        OUTROCASO: ESCREVA("Mês inválido");
    FIMESCOLHA
FIM.
Conceitos exigidos: agrupar os meses de 30 e de 31 dias, tratar fevereiro e meses inválidos.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o código de um produto (1, 2, 3 ou 4) e a quantidade comprada e exiba o valor a pagar, sabendo que os preços são R$ 5,00, R$ 3,50, R$ 8,00 e R$ 12,00, respectivamente.',
          'referencia' => 'Conceitos esperados: seleção do preço conforme o código, multiplicação pela quantidade, exibição do total e mensagem para código inexistente.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia a letra do conceito de um aluno (A, B, C, D ou E) e exiba a faixa de nota correspondente (A: 9 a 10, B: 7 a 8,9, C: 5 a 6,9, D: 3 a 4,9, E: abaixo de 3). Obrigatoriamente, aceite a letra em maiúscula ou minúscula na mesma estrutura ESCOLHA-CASO.',
          'referencia' => <<<'TXT'
Algoritmo FaixaDoConceito
VAR
    conceito: CARACTERE;
INICIO
    LEIA(conceito);
    ESCOLHA conceito
        CASO "A", "a": ESCREVA("9 a 10");
        CASO "B", "b": ESCREVA("7 a 8,9");
        CASO "C", "c": ESCREVA("5 a 6,9");
        CASO "D", "d": ESCREVA("3 a 4,9");
        CASO "E", "e": ESCREVA("Abaixo de 3");
        OUTROCASO: ESCREVA("Conceito inválido");
    FIMESCOLHA
FIM.
Exigido: cada CASO listando a letra maiúscula e a minúscula, mais OUTROCASO para valores inválidos.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Decisão — Lista 4: operadores lógicos',
      'descricao' => 'Condições compostas com E, OU e NAO.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia um número e informe se ele está no intervalo fechado de 10 a 20.',
          'referencia' => <<<'TXT'
Algoritmo NoIntervalo
VAR
    n: REAL;
INICIO
    LEIA(n);
    SE (n >= 10) E (n <= 20) ENTAO
        ESCREVA("Dentro do intervalo");
    SENAO
        ESCREVA("Fora do intervalo");
    FIMSE
FIM.
Conceitos exigidos: condição composta com E e limites inclusivos (>= e <=).
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia a idade de uma pessoa e informe se ela tem direito à meia-entrada, concedida a menores de 12 anos e a pessoas com 60 anos ou mais.',
          'referencia' => 'Conceitos esperados: condição composta com OU (idade < 12 OU idade >= 60) e mensagens para os dois resultados.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um ano e informe se ele é bissexto (divisível por 4 e não por 100, ou divisível por 400). Você deve usar uma única condição com os operadores E, OU e NAO.',
          'referencia' => <<<'TXT'
Algoritmo AnoBissexto
VAR
    ano: INTEIRO;
INICIO
    LEIA(ano);
    SE ((ano MOD 4 = 0) E NAO (ano MOD 100 = 0)) OU (ano MOD 400 = 0) ENTAO
        ESCREVA("Bissexto");
    SENAO
        ESCREVA("Não bissexto");
    FIMSE
FIM.
Exigido: a regra completa em uma única condição combinando E, OU e NAO. SEs aninhados não atendem ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia a nota e a frequência (em %) de um aluno e informe se ele foi aprovado, sabendo que é preciso ter nota mínima 6 e frequência mínima de 75%.',
          'referencia' => <<<'TXT'
Algoritmo NotaEFrequencia
VAR
    nota, frequencia: REAL;
INICIO
    LEIA(nota, frequencia);
    SE (nota >= 6) E (frequencia >= 75) ENTAO
        ESCREVA("Aprovado");
    SENAO
        ESCREVA("Reprovado");
    FIMSE
FIM.
Conceitos exigidos: as duas condições combinadas com E, com limites inclusivos.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um caractere e informe se ele é uma vogal (a, e, i, o, u, em minúscula) ou não.',
          'referencia' => 'Conceitos esperados: comparação do caractere com as cinco vogais, combinadas com OU (ou uma seleção múltipla), e mensagem para cada resultado.',
        ],
      ],
    ],
    [
      'titulo' => 'Decisão — Lista 5: SE aninhado e faixas',
      'descricao' => 'Classificação de valores em faixas com decisões encadeadas.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o IMC de uma pessoa e informe a classificação: abaixo de 18,5 (abaixo do peso), de 18,5 a 24,9 (normal), de 25 a 29,9 (sobrepeso) e 30 ou mais (obesidade).',
          'referencia' => 'Conceitos esperados: comparações em faixas sem sobreposição, cobrindo os quatro casos com SEs encadeados ou condições compostas.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia a nota de um aluno (0 a 10) e exiba o conceito: A (9 a 10), B (7 a 8,9), C (5 a 6,9) ou D (abaixo de 5).',
          'referencia' => <<<'TXT'
Algoritmo ConceitoDaNota
VAR
    nota: REAL;
INICIO
    LEIA(nota);
    SE nota >= 9 ENTAO
        ESCREVA("A");
    SENAO
        SE nota >= 7 ENTAO
            ESCREVA("B");
        SENAO
            SE nota >= 5 ENTAO
                ESCREVA("C");
            SENAO
                ESCREVA("D");
            FIMSE
        FIMSE
    FIMSE
FIM.
Conceitos exigidos: SEs aninhados testando os limites da maior para a menor faixa.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia a temperatura do dia e exiba "Frio" para menos de 15 °C, "Agradável" de 15 °C a 25 °C e "Quente" acima de 25 °C.',
          'referencia' => 'Conceitos esperados: três faixas de temperatura sem lacunas nem sobreposição e uma mensagem para cada faixa.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia o salário de um funcionário e calcule o imposto: isento até R$ 2.000,00; 10% sobre o salário de R$ 2.000,01 a R$ 4.000,00; 20% acima disso. Você deve usar SE aninhado, testando primeiro a faixa de isenção.',
          'referencia' => <<<'TXT'
Algoritmo ImpostoPorFaixa
VAR
    salario, imposto: REAL;
INICIO
    LEIA(salario);
    SE salario <= 2000 ENTAO
        imposto <- 0;
    SENAO
        SE salario <= 4000 ENTAO
            imposto <- salario * 0.10;
        SENAO
            imposto <- salario * 0.20;
        FIMSE
    FIMSE
    ESCREVA("Imposto: ", imposto);
FIM.
Exigido: SE aninhado com a isenção testada primeiro e o imposto guardado em variável antes de exibir.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia a idade de um nadador e exiba a categoria: infantil A (5 a 7 anos), infantil B (8 a 10), juvenil A (11 a 13), juvenil B (14 a 17) e adulto (18 ou mais). Você deve usar SE aninhado e informar "Idade inválida" para menores de 5 anos.',
          'referencia' => <<<'TXT'
Algoritmo CategoriaNadador
VAR
    idade: INTEIRO;
INICIO
    LEIA(idade);
    SE idade < 5 ENTAO
        ESCREVA("Idade inválida");
    SENAO
        SE idade <= 7 ENTAO
            ESCREVA("Infantil A");
        SENAO
            SE idade <= 10 ENTAO
                ESCREVA("Infantil B");
            SENAO
                SE idade <= 13 ENTAO
                    ESCREVA("Juvenil A");
                SENAO
                    SE idade <= 17 ENTAO
                        ESCREVA("Juvenil B");
                    SENAO
                        ESCREVA("Adulto");
                    FIMSE
                FIMSE
            FIMSE
        FIMSE
    FIMSE
FIM.
Exigido: SEs aninhados em ordem crescente de idade, começando pela idade inválida.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Decisão — Lista 6: triângulos e geometria',
      'descricao' => 'Validação e classificação de figuras.',
      'questoes' => [
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia três valores e informe se eles podem formar um triângulo (cada lado menor que a soma dos outros dois). Você deve usar uma única condição com o operador E.',
          'referencia' => <<<'TXT'
Algoritmo FormaTriangulo
VAR
    a, b, c: REAL;
INICIO
    LEIA(a, b, c);
    SE (a < b + c) E (b < a + c) E (c < a + b) ENTAO
        ESCREVA("Formam um triângulo");
    SENAO
        ESCREVA("Não formam um triângulo");
    FIMSE
FIM.
Exigido: as três desigualdades triangulares na mesma condição, ligadas por E.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia os três lados de um triângulo válido e informe se ele é equilátero, isósceles ou escaleno.',
          'referencia' => 'Conceitos esperados: três lados iguais (equilátero), exatamente dois iguais (isósceles) e todos diferentes (escaleno), testando primeiro o caso equilátero.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia os três lados de um triângulo, com o maior lado informado por último, e informe se ele é retângulo, acutângulo ou obtusângulo.',
          'referencia' => <<<'TXT'
Algoritmo ClassificaAngulos
VAR
    a, b, c: REAL;
INICIO
    LEIA(a, b, c);
    SE c * c = a * a + b * b ENTAO
        ESCREVA("Retângulo");
    SENAO
        SE c * c < a * a + b * b ENTAO
            ESCREVA("Acutângulo");
        SENAO
            ESCREVA("Obtusângulo");
        FIMSE
    FIMSE
FIM.
Conceitos exigidos: comparar o quadrado do maior lado com a soma dos quadrados dos outros dois (igual, menor ou maior).
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia as coordenadas x e y de um ponto e informe em qual quadrante ele está, ou se está sobre um dos eixos.',
          'referencia' => 'Conceitos esperados: tratar x = 0 ou y = 0 como "sobre um eixo" e usar os sinais de x e y para identificar os quatro quadrantes.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o raio de uma circunferência centrada na origem e as coordenadas de um ponto e informe se o ponto está dentro, sobre ou fora da circunferência.',
          'referencia' => <<<'TXT'
Algoritmo PontoNaCircunferencia
VAR
    raio, x, y, d2: REAL;
INICIO
    LEIA(raio, x, y);
    d2 <- x * x + y * y;
    SE d2 < raio * raio ENTAO
        ESCREVA("Dentro");
    SENAO
        SE d2 = raio * raio ENTAO
            ESCREVA("Sobre");
        SENAO
            ESCREVA("Fora");
        FIMSE
    FIMSE
FIM.
Conceitos exigidos: comparar o quadrado da distância à origem com o quadrado do raio nos três casos.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Decisão — Lista 7: validação de entradas',
      'descricao' => 'Rejeitar valores inválidos antes de calcular.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia uma nota e informe "Nota válida" se ela estiver entre 0 e 10, e "Nota inválida" caso contrário.',
          'referencia' => 'Conceitos esperados: condição que verifica os dois limites (nota >= 0 e nota <= 10) e mensagens para os dois casos.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia dois números e exiba o resultado da divisão do primeiro pelo segundo, informando "Divisão impossível" quando o divisor for zero.',
          'referencia' => 'Conceitos esperados: testar o divisor antes de dividir e só calcular quando ele for diferente de zero.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia os valores a, b e c de uma equação do segundo grau e exiba as raízes reais, ou informe que não existem raízes reais.',
          'referencia' => <<<'TXT'
Algoritmo RaizesSegundoGrau
VAR
    a, b, c, delta, x1, x2: REAL;
INICIO
    LEIA(a, b, c);
    delta <- b * b - 4 * a * c;
    SE delta < 0 ENTAO
        ESCREVA("Não existem raízes reais");
    SENAO
        x1 <- (-b + RAIZ(delta)) / (2 * a);
        x2 <- (-b - RAIZ(delta)) / (2 * a);
        ESCREVA("x1 = ", x1, " x2 = ", x2);
    FIMSE
FIM.
Conceitos exigidos: calcular o delta, testar se é negativo e aplicar a fórmula de Bhaskara quando houver raízes reais.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia o dia, o mês e o ano de uma data e informe se ela é válida, considerando meses de 30 e 31 dias e fevereiro com 28 ou 29 dias em anos bissextos. Você deve usar uma variável para guardar o número de dias do mês antes de validar o dia.',
          'referencia' => <<<'TXT'
Algoritmo ValidaData
VAR
    dia, mes, ano, diasNoMes: INTEIRO;
INICIO
    LEIA(dia, mes, ano);
    diasNoMes <- 0;
    SE (mes = 4) OU (mes = 6) OU (mes = 9) OU (mes = 11) ENTAO
        diasNoMes <- 30;
    SENAO
        SE mes = 2 ENTAO
            SE ((ano MOD 4 = 0) E (ano MOD 100 <> 0)) OU (ano MOD 400 = 0) ENTAO
                diasNoMes <- 29;
            SENAO
                diasNoMes <- 28;
            FIMSE
        SENAO
            SE (mes >= 1) E (mes <= 12) ENTAO
                diasNoMes <- 31;
            FIMSE
        FIMSE
    FIMSE
    SE (diasNoMes > 0) E (dia >= 1) E (dia <= diasNoMes) ENTAO
        ESCREVA("Data válida");
    SENAO
        ESCREVA("Data inválida");
    FIMSE
FIM.
Exigido: descobrir os dias do mês (com a regra do ano bissexto) em uma variável e só depois validar o dia; mês fora de 1 a 12 resulta em data inválida.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia a senha numérica digitada por um usuário e informe "Acesso permitido" se ela for igual a 4321 e "Acesso negado" caso contrário.',
          'referencia' => <<<'TXT'
Algoritmo VerificaSenha
VAR
    senha: INTEIRO;
INICIO
    LEIA(senha);
    SE senha = 4321 ENTAO
        ESCREVA("Acesso permitido");
    SENAO
        ESCREVA("Acesso negado");
    FIMSE
FIM.
Conceitos exigidos: comparação de igualdade com o valor esperado e as duas mensagens.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Decisão — Lista 8: problemas comerciais',
      'descricao' => 'Descontos, tarifas e regras de negócio.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Uma loja dá 10% de desconto em compras acima de R$ 100,00. Escreva um algoritmo que leia o valor da compra e exiba o valor a pagar.',
          'referencia' => <<<'TXT'
Algoritmo DescontoAcimaDeCem
VAR
    valor: REAL;
INICIO
    LEIA(valor);
    SE valor > 100 ENTAO
        valor <- valor * 0.90;
    FIMSE
    ESCREVA("Valor a pagar: ", valor);
FIM.
Conceitos exigidos: aplicar o desconto só quando o valor passa de 100 (SE simples) e exibir o valor final.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Uma operadora cobra R$ 50,00 por mês com franquia de 100 minutos e R$ 0,50 por minuto excedente. Escreva um algoritmo que leia os minutos usados e exiba o valor da conta. Você deve usar uma variável para os minutos excedentes.',
          'referencia' => <<<'TXT'
Algoritmo ContaTelefone
VAR
    minutos, excedente: INTEIRO;
    conta: REAL;
INICIO
    LEIA(minutos);
    excedente <- 0;
    SE minutos > 100 ENTAO
        excedente <- minutos - 100;
    FIMSE
    conta <- 50 + excedente * 0.50;
    ESCREVA("Conta: R$ ", conta);
FIM.
Exigido: calcular os minutos excedentes em variável própria (zero quando não houver excedente) e somar ao valor fixo.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Um estacionamento cobra R$ 8,00 pela primeira hora e R$ 3,00 por hora adicional. Escreva um algoritmo que leia a quantidade de horas (inteira) e exiba o valor a pagar.',
          'referencia' => 'Conceitos esperados: tratar a primeira hora separadamente e somar R$ 3,00 por hora acima de uma; prever o caso de zero horas.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia o saldo de uma conta e o valor de um saque e informe se o saque pode ser feito, exibindo o novo saldo quando possível.',
          'referencia' => 'Conceitos esperados: comparar o saque com o saldo, só subtrair quando houver saldo suficiente e exibir a mensagem adequada.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Uma empresa reajusta salários assim: até R$ 1.500,00, aumento de 15%; de R$ 1.500,01 a R$ 3.000,00, 10%; acima disso, 5%. Escreva um algoritmo que leia o salário e exiba o percentual aplicado, o valor do aumento e o novo salário. Obrigatoriamente, o percentual deve ser guardado em uma variável escolhida pelas decisões e usado em um único cálculo depois delas.',
          'referencia' => <<<'TXT'
Algoritmo ReajusteSalarial
VAR
    salario, percentual, aumento, novo: REAL;
INICIO
    LEIA(salario);
    SE salario <= 1500 ENTAO
        percentual <- 15;
    SENAO
        SE salario <= 3000 ENTAO
            percentual <- 10;
        SENAO
            percentual <- 5;
        FIMSE
    FIMSE
    aumento <- salario * percentual / 100;
    novo <- salario + aumento;
    ESCREVA(percentual, "% - aumento: ", aumento, " - novo salário: ", novo);
FIM.
Exigido: as decisões só escolhem o percentual; o aumento e o novo salário são calculados uma única vez, depois das decisões.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Decisão — Lista 9: decisões com texto e caracteres',
      'descricao' => 'Comparações com caracteres e cadeias.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia a letra "M" ou "F" para o sexo de uma pessoa e exiba "Masculino", "Feminino" ou "Opção inválida".',
          'referencia' => 'Conceitos esperados: comparar o caractere lido com "M" e com "F" e tratar qualquer outro valor como inválido.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia a resposta "S" ou "N" de um usuário à pergunta "Deseja continuar?" e exiba a mensagem correspondente, aceitando também as letras minúsculas.',
          'referencia' => <<<'TXT'
Algoritmo DesejaContinuar
VAR
    resposta: CARACTERE;
INICIO
    LEIA(resposta);
    SE (resposta = "S") OU (resposta = "s") ENTAO
        ESCREVA("Continuando...");
    SENAO
        SE (resposta = "N") OU (resposta = "n") ENTAO
            ESCREVA("Encerrando.");
        SENAO
            ESCREVA("Resposta inválida");
        FIMSE
    FIMSE
FIM.
Conceitos exigidos: condições com OU para maiúscula e minúscula e tratamento de resposta inválida.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o nome de usuário e a senha (texto) e exiba "Login aceito" somente se o usuário for "admin" e a senha for "1234". Você deve usar uma única condição com o operador E.',
          'referencia' => <<<'TXT'
Algoritmo LoginSimples
VAR
    usuario, senha: CARACTERE;
INICIO
    LEIA(usuario, senha);
    SE (usuario = "admin") E (senha = "1234") ENTAO
        ESCREVA("Login aceito");
    SENAO
        ESCREVA("Usuário ou senha incorretos");
    FIMSE
FIM.
Exigido: as duas comparações na mesma condição, ligadas por E.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um caractere e informe se ele é um dígito numérico (de "0" a "9"), uma letra maiúscula, uma letra minúscula ou outro símbolo.',
          'referencia' => <<<'TXT'
Algoritmo TipoDoCaractere
VAR
    c: CARACTERE;
INICIO
    LEIA(c);
    SE (c >= "0") E (c <= "9") ENTAO
        ESCREVA("Dígito");
    SENAO
        SE (c >= "A") E (c <= "Z") ENTAO
            ESCREVA("Letra maiúscula");
        SENAO
            SE (c >= "a") E (c <= "z") ENTAO
                ESCREVA("Letra minúscula");
            SENAO
                ESCREVA("Outro símbolo");
            FIMSE
        FIMSE
    FIMSE
FIM.
Conceitos exigidos: comparações por intervalo de caracteres para dígitos, maiúsculas e minúsculas, com o caso restante.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o turno em que um aluno estuda ("M" para matutino, "V" para vespertino ou "N" para noturno) e exiba "Bom dia!", "Boa tarde!" ou "Boa noite!", ou "Turno inválido".',
          'referencia' => 'Conceitos esperados: seleção pelo caractere do turno com uma mensagem para cada opção e tratamento do valor inválido.',
        ],
      ],
    ],
    [
      'titulo' => 'Decisão — Lista 10: problemas integradores',
      'descricao' => 'Situações com várias decisões combinadas.',
      'questoes' => [
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia o peso de uma encomenda (kg) e o código do destino (1 = mesma cidade, 2 = mesmo estado, 3 = outro estado) e exiba o frete: R$ 10,00, R$ 20,00 ou R$ 35,00 por destino, mais R$ 2,00 por quilo acima de 5 kg. Você deve usar ESCOLHA-CASO para o destino e SE para o adicional por peso.',
          'referencia' => <<<'TXT'
Algoritmo CalculaFrete
VAR
    peso, frete: REAL;
    destino: INTEIRO;
INICIO
    LEIA(peso, destino);
    frete <- 0;
    ESCOLHA destino
        CASO 1: frete <- 10;
        CASO 2: frete <- 20;
        CASO 3: frete <- 35;
        OUTROCASO: ESCREVA("Destino inválido");
    FIMESCOLHA
    SE frete > 0 ENTAO
        SE peso > 5 ENTAO
            frete <- frete + (peso - 5) * 2;
        FIMSE
        ESCREVA("Frete: R$ ", frete);
    FIMSE
FIM.
Exigido: ESCOLHA-CASO para o valor base por destino e SE separado para o adicional por quilo acima de 5 kg.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o horário (hora de 0 a 23) e exiba se é madrugada (0 a 5), manhã (6 a 11), tarde (12 a 17) ou noite (18 a 23), ou "Hora inválida".',
          'referencia' => 'Conceitos esperados: validar a hora e classificá-la em quatro faixas contíguas com decisões encadeadas.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia as notas de duas provas de um aluno. Se a média for maior ou igual a 7, ele está aprovado; se for menor que 4, reprovado; caso contrário, ele faz exame, e o algoritmo deve ler a nota do exame e aprovar se a média entre a média anterior e o exame for maior ou igual a 5.',
          'referencia' => <<<'TXT'
Algoritmo SituacaoComExame
VAR
    p1, p2, media, exame, mediaFinal: REAL;
INICIO
    LEIA(p1, p2);
    media <- (p1 + p2) / 2;
    SE media >= 7 ENTAO
        ESCREVA("Aprovado");
    SENAO
        SE media < 4 ENTAO
            ESCREVA("Reprovado");
        SENAO
            LEIA(exame);
            mediaFinal <- (media + exame) / 2;
            SE mediaFinal >= 5 ENTAO
                ESCREVA("Aprovado no exame");
            SENAO
                ESCREVA("Reprovado no exame");
            FIMSE
        FIMSE
    FIMSE
FIM.
Conceitos exigidos: média das provas, as três situações e a leitura do exame apenas quando necessária.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia a velocidade de um carro e o limite da via e informe: "Sem multa" se estiver até o limite; "Multa leve" até 20% acima; "Multa grave" acima disso. Você deve usar uma variável para o percentual de excesso.',
          'referencia' => <<<'TXT'
Algoritmo MultaVelocidade
VAR
    velocidade, limite, excesso: REAL;
INICIO
    LEIA(velocidade, limite);
    excesso <- (velocidade - limite) / limite * 100;
    SE excesso <= 0 ENTAO
        ESCREVA("Sem multa");
    SENAO
        SE excesso <= 20 ENTAO
            ESCREVA("Multa leve");
        SENAO
            ESCREVA("Multa grave");
        FIMSE
    FIMSE
FIM.
Exigido: calcular o percentual de excesso em uma variável e decidir a partir dela.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia o número de gols de dois times em uma partida e informe qual time venceu ou se houve empate.',
          'referencia' => 'Conceitos esperados: comparar os gols dos dois times e tratar os três resultados possíveis (vitória de um, vitória do outro e empate).',
        ],
      ],
    ],
  ],
];
