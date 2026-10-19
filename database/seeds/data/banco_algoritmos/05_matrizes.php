<?php

declare(strict_types=1);

return [
  'tema' => 'Matrizes',
  'exercicios' => [
    [
      'titulo' => 'Matrizes — Lista 1: leitura, exibição e percurso',
      'descricao' => 'Declaração de matrizes e percurso por linhas e colunas.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia os elementos de uma matriz 3 × 3 de inteiros e a exiba em formato de tabela, uma linha por vez.',
          'referencia' => 'Conceitos esperados: matriz 3 × 3, dois laços aninhados (linhas e colunas) para ler e outros dois para exibir, com quebra de linha ao fim de cada linha.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 4 × 4 de inteiros e exiba a soma de todos os seus elementos.',
          'referencia' => <<<'TXT'
Algoritmo SomaDaMatriz
VAR
    m: MATRIZ[1..4, 1..4] DE INTEIRO;
    i, j, soma: INTEIRO;
INICIO
    soma <- 0;
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 4 FACA
            LEIA(m[i, j]);
            soma <- soma + m[i, j];
        FIMPARA
    FIMPARA
    ESCREVA("Soma: ", soma);
FIM.
Conceitos exigidos: laços aninhados para linhas e colunas e acumulador iniciado em zero.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que preencha uma matriz 5 × 5 em que cada elemento seja a soma do seu índice de linha com o seu índice de coluna, e exiba a matriz. Você deve usar dois laços PARA aninhados, sem ler valores do usuário.',
          'referencia' => <<<'TXT'
Algoritmo PreencheComIndices
VAR
    m: MATRIZ[1..5, 1..5] DE INTEIRO;
    i, j: INTEIRO;
INICIO
    PARA i DE 1 ATE 5 FACA
        PARA j DE 1 ATE 5 FACA
            m[i, j] <- i + j;
            ESCREVA(m[i, j], " ");
        FIMPARA
        ESCREVAL("");
    FIMPARA
FIM.
Exigido: preenchimento por fórmula (m[i, j] <- i + j) em dois PARA aninhados, sem LEIA.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 3 × 4 de inteiros e exiba quantos elementos são negativos.',
          'referencia' => 'Conceitos esperados: percorrer todos os elementos com laços aninhados e contar os menores que zero.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 4 × 4 de inteiros e exiba o maior elemento e a linha e a coluna em que ele está.',
          'referencia' => <<<'TXT'
Algoritmo MaiorDaMatriz
VAR
    m: MATRIZ[1..4, 1..4] DE INTEIRO;
    i, j, linha, coluna: INTEIRO;
INICIO
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 4 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    linha <- 1;
    coluna <- 1;
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 4 FACA
            SE m[i, j] > m[linha, coluna] ENTAO
                linha <- i;
                coluna <- j;
            FIMSE
        FIMPARA
    FIMPARA
    ESCREVA("Maior: ", m[linha, coluna], " em (", linha, ", ", coluna, ")");
FIM.
Conceitos exigidos: guardar a linha e a coluna do maior e comparar com m[linha, coluna] em laços aninhados.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Matrizes — Lista 2: diagonais',
      'descricao' => 'Diagonal principal, diagonal secundária e regiões da matriz.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 4 × 4 e exiba a soma dos elementos da diagonal principal.',
          'referencia' => <<<'TXT'
Algoritmo SomaDiagonalPrincipal
VAR
    m: MATRIZ[1..4, 1..4] DE INTEIRO;
    i, j, soma: INTEIRO;
INICIO
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 4 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    soma <- 0;
    PARA i DE 1 ATE 4 FACA
        soma <- soma + m[i, i];
    FIMPARA
    ESCREVA("Soma da diagonal principal: ", soma);
FIM.
Conceitos exigidos: elementos da diagonal principal têm linha igual à coluna (m[i, i]).
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 5 × 5 e exiba os elementos da diagonal principal.',
          'referencia' => 'Conceitos esperados: exibir os elementos em que o índice da linha é igual ao da coluna.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 4 × 4 e exiba os elementos da diagonal secundária.',
          'referencia' => 'Conceitos esperados: na diagonal secundária de uma matriz N × N, a coluna é N + 1 − linha (para 4 × 4, j = 5 − i).',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 5 × 5 e exiba a soma dos elementos acima da diagonal principal. Você deve usar laços em que a coluna comece em linha + 1, sem testar a condição com SE.',
          'referencia' => <<<'TXT'
Algoritmo SomaAcimaDaDiagonal
VAR
    m: MATRIZ[1..5, 1..5] DE INTEIRO;
    i, j, soma: INTEIRO;
INICIO
    PARA i DE 1 ATE 5 FACA
        PARA j DE 1 ATE 5 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    soma <- 0;
    PARA i DE 1 ATE 4 FACA
        PARA j DE i + 1 ATE 5 FACA
            soma <- soma + m[i, j];
        FIMPARA
    FIMPARA
    ESCREVA("Soma acima da diagonal: ", soma);
FIM.
Exigido: o laço das colunas começa em i + 1, percorrendo só a região acima da diagonal, sem SE (j > i).
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 4 × 4 e informe se ela é triangular inferior (todos os elementos acima da diagonal principal iguais a zero).',
          'referencia' => <<<'TXT'
Algoritmo TriangularInferior
VAR
    m: MATRIZ[1..4, 1..4] DE REAL;
    i, j: INTEIRO;
    triangular: LOGICO;
INICIO
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 4 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    triangular <- VERDADEIRO;
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 4 FACA
            SE (j > i) E (m[i, j] <> 0) ENTAO
                triangular <- FALSO;
            FIMSE
        FIMPARA
    FIMPARA
    SE triangular ENTAO
        ESCREVA("É triangular inferior");
    SENAO
        ESCREVA("Não é triangular inferior");
    FIMSE
FIM.
Conceitos exigidos: verificar a região j > i e usar uma variável lógica que vira falsa ao encontrar um elemento diferente de zero.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Matrizes — Lista 3: linhas e colunas',
      'descricao' => 'Totais e estatísticas por linha e por coluna.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 3 × 4 e exiba a soma de cada linha.',
          'referencia' => 'Conceitos esperados: para cada linha, zerar um acumulador, somar as colunas daquela linha e exibir o total da linha.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 4 × 3 e exiba a soma de cada coluna. Você deve usar o laço das colunas por fora e o das linhas por dentro.',
          'referencia' => <<<'TXT'
Algoritmo SomaPorColuna
VAR
    m: MATRIZ[1..4, 1..3] DE INTEIRO;
    i, j, soma: INTEIRO;
INICIO
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 3 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    PARA j DE 1 ATE 3 FACA
        soma <- 0;
        PARA i DE 1 ATE 4 FACA
            soma <- soma + m[i, j];
        FIMPARA
        ESCREVA("Coluna ", j, ": ", soma);
    FIMPARA
FIM.
Exigido: laço externo nas colunas e interno nas linhas, com o acumulador zerado a cada coluna.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Uma matriz 5 × 4 guarda as notas de 5 alunos em 4 provas. Escreva um algoritmo que leia a matriz e exiba a média de cada aluno.',
          'referencia' => <<<'TXT'
Algoritmo MediaPorAluno
VAR
    notas: MATRIZ[1..5, 1..4] DE REAL;
    aluno, prova: INTEIRO;
    soma: REAL;
INICIO
    PARA aluno DE 1 ATE 5 FACA
        PARA prova DE 1 ATE 4 FACA
            LEIA(notas[aluno, prova]);
        FIMPARA
    FIMPARA
    PARA aluno DE 1 ATE 5 FACA
        soma <- 0;
        PARA prova DE 1 ATE 4 FACA
            soma <- soma + notas[aluno, prova];
        FIMPARA
        ESCREVA("Aluno ", aluno, ": ", soma / 4);
    FIMPARA
FIM.
Conceitos exigidos: cada linha representa um aluno; somar as colunas da linha e dividir por 4.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Uma matriz 3 × 12 guarda as vendas de 3 lojas nos 12 meses do ano. Escreva um algoritmo que leia a matriz e exiba qual loja vendeu mais no ano.',
          'referencia' => 'Conceitos esperados: somar cada linha (total anual da loja) e guardar a loja com o maior total.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 4 × 4 e gere um vetor com o maior elemento de cada linha, exibindo o vetor.',
          'referencia' => <<<'TXT'
Algoritmo MaiorPorLinha
VAR
    m: MATRIZ[1..4, 1..4] DE INTEIRO;
    maiores: VETOR[1..4] DE INTEIRO;
    i, j: INTEIRO;
INICIO
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 4 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    PARA i DE 1 ATE 4 FACA
        maiores[i] <- m[i, 1];
        PARA j DE 2 ATE 4 FACA
            SE m[i, j] > maiores[i] ENTAO
                maiores[i] <- m[i, j];
            FIMSE
        FIMPARA
        ESCREVA(maiores[i]);
    FIMPARA
FIM.
Conceitos exigidos: para cada linha, começar pelo primeiro elemento e procurar o maior nas demais colunas, guardando-o no vetor.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Matrizes — Lista 4: transposta e simetria',
      'descricao' => 'Transformações e propriedades de matrizes quadradas.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 3 × 4 e gere e exiba a sua transposta (4 × 3).',
          'referencia' => <<<'TXT'
Algoritmo Transposta
VAR
    m: MATRIZ[1..3, 1..4] DE INTEIRO;
    t: MATRIZ[1..4, 1..3] DE INTEIRO;
    i, j: INTEIRO;
INICIO
    PARA i DE 1 ATE 3 FACA
        PARA j DE 1 ATE 4 FACA
            LEIA(m[i, j]);
            t[j, i] <- m[i, j];
        FIMPARA
    FIMPARA
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 3 FACA
            ESCREVA(t[i, j], " ");
        FIMPARA
        ESCREVAL("");
    FIMPARA
FIM.
Conceitos exigidos: dimensões invertidas na transposta e atribuição t[j, i] <- m[i, j].
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 4 × 4 e informe se ela é simétrica (igual à sua transposta).',
          'referencia' => 'Conceitos esperados: comparar m[i, j] com m[j, i] para todos os pares e concluir que é simétrica só se não houver diferença.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 4 × 4 e a transforme na sua transposta na própria matriz. Você deve usar trocas apenas entre elementos acima e abaixo da diagonal, sem uma segunda matriz.',
          'referencia' => <<<'TXT'
Algoritmo TranspoeNoLugar
VAR
    m: MATRIZ[1..4, 1..4] DE INTEIRO;
    i, j, aux: INTEIRO;
INICIO
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 4 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    PARA i DE 1 ATE 3 FACA
        PARA j DE i + 1 ATE 4 FACA
            aux <- m[i, j];
            m[i, j] <- m[j, i];
            m[j, i] <- aux;
        FIMPARA
    FIMPARA
FIM.
Exigido: trocar m[i, j] com m[j, i] só para j > i; percorrer a matriz inteira desfaria as trocas, e usar outra matriz não atende ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que gere e exiba a matriz identidade de ordem 5 (1 na diagonal principal e 0 nas demais posições).',
          'referencia' => 'Conceitos esperados: laços aninhados atribuindo 1 quando a linha é igual à coluna e 0 caso contrário.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 3 × 3 e troque a primeira linha com a terceira, exibindo a matriz resultante.',
          'referencia' => <<<'TXT'
Algoritmo TrocaLinhas
VAR
    m: MATRIZ[1..3, 1..3] DE INTEIRO;
    i, j, aux: INTEIRO;
INICIO
    PARA i DE 1 ATE 3 FACA
        PARA j DE 1 ATE 3 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    PARA j DE 1 ATE 3 FACA
        aux <- m[1, j];
        m[1, j] <- m[3, j];
        m[3, j] <- aux;
    FIMPARA
    PARA i DE 1 ATE 3 FACA
        PARA j DE 1 ATE 3 FACA
            ESCREVA(m[i, j], " ");
        FIMPARA
        ESCREVAL("");
    FIMPARA
FIM.
Conceitos exigidos: percorrer as colunas trocando m[1, j] com m[3, j] por variável auxiliar.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Matrizes — Lista 5: operações entre matrizes',
      'descricao' => 'Soma, multiplicação por escalar e produto de matrizes.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia duas matrizes 3 × 3 e exiba a matriz soma.',
          'referencia' => 'Conceitos esperados: somar os elementos de mesma posição (C[i, j] = A[i, j] + B[i, j]) com laços aninhados e exibir o resultado.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 3 × 3 e um número real K e exiba a matriz multiplicada por K.',
          'referencia' => <<<'TXT'
Algoritmo MatrizPorEscalar
VAR
    m: MATRIZ[1..3, 1..3] DE REAL;
    i, j: INTEIRO;
    k: REAL;
INICIO
    PARA i DE 1 ATE 3 FACA
        PARA j DE 1 ATE 3 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    LEIA(k);
    PARA i DE 1 ATE 3 FACA
        PARA j DE 1 ATE 3 FACA
            ESCREVA(m[i, j] * k, " ");
        FIMPARA
        ESCREVAL("");
    FIMPARA
FIM.
Conceitos exigidos: multiplicar cada elemento por K em laços aninhados.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia duas matrizes 2 × 3 e exiba a matriz diferença (primeira menos a segunda).',
          'referencia' => 'Conceitos esperados: subtrair os elementos de mesma posição, respeitando a ordem primeira − segunda, e exibir a matriz 2 × 3 resultante.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz A 2 × 3 e uma matriz B 3 × 2 e exiba o produto A × B (2 × 2). Obrigatoriamente, cada elemento do resultado deve ser calculado com um terceiro laço que acumule a soma dos produtos.',
          'referencia' => <<<'TXT'
Algoritmo ProdutoDeMatrizes
VAR
    a: MATRIZ[1..2, 1..3] DE INTEIRO;
    b: MATRIZ[1..3, 1..2] DE INTEIRO;
    c: MATRIZ[1..2, 1..2] DE INTEIRO;
    i, j, k: INTEIRO;
INICIO
    PARA i DE 1 ATE 2 FACA
        PARA j DE 1 ATE 3 FACA
            LEIA(a[i, j]);
        FIMPARA
    FIMPARA
    PARA i DE 1 ATE 3 FACA
        PARA j DE 1 ATE 2 FACA
            LEIA(b[i, j]);
        FIMPARA
    FIMPARA
    PARA i DE 1 ATE 2 FACA
        PARA j DE 1 ATE 2 FACA
            c[i, j] <- 0;
            PARA k DE 1 ATE 3 FACA
                c[i, j] <- c[i, j] + a[i, k] * b[k, j];
            FIMPARA
            ESCREVA(c[i, j], " ");
        FIMPARA
        ESCREVAL("");
    FIMPARA
FIM.
Exigido: três laços aninhados, com c[i, j] zerado e acumulando a[i, k] × b[k, j]; multiplicar elemento a elemento não é produto de matrizes.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia duas matrizes 3 × 3 e informe se elas são iguais. Você deve usar uma variável lógica e um laço ENQUANTO que pare na primeira diferença encontrada.',
          'referencia' => <<<'TXT'
Algoritmo MatrizesIguais
VAR
    a, b: MATRIZ[1..3, 1..3] DE INTEIRO;
    i, j: INTEIRO;
    iguais: LOGICO;
INICIO
    PARA i DE 1 ATE 3 FACA
        PARA j DE 1 ATE 3 FACA
            LEIA(a[i, j]);
        FIMPARA
    FIMPARA
    PARA i DE 1 ATE 3 FACA
        PARA j DE 1 ATE 3 FACA
            LEIA(b[i, j]);
        FIMPARA
    FIMPARA
    iguais <- VERDADEIRO;
    i <- 1;
    ENQUANTO (i <= 3) E iguais FACA
        PARA j DE 1 ATE 3 FACA
            SE a[i, j] <> b[i, j] ENTAO
                iguais <- FALSO;
            FIMSE
        FIMPARA
        i <- i + 1;
    FIMENQUANTO
    SE iguais ENTAO
        ESCREVA("Iguais");
    SENAO
        ESCREVA("Diferentes");
    FIMSE
FIM.
Exigido: variável lógica e ENQUANTO nas linhas parando assim que uma diferença for encontrada.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Matrizes — Lista 6: tabuleiros e jogos',
      'descricao' => 'Matrizes que representam tabuleiros.',
      'questoes' => [
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Um tabuleiro de jogo da velha está em uma matriz 3 × 3 de caracteres ("X", "O" ou " "). Escreva um algoritmo que leia o tabuleiro e informe se o jogador "X" venceu. Você deve usar laços para verificar as linhas e as colunas e testes diretos para as duas diagonais.',
          'referencia' => <<<'TXT'
Algoritmo XVenceu
VAR
    t: MATRIZ[1..3, 1..3] DE CARACTERE;
    i, j: INTEIRO;
    venceu: LOGICO;
INICIO
    PARA i DE 1 ATE 3 FACA
        PARA j DE 1 ATE 3 FACA
            LEIA(t[i, j]);
        FIMPARA
    FIMPARA
    venceu <- FALSO;
    PARA i DE 1 ATE 3 FACA
        SE (t[i, 1] = "X") E (t[i, 2] = "X") E (t[i, 3] = "X") ENTAO
            venceu <- VERDADEIRO;
        FIMSE
        SE (t[1, i] = "X") E (t[2, i] = "X") E (t[3, i] = "X") ENTAO
            venceu <- VERDADEIRO;
        FIMSE
    FIMPARA
    SE (t[1, 1] = "X") E (t[2, 2] = "X") E (t[3, 3] = "X") ENTAO
        venceu <- VERDADEIRO;
    FIMSE
    SE (t[1, 3] = "X") E (t[2, 2] = "X") E (t[3, 1] = "X") ENTAO
        venceu <- VERDADEIRO;
    FIMSE
    SE venceu ENTAO
        ESCREVA("X venceu");
    SENAO
        ESCREVA("X não venceu");
    FIMSE
FIM.
Exigido: laço verificando as 3 linhas e as 3 colunas e testes explícitos das duas diagonais.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Um tabuleiro de xadrez 8 × 8 deve ser preenchido com "B" nas casas brancas e "P" nas pretas, começando com "B" na posição (1, 1). Escreva um algoritmo que preencha e exiba o tabuleiro.',
          'referencia' => 'Conceitos esperados: alternar as cores pela paridade da soma linha + coluna (par = "B", ímpar = "P") em laços aninhados.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Um tabuleiro de batalha naval 5 × 5 tem 1 nas casas com navio e 0 nas demais. Escreva um algoritmo que leia o tabuleiro e as coordenadas de um tiro e informe "Acertou" ou "Água".',
          'referencia' => <<<'TXT'
Algoritmo TiroBatalhaNaval
VAR
    mar: MATRIZ[1..5, 1..5] DE INTEIRO;
    i, j, linha, coluna: INTEIRO;
INICIO
    PARA i DE 1 ATE 5 FACA
        PARA j DE 1 ATE 5 FACA
            LEIA(mar[i, j]);
        FIMPARA
    FIMPARA
    LEIA(linha, coluna);
    SE mar[linha, coluna] = 1 ENTAO
        ESCREVA("Acertou");
    SENAO
        ESCREVA("Água");
    FIMSE
FIM.
Conceitos exigidos: ler o tabuleiro e acessar diretamente a posição informada para decidir o resultado.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Um mapa 6 × 6 tem 1 nas casas com obstáculo e 0 nas livres. Escreva um algoritmo que leia o mapa e exiba quantas casas livres existem em cada linha.',
          'referencia' => 'Conceitos esperados: para cada linha, contar os elementos iguais a zero e exibir a contagem da linha.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 3 × 3 de inteiros e informe se ela é um quadrado mágico (todas as linhas, colunas e as duas diagonais têm a mesma soma).',
          'referencia' => <<<'TXT'
Algoritmo QuadradoMagico
VAR
    m: MATRIZ[1..3, 1..3] DE INTEIRO;
    i, j, alvo, somaL, somaC, d1, d2: INTEIRO;
    magico: LOGICO;
INICIO
    PARA i DE 1 ATE 3 FACA
        PARA j DE 1 ATE 3 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    alvo <- m[1, 1] + m[1, 2] + m[1, 3];
    magico <- VERDADEIRO;
    d1 <- 0;
    d2 <- 0;
    PARA i DE 1 ATE 3 FACA
        somaL <- 0;
        somaC <- 0;
        PARA j DE 1 ATE 3 FACA
            somaL <- somaL + m[i, j];
            somaC <- somaC + m[j, i];
        FIMPARA
        SE (somaL <> alvo) OU (somaC <> alvo) ENTAO
            magico <- FALSO;
        FIMSE
        d1 <- d1 + m[i, i];
        d2 <- d2 + m[i, 4 - i];
    FIMPARA
    SE magico E (d1 = alvo) E (d2 = alvo) ENTAO
        ESCREVA("É quadrado mágico");
    SENAO
        ESCREVA("Não é quadrado mágico");
    FIMSE
FIM.
Conceitos exigidos: usar a soma da primeira linha como referência e comparar com cada linha, cada coluna e as duas diagonais.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Matrizes — Lista 7: tabelas de dados',
      'descricao' => 'Matrizes como tabelas de vendas, notas e medições.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Uma matriz 7 × 3 guarda a temperatura de 7 dias em 3 horários (manhã, tarde e noite). Escreva um algoritmo que leia a matriz e exiba a média de temperatura de cada dia.',
          'referencia' => 'Conceitos esperados: cada linha é um dia; somar as 3 colunas da linha e dividir por 3.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Uma matriz 4 × 6 guarda as vendas de 4 vendedores em 6 meses. Escreva um algoritmo que leia a matriz e exiba o mês com o maior total de vendas somando todos os vendedores.',
          'referencia' => 'Conceitos esperados: somar cada coluna (total do mês) e guardar o mês com o maior total.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Uma matriz 10 × 3 guarda as notas de 10 alunos em 3 provas. Escreva um algoritmo que leia a matriz e exiba quantos alunos tiveram média maior ou igual a 7.',
          'referencia' => <<<'TXT'
Algoritmo AlunosComMediaSete
VAR
    notas: MATRIZ[1..10, 1..3] DE REAL;
    i, j, aprovados: INTEIRO;
    soma: REAL;
INICIO
    aprovados <- 0;
    PARA i DE 1 ATE 10 FACA
        soma <- 0;
        PARA j DE 1 ATE 3 FACA
            LEIA(notas[i, j]);
            soma <- soma + notas[i, j];
        FIMPARA
        SE soma / 3 >= 7 ENTAO
            aprovados <- aprovados + 1;
        FIMSE
    FIMPARA
    ESCREVA("Alunos com média >= 7: ", aprovados);
FIM.
Conceitos exigidos: média por linha e contador dos alunos que atingem o critério.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Uma matriz 5 × 4 guarda o estoque de 5 produtos em 4 filiais. Escreva um algoritmo que leia a matriz e exiba o estoque total de cada produto e de cada filial. Você deve usar um vetor para os totais por produto e outro para os totais por filial, preenchidos no mesmo percurso.',
          'referencia' => <<<'TXT'
Algoritmo EstoquePorProdutoEFilial
VAR
    estoque: MATRIZ[1..5, 1..4] DE INTEIRO;
    porProduto: VETOR[1..5] DE INTEIRO;
    porFilial: VETOR[1..4] DE INTEIRO;
    i, j: INTEIRO;
INICIO
    PARA j DE 1 ATE 4 FACA
        porFilial[j] <- 0;
    FIMPARA
    PARA i DE 1 ATE 5 FACA
        porProduto[i] <- 0;
        PARA j DE 1 ATE 4 FACA
            LEIA(estoque[i, j]);
            porProduto[i] <- porProduto[i] + estoque[i, j];
            porFilial[j] <- porFilial[j] + estoque[i, j];
        FIMPARA
    FIMPARA
    PARA i DE 1 ATE 5 FACA
        ESCREVA("Produto ", i, ": ", porProduto[i]);
    FIMPARA
    PARA j DE 1 ATE 4 FACA
        ESCREVA("Filial ", j, ": ", porFilial[j]);
    FIMPARA
FIM.
Exigido: dois vetores de totais (por linha e por coluna) atualizados no mesmo percurso da matriz.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Uma matriz 6 × 6 guarda as distâncias (km) entre 6 cidades. Escreva um algoritmo que leia a matriz e um roteiro de 4 cidades (números de 1 a 6) em um vetor e exiba a distância total percorrida.',
          'referencia' => <<<'TXT'
Algoritmo DistanciaDoRoteiro
VAR
    dist: MATRIZ[1..6, 1..6] DE REAL;
    roteiro: VETOR[1..4] DE INTEIRO;
    i, j: INTEIRO;
    total: REAL;
INICIO
    PARA i DE 1 ATE 6 FACA
        PARA j DE 1 ATE 6 FACA
            LEIA(dist[i, j]);
        FIMPARA
    FIMPARA
    PARA i DE 1 ATE 4 FACA
        LEIA(roteiro[i]);
    FIMPARA
    total <- 0;
    PARA i DE 1 ATE 3 FACA
        total <- total + dist[roteiro[i], roteiro[i + 1]];
    FIMPARA
    ESCREVA("Distância total: ", total, " km");
FIM.
Conceitos exigidos: usar os elementos do vetor do roteiro como índices da matriz e somar os 3 trechos consecutivos.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Matrizes — Lista 8: busca e contagem',
      'descricao' => 'Localizar valores e contar ocorrências em matrizes.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 4 × 5 e um valor X e exiba todas as posições (linha, coluna) em que X aparece, ou uma mensagem se ele não aparecer.',
          'referencia' => <<<'TXT'
Algoritmo PosicoesDeX
VAR
    m: MATRIZ[1..4, 1..5] DE INTEIRO;
    i, j, x, achados: INTEIRO;
INICIO
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 5 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    LEIA(x);
    achados <- 0;
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 5 FACA
            SE m[i, j] = x ENTAO
                ESCREVA("(", i, ", ", j, ")");
                achados <- achados + 1;
            FIMSE
        FIMPARA
    FIMPARA
    SE achados = 0 ENTAO
        ESCREVA("Valor não encontrado");
    FIMSE
FIM.
Conceitos exigidos: percorrer toda a matriz exibindo cada ocorrência e contar para tratar o caso de nenhuma.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 5 × 5 e informe se o valor 0 aparece em alguma posição, parando a busca assim que encontrá-lo. Você deve usar laços ENQUANTO para linhas e colunas com uma variável lógica de controle.',
          'referencia' => <<<'TXT'
Algoritmo ExisteZero
VAR
    m: MATRIZ[1..5, 1..5] DE INTEIRO;
    i, j: INTEIRO;
    achou: LOGICO;
INICIO
    PARA i DE 1 ATE 5 FACA
        PARA j DE 1 ATE 5 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    achou <- FALSO;
    i <- 1;
    ENQUANTO (i <= 5) E NAO achou FACA
        j <- 1;
        ENQUANTO (j <= 5) E NAO achou FACA
            SE m[i, j] = 0 ENTAO
                achou <- VERDADEIRO;
            FIMSE
            j <- j + 1;
        FIMENQUANTO
        i <- i + 1;
    FIMENQUANTO
    SE achou ENTAO
        ESCREVA("Existe zero na matriz");
    SENAO
        ESCREVA("Não existe zero");
    FIMSE
FIM.
Exigido: dois ENQUANTO aninhados controlados pela variável lógica, encerrando ao achar o zero.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 4 × 4 de inteiros e exiba quantos elementos são pares.',
          'referencia' => 'Conceitos esperados: percorrer a matriz e contar os elementos com resto zero na divisão por 2.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 5 × 5 e exiba a linha que tem a maior soma de elementos.',
          'referencia' => 'Conceitos esperados: calcular a soma de cada linha e guardar o número da linha com a maior soma.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 4 × 4 e exiba os "pontos de sela": elementos que são, ao mesmo tempo, o menor da sua linha e o maior da sua coluna. Obrigatoriamente, para cada elemento, verifique a linha e a coluna inteiras antes de concluir.',
          'referencia' => <<<'TXT'
Algoritmo PontosDeSela
VAR
    m: MATRIZ[1..4, 1..4] DE INTEIRO;
    i, j, k: INTEIRO;
    menorDaLinha, maiorDaColuna: LOGICO;
INICIO
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 4 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 4 FACA
            menorDaLinha <- VERDADEIRO;
            maiorDaColuna <- VERDADEIRO;
            PARA k DE 1 ATE 4 FACA
                SE m[i, k] < m[i, j] ENTAO
                    menorDaLinha <- FALSO;
                FIMSE
                SE m[k, j] > m[i, j] ENTAO
                    maiorDaColuna <- FALSO;
                FIMSE
            FIMPARA
            SE menorDaLinha E maiorDaColuna ENTAO
                ESCREVA("Ponto de sela em (", i, ", ", j, "): ", m[i, j]);
            FIMSE
        FIMPARA
    FIMPARA
FIM.
Exigido: para cada elemento, um laço que percorra a linha e a coluna inteiras, com duas variáveis lógicas.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Matrizes — Lista 9: matrizes geradas por regra',
      'descricao' => 'Construção de matrizes a partir de padrões.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que gere uma matriz 4 × 4 em que cada elemento é o produto do índice da linha pelo índice da coluna e exiba a matriz.',
          'referencia' => 'Conceitos esperados: laços aninhados atribuindo i × j a cada posição e exibição em formato de tabela.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que gere uma matriz 5 × 5 com 1 na borda (primeira e última linha e coluna) e 0 no interior, exibindo-a.',
          'referencia' => <<<'TXT'
Algoritmo MatrizComBorda
VAR
    m: MATRIZ[1..5, 1..5] DE INTEIRO;
    i, j: INTEIRO;
INICIO
    PARA i DE 1 ATE 5 FACA
        PARA j DE 1 ATE 5 FACA
            SE (i = 1) OU (i = 5) OU (j = 1) OU (j = 5) ENTAO
                m[i, j] <- 1;
            SENAO
                m[i, j] <- 0;
            FIMSE
            ESCREVA(m[i, j], " ");
        FIMPARA
        ESCREVAL("");
    FIMPARA
FIM.
Conceitos exigidos: condição com OU identificando a primeira e a última linha e coluna.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que gere o triângulo de Pascal com 6 linhas em uma matriz 6 × 6 e o exiba. Você deve usar a regra m[i, j] = m[i − 1, j − 1] + m[i − 1, j], com 1 nas extremidades de cada linha.',
          'referencia' => <<<'TXT'
Algoritmo TrianguloDePascal
VAR
    m: MATRIZ[1..6, 1..6] DE INTEIRO;
    i, j: INTEIRO;
INICIO
    PARA i DE 1 ATE 6 FACA
        PARA j DE 1 ATE i FACA
            SE (j = 1) OU (j = i) ENTAO
                m[i, j] <- 1;
            SENAO
                m[i, j] <- m[i - 1, j - 1] + m[i - 1, j];
            FIMSE
            ESCREVA(m[i, j], " ");
        FIMPARA
        ESCREVAL("");
    FIMPARA
FIM.
Exigido: cada elemento interno calculado pela soma dos dois elementos acima, com 1 na primeira e na última posição da linha.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que preencha uma matriz 4 × 5 com os números de 1 a 20 em sequência, linha por linha, e a exiba.',
          'referencia' => <<<'TXT'
Algoritmo SequenciaNaMatriz
VAR
    m: MATRIZ[1..4, 1..5] DE INTEIRO;
    i, j, contador: INTEIRO;
INICIO
    contador <- 1;
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 5 FACA
            m[i, j] <- contador;
            contador <- contador + 1;
            ESCREVA(m[i, j], " ");
        FIMPARA
        ESCREVAL("");
    FIMPARA
FIM.
Conceitos exigidos: contador incrementado a cada posição, percorrendo a matriz por linhas.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que preencha uma matriz 5 × 5 com 1 nas duas diagonais (principal e secundária) e 0 nas demais posições, formando um "X", e a exiba.',
          'referencia' => 'Conceitos esperados: atribuir 1 quando j = i (diagonal principal) ou j = 6 − i (diagonal secundária) e 0 nos demais casos.',
        ],
      ],
    ],
    [
      'titulo' => 'Matrizes — Lista 10: problemas integradores',
      'descricao' => 'Problemas com matrizes, vetores e decisões combinados.',
      'questoes' => [
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Uma sala de cinema tem 10 fileiras com 12 poltronas, representadas por uma matriz em que 1 é ocupada e 0 é livre. Escreva um algoritmo que leia a matriz, exiba o percentual de ocupação e a fileira com mais poltronas livres. Você deve usar um único percurso pela matriz para calcular as duas informações.',
          'referencia' => <<<'TXT'
Algoritmo OcupacaoCinema
VAR
    sala: MATRIZ[1..10, 1..12] DE INTEIRO;
    i, j, ocupadas, livresLinha, maisLivres, fileira: INTEIRO;
INICIO
    ocupadas <- 0;
    maisLivres <- -1;
    fileira <- 0;
    PARA i DE 1 ATE 10 FACA
        livresLinha <- 0;
        PARA j DE 1 ATE 12 FACA
            LEIA(sala[i, j]);
            SE sala[i, j] = 1 ENTAO
                ocupadas <- ocupadas + 1;
            SENAO
                livresLinha <- livresLinha + 1;
            FIMSE
        FIMPARA
        SE livresLinha > maisLivres ENTAO
            maisLivres <- livresLinha;
            fileira <- i;
        FIMSE
    FIMPARA
    ESCREVA("Ocupação: ", ocupadas * 100 / 120, "% - Fileira mais livre: ", fileira);
FIM.
Exigido: um só percurso contando as ocupadas e os livres por fileira, atualizando a fileira mais livre ao fim de cada linha.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 4 × 4 e gere um vetor com a soma de cada coluna, exibindo depois o vetor.',
          'referencia' => 'Conceitos esperados: um vetor de 4 posições em que a posição j recebe a soma dos elementos da coluna j.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 3 × 3 de números reais e exiba a matriz com cada elemento substituído pelo seu valor dividido pelo maior elemento da matriz.',
          'referencia' => <<<'TXT'
Algoritmo NormalizaMatriz
VAR
    m: MATRIZ[1..3, 1..3] DE REAL;
    i, j: INTEIRO;
    maior: REAL;
INICIO
    PARA i DE 1 ATE 3 FACA
        PARA j DE 1 ATE 3 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    maior <- m[1, 1];
    PARA i DE 1 ATE 3 FACA
        PARA j DE 1 ATE 3 FACA
            SE m[i, j] > maior ENTAO
                maior <- m[i, j];
            FIMSE
        FIMPARA
    FIMPARA
    PARA i DE 1 ATE 3 FACA
        PARA j DE 1 ATE 3 FACA
            ESCREVA(m[i, j] / maior, " ");
        FIMPARA
        ESCREVAL("");
    FIMPARA
FIM.
Conceitos exigidos: encontrar o maior em um percurso completo e só depois dividir cada elemento por ele.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 4 × 4 e exiba a soma dos elementos de cada uma das duas diagonais e informe qual é maior. Você deve usar um único laço PARA para percorrer as duas diagonais ao mesmo tempo.',
          'referencia' => <<<'TXT'
Algoritmo ComparaDiagonais
VAR
    m: MATRIZ[1..4, 1..4] DE INTEIRO;
    i, j, principal, secundaria: INTEIRO;
INICIO
    PARA i DE 1 ATE 4 FACA
        PARA j DE 1 ATE 4 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    principal <- 0;
    secundaria <- 0;
    PARA i DE 1 ATE 4 FACA
        principal <- principal + m[i, i];
        secundaria <- secundaria + m[i, 5 - i];
    FIMPARA
    ESCREVA("Principal: ", principal, " Secundária: ", secundaria);
    SE principal > secundaria ENTAO
        ESCREVA("A diagonal principal é maior");
    SENAO
        SE secundaria > principal ENTAO
            ESCREVA("A diagonal secundária é maior");
        SENAO
            ESCREVA("As somas são iguais");
        FIMSE
    FIMSE
FIM.
Exigido: um único PARA somando m[i, i] e m[i, 5 − i] no mesmo passo, com o caso de empate tratado.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 3 × 5 e exiba a média de todos os seus elementos.',
          'referencia' => 'Conceitos esperados: somar todos os elementos com laços aninhados e dividir pelo total de posições (15).',
        ],
      ],
    ],
  ],
];
