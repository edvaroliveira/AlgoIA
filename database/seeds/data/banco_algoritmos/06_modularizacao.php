<?php

declare(strict_types=1);

return [
  'tema' => 'Modularização',
  'exercicios' => [
    [
      'titulo' => 'Modularização — Lista 1: primeiras funções',
      'descricao' => 'Funções com parâmetros e valor de retorno.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva uma função que receba um número inteiro e retorne o seu dobro, e um algoritmo principal que leia um número e exiba o resultado da função.',
          'referencia' => 'Conceitos esperados: função com um parâmetro inteiro que retorna o valor multiplicado por 2, e chamada da função no algoritmo principal exibindo o retorno.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva uma função que receba dois números reais e retorne o maior deles, e um algoritmo principal que leia os números e exiba o maior.',
          'referencia' => <<<'TXT'
Algoritmo MaiorComFuncao
VAR
    a, b: REAL;

FUNCAO Maior(x, y: REAL): REAL
INICIO
    SE x > y ENTAO
        RETORNE x;
    SENAO
        RETORNE y;
    FIMSE
FIMFUNCAO

INICIO
    LEIA(a, b);
    ESCREVA("Maior: ", Maior(a, b));
FIM.
Conceitos exigidos: função com dois parâmetros que retorna o maior, chamada no algoritmo principal com o valor de retorno exibido.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função que receba três notas e retorne a média aritmética, e um algoritmo principal que leia as notas de um aluno e informe se ele foi aprovado (média ≥ 6). Você deve usar o valor retornado pela função na decisão, sem recalcular a média no algoritmo principal.',
          'referencia' => <<<'TXT'
Algoritmo AprovacaoComFuncao
VAR
    n1, n2, n3: REAL;

FUNCAO Media(a, b, c: REAL): REAL
INICIO
    RETORNE (a + b + c) / 3;
FIMFUNCAO

INICIO
    LEIA(n1, n2, n3);
    SE Media(n1, n2, n3) >= 6 ENTAO
        ESCREVA("Aprovado");
    SENAO
        ESCREVA("Reprovado");
    FIMSE
FIM.
Exigido: a média calculada só dentro da função e o retorno usado na condição do algoritmo principal.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva uma função que receba uma temperatura em Celsius e retorne o valor em Kelvin, e use-a em um algoritmo que converta uma temperatura lida.',
          'referencia' => 'Conceitos esperados: função com parâmetro REAL retornando celsius + 273,15 e chamada no algoritmo principal.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função que receba um número inteiro e retorne VERDADEIRO se ele for par e FALSO caso contrário, e um algoritmo principal que leia 5 números e informe para cada um se é par.',
          'referencia' => <<<'TXT'
Algoritmo ParComFuncao
VAR
    i, n: INTEIRO;

FUNCAO EhPar(x: INTEIRO): LOGICO
INICIO
    RETORNE x MOD 2 = 0;
FIMFUNCAO

INICIO
    PARA i DE 1 ATE 5 FACA
        LEIA(n);
        SE EhPar(n) ENTAO
            ESCREVA(n, " é par");
        SENAO
            ESCREVA(n, " é ímpar");
        FIMSE
    FIMPARA
FIM.
Conceitos exigidos: função do tipo LOGICO e o seu retorno usado diretamente como condição do SE.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Modularização — Lista 2: procedimentos',
      'descricao' => 'Sub-rotinas sem retorno para organizar a saída e as tarefas.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva um procedimento que receba um número N e exiba uma linha com N asteriscos, e um algoritmo principal que o chame três vezes com tamanhos diferentes.',
          'referencia' => <<<'TXT'
Algoritmo LinhasDeAsteriscos
VAR
    tamanho: INTEIRO;

PROCEDIMENTO Linha(n: INTEIRO)
VAR
    i: INTEIRO;
INICIO
    PARA i DE 1 ATE n FACA
        ESCREVA("*");
    FIMPARA
    ESCREVAL("");
FIMPROCEDIMENTO

INICIO
    Linha(5);
    Linha(10);
    LEIA(tamanho);
    Linha(tamanho);
FIM.
Conceitos exigidos: procedimento com parâmetro, variável local para o laço e várias chamadas com argumentos diferentes.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um procedimento que exiba um cabeçalho com o nome da instituição e a data, e um algoritmo que o chame antes de exibir um relatório simples.',
          'referencia' => 'Conceitos esperados: procedimento sem retorno que agrupa as instruções de exibição do cabeçalho, chamado no algoritmo principal antes do restante da saída.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um procedimento que receba um número e exiba a sua tabuada de 1 a 10, e um algoritmo que exiba as tabuadas de 2 a 5 chamando o procedimento.',
          'referencia' => 'Conceitos esperados: procedimento com parâmetro e laço interno de 1 a 10, chamado dentro de um laço do algoritmo principal para os números de 2 a 5.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo com um menu (1 - Somar, 2 - Multiplicar, 0 - Sair) em que cada opção é executada por um procedimento próprio que lê os dois números e exibe o resultado. Você deve usar um procedimento para exibir o menu e um procedimento para cada operação.',
          'referencia' => <<<'TXT'
Algoritmo MenuComProcedimentos
VAR
    opcao: INTEIRO;

PROCEDIMENTO MostraMenu()
INICIO
    ESCREVA("1 - Somar  2 - Multiplicar  0 - Sair");
FIMPROCEDIMENTO

PROCEDIMENTO Somar()
VAR
    a, b: REAL;
INICIO
    LEIA(a, b);
    ESCREVA(a + b);
FIMPROCEDIMENTO

PROCEDIMENTO Multiplicar()
VAR
    a, b: REAL;
INICIO
    LEIA(a, b);
    ESCREVA(a * b);
FIMPROCEDIMENTO

INICIO
    REPITA
        MostraMenu();
        LEIA(opcao);
        ESCOLHA opcao
            CASO 1: Somar();
            CASO 2: Multiplicar();
        FIMESCOLHA
    ATE opcao = 0
FIM.
Exigido: um procedimento para o menu e um para cada operação, com variáveis locais; o algoritmo principal só controla o laço e a escolha.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um procedimento que receba o nome e a nota de um aluno e exiba uma linha de boletim com o nome, a nota e a situação (aprovado com nota ≥ 6), e use-o para 3 alunos lidos no algoritmo principal.',
          'referencia' => <<<'TXT'
Algoritmo BoletimComProcedimento
VAR
    i: INTEIRO;
    nome: CARACTERE;
    nota: REAL;

PROCEDIMENTO LinhaBoletim(n: CARACTERE; x: REAL)
INICIO
    SE x >= 6 ENTAO
        ESCREVA(n, " - ", x, " - Aprovado");
    SENAO
        ESCREVA(n, " - ", x, " - Reprovado");
    FIMSE
FIMPROCEDIMENTO

INICIO
    PARA i DE 1 ATE 3 FACA
        LEIA(nome, nota);
        LinhaBoletim(nome, nota);
    FIMPARA
FIM.
Conceitos exigidos: procedimento com parâmetros de tipos diferentes, decisão dentro dele e chamada no laço principal.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Modularização — Lista 3: passagem por valor e por referência',
      'descricao' => 'Parâmetros que alteram ou não as variáveis de quem chama.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Explique, com um pequeno algoritmo, a diferença entre passar uma variável por valor e por referência a um procedimento que soma 1 ao parâmetro.',
          'referencia' => 'Conceitos esperados: por valor, o procedimento trabalha com uma cópia e a variável original não muda; por referência (VAR), a alteração feita no procedimento aparece na variável de quem chamou; mostrar o valor antes e depois das duas chamadas.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um procedimento que troque os valores de duas variáveis inteiras e um algoritmo que leia dois números, chame o procedimento e exiba os valores trocados. Você deve usar passagem por referência nos dois parâmetros.',
          'referencia' => <<<'TXT'
Algoritmo TrocaPorReferencia
VAR
    a, b: INTEIRO;

PROCEDIMENTO Troca(VAR x, y: INTEIRO)
VAR
    aux: INTEIRO;
INICIO
    aux <- x;
    x <- y;
    y <- aux;
FIMPROCEDIMENTO

INICIO
    LEIA(a, b);
    Troca(a, b);
    ESCREVA("A = ", a, " B = ", b);
FIM.
Exigido: parâmetros por referência (VAR) para que a troca altere as variáveis do algoritmo principal; com passagem por valor a troca não teria efeito.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um procedimento que receba um valor em segundos e devolva, por parâmetros de referência, as horas, os minutos e os segundos correspondentes.',
          'referencia' => <<<'TXT'
Algoritmo ConverteComReferencia
VAR
    total, h, m, s: INTEIRO;

PROCEDIMENTO Converte(segundos: INTEIRO; VAR horas, minutos, resto: INTEIRO)
INICIO
    horas <- segundos DIV 3600;
    minutos <- (segundos MOD 3600) DIV 60;
    resto <- segundos MOD 60;
FIMPROCEDIMENTO

INICIO
    LEIA(total);
    Converte(total, h, m, s);
    ESCREVA(h, "h ", m, "min ", s, "s");
FIM.
Conceitos exigidos: um parâmetro de entrada por valor e três parâmetros de saída por referência.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um procedimento que receba o salário de um funcionário por referência e aplique um aumento de 10%, e um algoritmo que exiba o salário antes e depois da chamada.',
          'referencia' => 'Conceitos esperados: parâmetro por referência (VAR) alterado dentro do procedimento e exibição do salário atualizado no algoritmo principal.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um procedimento que receba três números inteiros por referência e os reorganize em ordem crescente, e um algoritmo que o use. Obrigatoriamente, a ordenação deve reaproveitar um procedimento de troca entre duas variáveis.',
          'referencia' => <<<'TXT'
Algoritmo OrdenaTresPorReferencia
VAR
    a, b, c: INTEIRO;

PROCEDIMENTO Troca(VAR x, y: INTEIRO)
VAR
    aux: INTEIRO;
INICIO
    aux <- x;
    x <- y;
    y <- aux;
FIMPROCEDIMENTO

PROCEDIMENTO Ordena(VAR x, y, z: INTEIRO)
INICIO
    SE x > y ENTAO
        Troca(x, y);
    FIMSE
    SE y > z ENTAO
        Troca(y, z);
    FIMSE
    SE x > y ENTAO
        Troca(x, y);
    FIMSE
FIMPROCEDIMENTO

INICIO
    LEIA(a, b, c);
    Ordena(a, b, c);
    ESCREVA(a, " ", b, " ", c);
FIM.
Exigido: o procedimento Ordena chamando o procedimento Troca, todos com parâmetros por referência.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Modularização — Lista 4: biblioteca matemática',
      'descricao' => 'Funções reaproveitáveis para cálculos clássicos.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função que receba um número inteiro N (N ≥ 0) e retorne o seu fatorial, e um algoritmo que exiba os fatoriais de 0 a 10.',
          'referencia' => <<<'TXT'
Algoritmo TabelaDeFatoriais
VAR
    i: INTEIRO;

FUNCAO Fatorial(n: INTEIRO): INTEIRO
VAR
    k, resultado: INTEIRO;
INICIO
    resultado <- 1;
    PARA k DE 2 ATE n FACA
        resultado <- resultado * k;
    FIMPARA
    RETORNE resultado;
FIMFUNCAO

INICIO
    PARA i DE 0 ATE 10 FACA
        ESCREVA(i, "! = ", Fatorial(i));
    FIMPARA
FIM.
Conceitos exigidos: função com variáveis locais e laço interno, chamada repetidamente no algoritmo principal.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função que informe se um número inteiro é primo e use-a para exibir todos os primos entre 1 e 50.',
          'referencia' => 'Conceitos esperados: função lógica que procura divisores do número, e laço no algoritmo principal exibindo os números para os quais a função retorna verdadeiro.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função que retorne o MDC de dois números inteiros e uma função que retorne o MMC usando a fórmula MMC(a, b) = a × b / MDC(a, b). Você deve usar a função MDC dentro da função MMC.',
          'referencia' => <<<'TXT'
Algoritmo MdcEMmc
VAR
    a, b: INTEIRO;

FUNCAO Mdc(x, y: INTEIRO): INTEIRO
VAR
    resto: INTEIRO;
INICIO
    ENQUANTO y <> 0 FACA
        resto <- x MOD y;
        x <- y;
        y <- resto;
    FIMENQUANTO
    RETORNE x;
FIMFUNCAO

FUNCAO Mmc(x, y: INTEIRO): INTEIRO
INICIO
    RETORNE x * y DIV Mdc(x, y);
FIMFUNCAO

INICIO
    LEIA(a, b);
    ESCREVA("MDC: ", Mdc(a, b), " MMC: ", Mmc(a, b));
FIM.
Exigido: a função Mmc chamando a função Mdc, sem repetir o algoritmo de Euclides.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva uma função que receba o raio de um círculo e retorne a sua área, e um algoritmo que leia 3 raios e exiba a área de cada círculo.',
          'referencia' => <<<'TXT'
Algoritmo AreasDeCirculos
VAR
    i: INTEIRO;
    r: REAL;

FUNCAO AreaCirculo(raio: REAL): REAL
INICIO
    RETORNE 3.14159 * raio * raio;
FIMFUNCAO

INICIO
    PARA i DE 1 ATE 3 FACA
        LEIA(r);
        ESCREVA("Área: ", AreaCirculo(r));
    FIMPARA
FIM.
Conceitos exigidos: função com parâmetro REAL retornando PI × r², usada dentro do laço.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva uma função que receba um número inteiro e retorne a quantidade de dígitos dele, e use-a em um algoritmo que leia um número e exiba o resultado.',
          'referencia' => 'Conceitos esperados: função com laço de divisões inteiras por 10 contando os dígitos, retornando a contagem para o algoritmo principal.',
        ],
      ],
    ],
    [
      'titulo' => 'Modularização — Lista 5: recursão',
      'descricao' => 'Funções que chamam a si mesmas, com caso base.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função recursiva que retorne a soma dos números inteiros de 1 até N.',
          'referencia' => 'Conceitos esperados: caso base (N = 1 ou N = 0) e chamada recursiva somando N ao resultado da função para N − 1.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função recursiva que retorne o fatorial de um número inteiro N, e um algoritmo que leia N e exiba o resultado.',
          'referencia' => <<<'TXT'
Algoritmo FatorialRecursivo
VAR
    n: INTEIRO;

FUNCAO Fat(x: INTEIRO): INTEIRO
INICIO
    SE x <= 1 ENTAO
        RETORNE 1;
    SENAO
        RETORNE x * Fat(x - 1);
    FIMSE
FIMFUNCAO

INICIO
    LEIA(n);
    ESCREVA(n, "! = ", Fat(n));
FIM.
Conceitos exigidos: caso base x <= 1 retornando 1 e chamada recursiva com x − 1.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função recursiva que retorne o N-ésimo termo da sequência de Fibonacci, com Fib(1) = Fib(2) = 1.',
          'referencia' => 'Conceitos esperados: caso base para N = 1 e N = 2 e retorno de Fib(N − 1) + Fib(N − 2) nos demais casos.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva uma função que calcule B elevado a E (E ≥ 0) e um algoritmo que a use. Você deve usar recursão, com o caso base E = 0, sem nenhum laço.',
          'referencia' => <<<'TXT'
Algoritmo PotenciaRecursiva
VAR
    b, e: INTEIRO;

FUNCAO Potencia(base, expoente: INTEIRO): INTEIRO
INICIO
    SE expoente = 0 ENTAO
        RETORNE 1;
    SENAO
        RETORNE base * Potencia(base, expoente - 1);
    FIMSE
FIMFUNCAO

INICIO
    LEIA(b, e);
    ESCREVA(b, "^", e, " = ", Potencia(b, e));
FIM.
Exigido: função recursiva com caso base expoente = 0, sem PARA, ENQUANTO ou REPITA.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva uma função que retorne a soma dos dígitos de um número inteiro positivo. Obrigatoriamente, a função deve ser recursiva, somando o último dígito ao resultado da chamada para o número sem esse dígito.',
          'referencia' => <<<'TXT'
Algoritmo SomaDigitosRecursiva
VAR
    n: INTEIRO;

FUNCAO SomaDigitos(x: INTEIRO): INTEIRO
INICIO
    SE x < 10 ENTAO
        RETORNE x;
    SENAO
        RETORNE x MOD 10 + SomaDigitos(x DIV 10);
    FIMSE
FIMFUNCAO

INICIO
    LEIA(n);
    ESCREVA("Soma dos dígitos: ", SomaDigitos(n));
FIM.
Exigido: recursão com caso base para um único dígito e chamada com x DIV 10 somada ao último dígito (x MOD 10).
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Modularização — Lista 6: funções com vetores',
      'descricao' => 'Sub-rotinas que recebem e processam vetores.',
      'questoes' => [
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função que receba um vetor de 10 notas e retorne a média, e um algoritmo que leia as notas e exiba a média. Você deve usar o vetor como parâmetro da função, sem variáveis globais.',
          'referencia' => <<<'TXT'
Algoritmo MediaDoVetor
VAR
    notas: VETOR[1..10] DE REAL;
    i: INTEIRO;

FUNCAO Media(v: VETOR[1..10] DE REAL): REAL
VAR
    k: INTEIRO;
    soma: REAL;
INICIO
    soma <- 0;
    PARA k DE 1 ATE 10 FACA
        soma <- soma + v[k];
    FIMPARA
    RETORNE soma / 10;
FIMFUNCAO

INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(notas[i]);
    FIMPARA
    ESCREVA("Média: ", Media(notas));
FIM.
Exigido: o vetor passado como parâmetro e variáveis locais na função; acessar o vetor global dentro da função não atende ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um procedimento que receba um vetor de 8 inteiros e exiba seus elementos separados por vírgula.',
          'referencia' => 'Conceitos esperados: procedimento com o vetor como parâmetro, laço interno de exibição e o separador entre os elementos.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função que receba um vetor de 10 inteiros e retorne a posição do maior elemento, e um algoritmo que exiba o maior valor usando essa posição.',
          'referencia' => <<<'TXT'
Algoritmo PosicaoDoMaiorComFuncao
VAR
    v: VETOR[1..10] DE INTEIRO;
    i, p: INTEIRO;

FUNCAO PosMaior(x: VETOR[1..10] DE INTEIRO): INTEIRO
VAR
    k, pos: INTEIRO;
INICIO
    pos <- 1;
    PARA k DE 2 ATE 10 FACA
        SE x[k] > x[pos] ENTAO
            pos <- k;
        FIMSE
    FIMPARA
    RETORNE pos;
FIMFUNCAO

INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
    FIMPARA
    p <- PosMaior(v);
    ESCREVA("Maior: ", v[p], " na posição ", p);
FIM.
Conceitos exigidos: função que retorna um índice e o uso desse índice no algoritmo principal para acessar o vetor.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função que receba um vetor de 15 inteiros e um valor X e retorne quantas vezes X aparece no vetor.',
          'referencia' => 'Conceitos esperados: função com o vetor e o valor como parâmetros, contador local e retorno da contagem.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Escreva um procedimento que receba um vetor de 10 inteiros por referência e o ordene em ordem crescente, e um algoritmo que leia o vetor, chame o procedimento e exiba o resultado.',
          'referencia' => <<<'TXT'
Algoritmo OrdenaComProcedimento
VAR
    v: VETOR[1..10] DE INTEIRO;
    i: INTEIRO;

PROCEDIMENTO Ordena(VAR x: VETOR[1..10] DE INTEIRO)
VAR
    a, b, aux: INTEIRO;
INICIO
    PARA a DE 1 ATE 9 FACA
        PARA b DE 1 ATE 10 - a FACA
            SE x[b] > x[b + 1] ENTAO
                aux <- x[b];
                x[b] <- x[b + 1];
                x[b + 1] <- aux;
            FIMSE
        FIMPARA
    FIMPARA
FIMPROCEDIMENTO

INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
    FIMPARA
    Ordena(v);
    PARA i DE 1 ATE 10 FACA
        ESCREVA(v[i]);
    FIMPARA
FIM.
Conceitos exigidos: vetor passado por referência para que a ordenação feita no procedimento valha no algoritmo principal.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Modularização — Lista 7: validação e entrada de dados',
      'descricao' => 'Sub-rotinas que garantem dados válidos.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva uma função que leia e retorne uma nota válida (entre 0 e 10), repetindo a leitura enquanto ela estiver fora do intervalo.',
          'referencia' => 'Conceitos esperados: laço de leitura dentro da função até a nota estar entre 0 e 10 e retorno do valor válido.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função que receba um caractere e retorne VERDADEIRO se ele for uma vogal, maiúscula ou minúscula, e use-a para contar as vogais de 10 caracteres lidos.',
          'referencia' => 'Conceitos esperados: função lógica que compara o caractere com as vogais nas duas caixas e contador no algoritmo principal incrementado quando a função retorna verdadeiro.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função que receba dia, mês e ano e retorne VERDADEIRO se a data for válida, considerando anos bissextos, e um algoritmo que leia uma data e informe o resultado.',
          'referencia' => <<<'TXT'
Algoritmo ValidaDataComFuncao
VAR
    d, m, a: INTEIRO;

FUNCAO Bissexto(ano: INTEIRO): LOGICO
INICIO
    RETORNE ((ano MOD 4 = 0) E (ano MOD 100 <> 0)) OU (ano MOD 400 = 0);
FIMFUNCAO

FUNCAO DataValida(dia, mes, ano: INTEIRO): LOGICO
VAR
    limite: INTEIRO;
INICIO
    SE (mes < 1) OU (mes > 12) ENTAO
        RETORNE FALSO;
    FIMSE
    ESCOLHA mes
        CASO 4, 6, 9, 11: limite <- 30;
        CASO 2:
            SE Bissexto(ano) ENTAO
                limite <- 29;
            SENAO
                limite <- 28;
            FIMSE
        OUTROCASO: limite <- 31;
    FIMESCOLHA
    RETORNE (dia >= 1) E (dia <= limite);
FIMFUNCAO

INICIO
    LEIA(d, m, a);
    SE DataValida(d, m, a) ENTAO
        ESCREVA("Data válida");
    SENAO
        ESCREVA("Data inválida");
    FIMSE
FIM.
Conceitos exigidos: separar a regra do ano bissexto em uma função e usá-la na validação da data.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função que leia e retorne a idade de uma pessoa, aceitando apenas valores de 0 a 120, e um algoritmo que leia a idade de 5 pessoas e exiba a média. Você deve usar a função de leitura validada para todas as idades.',
          'referencia' => <<<'TXT'
Algoritmo MediaDeIdadesValidas
VAR
    i, soma: INTEIRO;

FUNCAO LeIdade(): INTEIRO
VAR
    idade: INTEIRO;
INICIO
    REPITA
        LEIA(idade);
    ATE (idade >= 0) E (idade <= 120)
    RETORNE idade;
FIMFUNCAO

INICIO
    soma <- 0;
    PARA i DE 1 ATE 5 FACA
        soma <- soma + LeIdade();
    FIMPARA
    ESCREVA("Média: ", soma / 5);
FIM.
Exigido: toda leitura de idade feita pela função LeIdade, que repete até receber um valor válido.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva uma função que receba um número inteiro e retorne o seu valor absoluto (sem sinal), e um algoritmo que leia um número e exiba o resultado.',
          'referencia' => <<<'TXT'
Algoritmo ValorAbsoluto
VAR
    numero: INTEIRO;

FUNCAO Absoluto(x: INTEIRO): INTEIRO
INICIO
    SE x < 0 ENTAO
        RETORNE -x;
    SENAO
        RETORNE x;
    FIMSE
FIMFUNCAO

INICIO
    LEIA(numero);
    ESCREVA("Valor absoluto: ", Absoluto(numero));
FIM.
Conceitos exigidos: função que inverte o sinal dos negativos e mantém os demais.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Modularização — Lista 8: escopo de variáveis',
      'descricao' => 'Variáveis locais, globais e parâmetros.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo com uma variável global contador e um procedimento que a incremente; chame o procedimento 5 vezes e exiba o valor final do contador.',
          'referencia' => <<<'TXT'
Algoritmo ContadorGlobal
VAR
    contador, i: INTEIRO;

PROCEDIMENTO Incrementa()
INICIO
    contador <- contador + 1;
FIMPROCEDIMENTO

INICIO
    contador <- 0;
    PARA i DE 1 ATE 5 FACA
        Incrementa();
    FIMPARA
    ESCREVA("Contador: ", contador);
FIM.
Conceitos exigidos: variável global declarada no algoritmo principal e alterada dentro do procedimento.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva o mesmo contador da questão anterior, agora sem variável global. Você deve usar um procedimento que receba o contador por referência.',
          'referencia' => <<<'TXT'
Algoritmo ContadorPorReferencia
VAR
    contador, i: INTEIRO;

PROCEDIMENTO Incrementa(VAR c: INTEIRO)
INICIO
    c <- c + 1;
FIMPROCEDIMENTO

INICIO
    contador <- 0;
    PARA i DE 1 ATE 5 FACA
        Incrementa(contador);
    FIMPARA
    ESCREVA("Contador: ", contador);
FIM.
Exigido: o procedimento altera apenas o seu parâmetro por referência, sem acessar variáveis globais.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Explique o que acontece quando uma função declara uma variável local com o mesmo nome de uma variável do algoritmo principal, mostrando um exemplo curto.',
          'referencia' => 'Conceitos esperados: a variável local "esconde" a do algoritmo principal dentro da função; alterá-la não muda a variável externa; exemplo exibindo os dois valores.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função que receba um número e retorne o seu quadrado, declarando o resultado em uma variável local, e explique por que essa variável não pode ser usada no algoritmo principal.',
          'referencia' => 'Conceitos esperados: função com variável local para o resultado e retorno dela; explicação de que variáveis locais só existem durante a execução da função.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que calcule o salário líquido de um funcionário a partir do salário bruto, com descontos de 11% (previdência) e 7,5% (imposto). Obrigatoriamente, cada desconto deve ser calculado por uma função própria e o algoritmo principal não deve ter variáveis globais além do salário bruto.',
          'referencia' => <<<'TXT'
Algoritmo SalarioLiquidoModular
VAR
    bruto: REAL;

FUNCAO Previdencia(s: REAL): REAL
INICIO
    RETORNE s * 0.11;
FIMFUNCAO

FUNCAO Imposto(s: REAL): REAL
INICIO
    RETORNE s * 0.075;
FIMFUNCAO

INICIO
    LEIA(bruto);
    ESCREVA("Líquido: ", bruto - Previdencia(bruto) - Imposto(bruto));
FIM.
Exigido: uma função por desconto, o salário bruto como única variável do algoritmo principal e o líquido calculado com os retornos das funções.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Modularização — Lista 9: decomposição de problemas',
      'descricao' => 'Dividir um problema maior em sub-rotinas.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Um sistema de notas precisa ler as notas de 5 alunos, calcular a média de cada um e exibir a situação. Proponha uma divisão em sub-rotinas (funções e procedimentos) e escreva o algoritmo.',
          'referencia' => 'Conceitos esperados: ao menos uma sub-rotina para ler ou validar notas, uma para calcular a média e uma para exibir a situação, com o algoritmo principal apenas coordenando as chamadas.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo de caixa de supermercado que leia o preço e a quantidade de itens até que seja digitado preço 0 e exiba o total, usando uma função para o subtotal de cada item e um procedimento para exibir o cupom final.',
          'referencia' => <<<'TXT'
Algoritmo CaixaSupermercado
VAR
    preco, total: REAL;
    quantidade, itens: INTEIRO;

FUNCAO Subtotal(p: REAL; q: INTEIRO): REAL
INICIO
    RETORNE p * q;
FIMFUNCAO

PROCEDIMENTO Cupom(t: REAL; n: INTEIRO)
INICIO
    ESCREVA("Itens: ", n);
    ESCREVA("Total: R$ ", t);
FIMPROCEDIMENTO

INICIO
    total <- 0;
    itens <- 0;
    LEIA(preco);
    ENQUANTO preco <> 0 FACA
        LEIA(quantidade);
        total <- total + Subtotal(preco, quantidade);
        itens <- itens + quantidade;
        LEIA(preco);
    FIMENQUANTO
    Cupom(total, itens);
FIM.
Conceitos exigidos: função para o subtotal, procedimento para a saída e o laço de leitura no algoritmo principal.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e exiba a média, o maior e o menor valor. Você deve usar três funções separadas (Media, Maior e Menor), cada uma recebendo o vetor como parâmetro.',
          'referencia' => <<<'TXT'
Algoritmo EstatisticasDoVetor
VAR
    v: VETOR[1..10] DE REAL;
    i: INTEIRO;

FUNCAO Media(x: VETOR[1..10] DE REAL): REAL
VAR
    k: INTEIRO;
    s: REAL;
INICIO
    s <- 0;
    PARA k DE 1 ATE 10 FACA
        s <- s + x[k];
    FIMPARA
    RETORNE s / 10;
FIMFUNCAO

FUNCAO Maior(x: VETOR[1..10] DE REAL): REAL
VAR
    k: INTEIRO;
    m: REAL;
INICIO
    m <- x[1];
    PARA k DE 2 ATE 10 FACA
        SE x[k] > m ENTAO
            m <- x[k];
        FIMSE
    FIMPARA
    RETORNE m;
FIMFUNCAO

FUNCAO Menor(x: VETOR[1..10] DE REAL): REAL
VAR
    k: INTEIRO;
    m: REAL;
INICIO
    m <- x[1];
    PARA k DE 2 ATE 10 FACA
        SE x[k] < m ENTAO
            m <- x[k];
        FIMSE
    FIMPARA
    RETORNE m;
FIMFUNCAO

INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
    FIMPARA
    ESCREVA("Média: ", Media(v), " Maior: ", Maior(v), " Menor: ", Menor(v));
FIM.
Exigido: três funções independentes recebendo o vetor; calcular tudo em um único laço do algoritmo principal não atende ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que converta valores entre Celsius e Fahrenheit conforme a opção do usuário (1 = C para F, 2 = F para C), usando uma função para cada conversão.',
          'referencia' => <<<'TXT'
Algoritmo ConversorDeTemperatura
VAR
    opcao: INTEIRO;
    t: REAL;

FUNCAO CParaF(c: REAL): REAL
INICIO
    RETORNE c * 9 / 5 + 32;
FIMFUNCAO

FUNCAO FParaC(f: REAL): REAL
INICIO
    RETORNE (f - 32) * 5 / 9;
FIMFUNCAO

INICIO
    LEIA(opcao, t);
    SE opcao = 1 ENTAO
        ESCREVA(CParaF(t), " °F");
    SENAO
        SE opcao = 2 ENTAO
            ESCREVA(FParaC(t), " °C");
        SENAO
            ESCREVA("Opção inválida");
        FIMSE
    FIMSE
FIM.
Conceitos exigidos: uma função para cada sentido da conversão e a escolha da função conforme a opção.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva uma função que receba o peso e a altura de uma pessoa e retorne o IMC, e um procedimento que receba o IMC e exiba a classificação.',
          'referencia' => 'Conceitos esperados: função que calcula peso / altura² e procedimento separado com as faixas de classificação, chamados em sequência no algoritmo principal.',
        ],
      ],
    ],
    [
      'titulo' => 'Modularização — Lista 10: problemas integradores',
      'descricao' => 'Programas organizados em várias sub-rotinas cooperando.',
      'questoes' => [
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo de cadastro de 5 produtos (nome e preço em vetores) com um menu: 1 - Cadastrar, 2 - Listar, 3 - Exibir o mais caro, 0 - Sair. Você deve usar um procedimento para cada opção do menu, recebendo os vetores como parâmetros.',
          'referencia' => <<<'TXT'
Algoritmo CadastroDeProdutos
VAR
    nomes: VETOR[1..5] DE CARACTERE;
    precos: VETOR[1..5] DE REAL;
    opcao: INTEIRO;

PROCEDIMENTO Cadastrar(VAR n: VETOR[1..5] DE CARACTERE; VAR p: VETOR[1..5] DE REAL)
VAR
    i: INTEIRO;
INICIO
    PARA i DE 1 ATE 5 FACA
        LEIA(n[i], p[i]);
    FIMPARA
FIMPROCEDIMENTO

PROCEDIMENTO Listar(n: VETOR[1..5] DE CARACTERE; p: VETOR[1..5] DE REAL)
VAR
    i: INTEIRO;
INICIO
    PARA i DE 1 ATE 5 FACA
        ESCREVA(n[i], " - R$ ", p[i]);
    FIMPARA
FIMPROCEDIMENTO

PROCEDIMENTO MaisCaro(n: VETOR[1..5] DE CARACTERE; p: VETOR[1..5] DE REAL)
VAR
    i, pos: INTEIRO;
INICIO
    pos <- 1;
    PARA i DE 2 ATE 5 FACA
        SE p[i] > p[pos] ENTAO
            pos <- i;
        FIMSE
    FIMPARA
    ESCREVA("Mais caro: ", n[pos]);
FIMPROCEDIMENTO

INICIO
    REPITA
        LEIA(opcao);
        ESCOLHA opcao
            CASO 1: Cadastrar(nomes, precos);
            CASO 2: Listar(nomes, precos);
            CASO 3: MaisCaro(nomes, precos);
        FIMESCOLHA
    ATE opcao = 0
FIM.
Exigido: um procedimento por opção; o cadastro recebe os vetores por referência, e a listagem e a busca os recebem por valor.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma frase de até 50 caracteres e exiba quantas vogais, consoantes e espaços ela tem, usando uma função para classificar cada caractere.',
          'referencia' => 'Conceitos esperados: percorrer a frase caractere por caractere e chamar uma função que identifique se é vogal, consoante ou espaço, com contadores no algoritmo principal.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva uma função que receba um número inteiro e retorne VERDADEIRO se ele for um número perfeito, e use-a para exibir todos os números perfeitos entre 1 e 1000.',
          'referencia' => <<<'TXT'
Algoritmo NumerosPerfeitos
VAR
    i: INTEIRO;

FUNCAO Perfeito(n: INTEIRO): LOGICO
VAR
    d, soma: INTEIRO;
INICIO
    soma <- 0;
    PARA d DE 1 ATE n - 1 FACA
        SE n MOD d = 0 ENTAO
            soma <- soma + d;
        FIMSE
    FIMPARA
    RETORNE (n > 1) E (soma = n);
FIMFUNCAO

INICIO
    PARA i DE 1 ATE 1000 FACA
        SE Perfeito(i) ENTAO
            ESCREVA(i);
        FIMSE
    FIMPARA
FIM.
Conceitos exigidos: função lógica que soma os divisores próprios e o laço principal exibindo os números para os quais ela retorna verdadeiro.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia uma matriz 3 × 3 e exiba o seu determinante. Você deve usar uma função que receba a matriz como parâmetro e aplique a regra de Sarrus.',
          'referencia' => <<<'TXT'
Algoritmo DeterminanteSarrus
VAR
    m: MATRIZ[1..3, 1..3] DE REAL;
    i, j: INTEIRO;

FUNCAO Determinante(a: MATRIZ[1..3, 1..3] DE REAL): REAL
INICIO
    RETORNE a[1, 1] * a[2, 2] * a[3, 3] + a[1, 2] * a[2, 3] * a[3, 1] + a[1, 3] * a[2, 1] * a[3, 2]
          - a[1, 3] * a[2, 2] * a[3, 1] - a[1, 1] * a[2, 3] * a[3, 2] - a[1, 2] * a[2, 1] * a[3, 3];
FIMFUNCAO

INICIO
    PARA i DE 1 ATE 3 FACA
        PARA j DE 1 ATE 3 FACA
            LEIA(m[i, j]);
        FIMPARA
    FIMPARA
    ESCREVA("Determinante: ", Determinante(m));
FIM.
Exigido: função que recebe a matriz e devolve o determinante pela regra de Sarrus (três produtos somados e três subtraídos).
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva uma função que receba o valor de uma compra e retorne o valor com desconto de 5% para compras acima de R$ 200,00, e use-a em um algoritmo que leia o valor de 3 compras.',
          'referencia' => 'Conceitos esperados: função com decisão interna aplicando o desconto só acima de 200 e chamada para cada uma das três compras lidas.',
        ],
      ],
    ],
  ],
];
