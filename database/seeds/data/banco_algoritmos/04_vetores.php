<?php

declare(strict_types=1);

return [
  'tema' => 'Vetores',
  'exercicios' => [
    [
      'titulo' => 'Vetores — Lista 1: leitura, exibição e acesso por índice',
      'descricao' => 'Declaração de vetores, preenchimento e percurso.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números inteiros em um vetor e depois exiba todos eles.',
          'referencia' => 'Conceitos esperados: declarar um vetor de 10 posições, preenchê-lo em um laço e percorrê-lo em outro laço para exibir os valores.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e os exiba na ordem inversa da leitura.',
          'referencia' => <<<'TXT'
Algoritmo ExibeInverso
VAR
    v: VETOR[1..10] DE INTEIRO;
    i: INTEIRO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
    FIMPARA
    PARA i DE 10 ATE 1 PASSO -1 FACA
        ESCREVA(v[i]);
    FIMPARA
FIM.
Conceitos exigidos: vetor de 10 posições, leitura de 1 a 10 e percurso de 10 a 1 na exibição.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e inverta a ordem dos elementos no próprio vetor, exibindo-o depois. Você deve usar trocas entre as posições i e 11 − i, sem um segundo vetor.',
          'referencia' => <<<'TXT'
Algoritmo InverteNoProprioVetor
VAR
    v: VETOR[1..10] DE INTEIRO;
    i, aux: INTEIRO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
    FIMPARA
    PARA i DE 1 ATE 5 FACA
        aux <- v[i];
        v[i] <- v[11 - i];
        v[11 - i] <- aux;
    FIMPARA
    PARA i DE 1 ATE 10 FACA
        ESCREVA(v[i]);
    FIMPARA
FIM.
Exigido: percorrer só até a metade (5) trocando v[i] com v[11 − i] por variável auxiliar; usar um vetor extra não atende ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que preencha um vetor de 20 posições com os quadrados dos números de 1 a 20 e exiba o vetor.',
          'referencia' => 'Conceitos esperados: laço que atribui i × i à posição i do vetor e exibição dos valores guardados.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 8 números em um vetor e exiba apenas os elementos que estão nas posições pares (2, 4, 6 e 8).',
          'referencia' => <<<'TXT'
Algoritmo PosicoesPares
VAR
    v: VETOR[1..8] DE INTEIRO;
    i: INTEIRO;
INICIO
    PARA i DE 1 ATE 8 FACA
        LEIA(v[i]);
    FIMPARA
    PARA i DE 2 ATE 8 PASSO 2 FACA
        ESCREVA(v[i]);
    FIMPARA
FIM.
Conceitos exigidos: diferenciar a posição (índice) do valor e exibir só os índices pares.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Vetores — Lista 2: soma, média e contagens',
      'descricao' => 'Acumulação e contagem sobre os elementos de um vetor.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia as notas de 10 alunos em um vetor, calcule a média e exiba quantos alunos ficaram acima da média.',
          'referencia' => <<<'TXT'
Algoritmo AcimaDaMedia
VAR
    notas: VETOR[1..10] DE REAL;
    i, acima: INTEIRO;
    soma, media: REAL;
INICIO
    soma <- 0;
    PARA i DE 1 ATE 10 FACA
        LEIA(notas[i]);
        soma <- soma + notas[i];
    FIMPARA
    media <- soma / 10;
    acima <- 0;
    PARA i DE 1 ATE 10 FACA
        SE notas[i] > media ENTAO
            acima <- acima + 1;
        FIMSE
    FIMPARA
    ESCREVA("Média: ", media, " Acima da média: ", acima);
FIM.
Conceitos exigidos: guardar as notas no vetor para compará-las com a média depois que ela é calculada (dois percursos).
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia 15 números inteiros em um vetor e exiba a soma de todos os elementos.',
          'referencia' => 'Conceitos esperados: preencher o vetor e acumular a soma dos elementos em um percurso.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 20 números inteiros em um vetor e exiba quantos são pares e quantos são ímpares.',
          'referencia' => 'Conceitos esperados: percorrer o vetor testando o resto da divisão por 2 e manter dois contadores.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 12 temperaturas mensais em um vetor e exiba a média anual e os meses (1 a 12) com temperatura acima da média. Você deve usar dois laços separados: um para ler e somar, outro para comparar com a média.',
          'referencia' => <<<'TXT'
Algoritmo MesesAcimaDaMedia
VAR
    t: VETOR[1..12] DE REAL;
    mes: INTEIRO;
    soma, media: REAL;
INICIO
    soma <- 0;
    PARA mes DE 1 ATE 12 FACA
        LEIA(t[mes]);
        soma <- soma + t[mes];
    FIMPARA
    media <- soma / 12;
    ESCREVA("Média anual: ", media);
    PARA mes DE 1 ATE 12 FACA
        SE t[mes] > media ENTAO
            ESCREVA("Mês ", mes);
        FIMSE
    FIMPARA
FIM.
Exigido: dois laços separados, o segundo só depois do cálculo da média.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 valores em um vetor e exiba o percentual de valores negativos.',
          'referencia' => <<<'TXT'
Algoritmo PercentualNegativos
VAR
    v: VETOR[1..10] DE REAL;
    i, negativos: INTEIRO;
INICIO
    negativos <- 0;
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
        SE v[i] < 0 ENTAO
            negativos <- negativos + 1;
        FIMSE
    FIMPARA
    ESCREVA("Negativos: ", negativos * 100 / 10, "%");
FIM.
Conceitos exigidos: contar os negativos e calcular a proporção em relação ao tamanho do vetor.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Vetores — Lista 3: maior, menor e posições',
      'descricao' => 'Extremos de um vetor e onde eles aparecem.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e exiba o maior elemento.',
          'referencia' => 'Conceitos esperados: iniciar o maior com o primeiro elemento e percorrer os demais atualizando-o.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e exiba o menor elemento e a posição em que ele está. Você deve usar uma variável para a posição do menor e comparar sempre com v[posicao].',
          'referencia' => <<<'TXT'
Algoritmo PosicaoDoMenor
VAR
    v: VETOR[1..10] DE INTEIRO;
    i, posicao: INTEIRO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
    FIMPARA
    posicao <- 1;
    PARA i DE 2 ATE 10 FACA
        SE v[i] < v[posicao] ENTAO
            posicao <- i;
        FIMSE
    FIMPARA
    ESCREVA("Menor: ", v[posicao], " na posição ", posicao);
FIM.
Exigido: guardar só a posição do menor e comparar cada elemento com v[posicao].
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e exiba a diferença entre o maior e o menor elemento (amplitude).',
          'referencia' => <<<'TXT'
Algoritmo Amplitude
VAR
    v: VETOR[1..10] DE REAL;
    i: INTEIRO;
    maior, menor: REAL;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
    FIMPARA
    maior <- v[1];
    menor <- v[1];
    PARA i DE 2 ATE 10 FACA
        SE v[i] > maior ENTAO
            maior <- v[i];
        FIMSE
        SE v[i] < menor ENTAO
            menor <- v[i];
        FIMSE
    FIMPARA
    ESCREVA("Amplitude: ", maior - menor);
FIM.
Conceitos exigidos: encontrar maior e menor no mesmo percurso e exibir a diferença.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números distintos em um vetor e exiba o segundo maior elemento.',
          'referencia' => 'Conceitos esperados: manter o maior e o segundo maior durante o percurso, atualizando o segundo quando o maior muda ou quando um valor fica entre os dois.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia 15 números em um vetor e exiba todas as posições em que aparece o maior valor (ele pode se repetir). Obrigatoriamente, a solução deve ter um laço para achar o maior e outro para listar as posições.',
          'referencia' => <<<'TXT'
Algoritmo PosicoesDoMaior
VAR
    v: VETOR[1..15] DE INTEIRO;
    i, maior: INTEIRO;
INICIO
    PARA i DE 1 ATE 15 FACA
        LEIA(v[i]);
    FIMPARA
    maior <- v[1];
    PARA i DE 2 ATE 15 FACA
        SE v[i] > maior ENTAO
            maior <- v[i];
        FIMSE
    FIMPARA
    PARA i DE 1 ATE 15 FACA
        SE v[i] = maior ENTAO
            ESCREVA("Posição ", i);
        FIMSE
    FIMPARA
FIM.
Exigido: um laço para descobrir o maior e um segundo laço para listar todas as posições com esse valor.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Vetores — Lista 4: busca em vetores',
      'descricao' => 'Busca sequencial e binária, ocorrências e existência.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e depois um valor X, informando se X está no vetor e em qual posição aparece pela primeira vez.',
          'referencia' => <<<'TXT'
Algoritmo BuscaSequencial
VAR
    v: VETOR[1..10] DE INTEIRO;
    i, x, posicao: INTEIRO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
    FIMPARA
    LEIA(x);
    posicao <- 0;
    i <- 1;
    ENQUANTO (i <= 10) E (posicao = 0) FACA
        SE v[i] = x ENTAO
            posicao <- i;
        FIMSE
        i <- i + 1;
    FIMENQUANTO
    SE posicao > 0 ENTAO
        ESCREVA("Encontrado na posição ", posicao);
    SENAO
        ESCREVA("Não encontrado");
    FIMSE
FIM.
Conceitos exigidos: percorrer o vetor comparando com X, parar na primeira ocorrência e tratar o caso de não encontrar.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia 20 números em um vetor e depois um valor X e exiba quantas vezes X aparece no vetor.',
          'referencia' => 'Conceitos esperados: percorrer todo o vetor e contar as posições iguais a X.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia 16 números em ordem crescente em um vetor e depois um valor X, informando a posição de X ou que ele não existe. Você deve usar busca binária, dividindo o intervalo de busca ao meio a cada passo.',
          'referencia' => <<<'TXT'
Algoritmo BuscaBinaria
VAR
    v: VETOR[1..16] DE INTEIRO;
    i, x, inicio, fim, meio, posicao: INTEIRO;
INICIO
    PARA i DE 1 ATE 16 FACA
        LEIA(v[i]);
    FIMPARA
    LEIA(x);
    inicio <- 1;
    fim <- 16;
    posicao <- 0;
    ENQUANTO (inicio <= fim) E (posicao = 0) FACA
        meio <- (inicio + fim) DIV 2;
        SE v[meio] = x ENTAO
            posicao <- meio;
        SENAO
            SE x < v[meio] ENTAO
                fim <- meio - 1;
            SENAO
                inicio <- meio + 1;
            FIMSE
        FIMSE
    FIMENQUANTO
    SE posicao > 0 ENTAO
        ESCREVA("Posição: ", posicao);
    SENAO
        ESCREVA("Não existe");
    FIMSE
FIM.
Exigido: busca binária com início, fim e meio ajustados a cada passo; a busca sequencial não atende ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o nome de 10 alunos em um vetor e depois um nome a pesquisar, informando se o aluno está matriculado.',
          'referencia' => 'Conceitos esperados: vetor de CARACTERE, comparação do nome pesquisado com cada posição e mensagem conforme o resultado.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e informe se existe algum elemento repetido.',
          'referencia' => <<<'TXT'
Algoritmo ExisteRepetido
VAR
    v: VETOR[1..10] DE INTEIRO;
    i, j: INTEIRO;
    repetido: LOGICO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
    FIMPARA
    repetido <- FALSO;
    PARA i DE 1 ATE 9 FACA
        PARA j DE i + 1 ATE 10 FACA
            SE v[i] = v[j] ENTAO
                repetido <- VERDADEIRO;
            FIMSE
        FIMPARA
    FIMPARA
    SE repetido ENTAO
        ESCREVA("Há elementos repetidos");
    SENAO
        ESCREVA("Todos os elementos são distintos");
    FIMSE
FIM.
Conceitos exigidos: comparar cada par de posições distintas (j começando em i + 1) e usar uma variável lógica para o resultado.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Vetores — Lista 5: ordenação',
      'descricao' => 'Métodos clássicos de ordenação de vetores.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e os exiba em ordem crescente.',
          'referencia' => 'Conceitos esperados: um método de ordenação (bolha, seleção ou inserção) que compare e troque elementos até o vetor ficar em ordem, e a exibição do vetor ordenado.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e os ordene em ordem decrescente pelo método da bolha, exibindo o vetor ordenado.',
          'referencia' => <<<'TXT'
Algoritmo BolhaDecrescente
VAR
    v: VETOR[1..10] DE INTEIRO;
    i, j, aux: INTEIRO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
    FIMPARA
    PARA i DE 1 ATE 9 FACA
        PARA j DE 1 ATE 10 - i FACA
            SE v[j] < v[j + 1] ENTAO
                aux <- v[j];
                v[j] <- v[j + 1];
                v[j + 1] <- aux;
            FIMSE
        FIMPARA
    FIMPARA
    PARA i DE 1 ATE 10 FACA
        ESCREVA(v[i]);
    FIMPARA
FIM.
Conceitos exigidos: laços aninhados comparando vizinhos, troca por variável auxiliar e comparação invertida (<) para a ordem decrescente.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia 5 números em um vetor e informe se eles foram digitados em ordem crescente.',
          'referencia' => 'Conceitos esperados: comparar cada elemento com o seguinte e concluir que está em ordem só se nenhum par estiver invertido.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e os ordene em ordem crescente. Você deve usar o método de seleção: a cada passo, encontrar o menor elemento da parte não ordenada e trocá-lo com a primeira posição dessa parte.',
          'referencia' => <<<'TXT'
Algoritmo OrdenacaoPorSelecao
VAR
    v: VETOR[1..10] DE INTEIRO;
    i, j, menor, aux: INTEIRO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
    FIMPARA
    PARA i DE 1 ATE 9 FACA
        menor <- i;
        PARA j DE i + 1 ATE 10 FACA
            SE v[j] < v[menor] ENTAO
                menor <- j;
            FIMSE
        FIMPARA
        aux <- v[i];
        v[i] <- v[menor];
        v[menor] <- aux;
    FIMPARA
    PARA i DE 1 ATE 10 FACA
        ESCREVA(v[i]);
    FIMPARA
FIM.
Exigido: método de seleção, com a posição do menor encontrada no laço interno e uma única troca por passo do laço externo; o método da bolha não atende ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 nomes em um vetor e os exiba em ordem alfabética. Você deve usar o método da bolha com uma variável lógica que interrompa as passagens quando nenhuma troca acontecer.',
          'referencia' => <<<'TXT'
Algoritmo NomesEmOrdem
VAR
    nomes: VETOR[1..10] DE CARACTERE;
    i: INTEIRO;
    aux: CARACTERE;
    trocou: LOGICO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(nomes[i]);
    FIMPARA
    REPITA
        trocou <- FALSO;
        PARA i DE 1 ATE 9 FACA
            SE nomes[i] > nomes[i + 1] ENTAO
                aux <- nomes[i];
                nomes[i] <- nomes[i + 1];
                nomes[i + 1] <- aux;
                trocou <- VERDADEIRO;
            FIMSE
        FIMPARA
    ATE NAO trocou
    PARA i DE 1 ATE 10 FACA
        ESCREVA(nomes[i]);
    FIMPARA
FIM.
Exigido: bolha com variável lógica de controle, repetindo as passagens até que não haja troca.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Vetores — Lista 6: construção de novos vetores',
      'descricao' => 'Cópias, filtros e combinação de vetores.',
      'questoes' => [
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia dois vetores A e B de 10 posições e gere um vetor C em que cada C[i] = A[i] + B[i], exibindo C. Você deve usar um único laço para gerar C.',
          'referencia' => <<<'TXT'
Algoritmo SomaDeVetores
VAR
    a, b, c: VETOR[1..10] DE INTEIRO;
    i: INTEIRO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(a[i], b[i]);
    FIMPARA
    PARA i DE 1 ATE 10 FACA
        c[i] <- a[i] + b[i];
        ESCREVA(c[i]);
    FIMPARA
FIM.
Exigido: gerar C em um único laço, somando as posições correspondentes.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor A e gere um vetor B com o dobro de cada elemento de A, exibindo B.',
          'referencia' => 'Conceitos esperados: percorrer A e atribuir 2 × A[i] a B[i], exibindo o vetor resultante.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 15 números em um vetor e copie apenas os positivos para um segundo vetor, exibindo-o com a quantidade de elementos copiados.',
          'referencia' => <<<'TXT'
Algoritmo CopiaPositivos
VAR
    v, positivos: VETOR[1..15] DE INTEIRO;
    i, n: INTEIRO;
INICIO
    PARA i DE 1 ATE 15 FACA
        LEIA(v[i]);
    FIMPARA
    n <- 0;
    PARA i DE 1 ATE 15 FACA
        SE v[i] > 0 ENTAO
            n <- n + 1;
            positivos[n] <- v[i];
        FIMSE
    FIMPARA
    PARA i DE 1 ATE n FACA
        ESCREVA(positivos[i]);
    FIMPARA
    ESCREVA("Copiados: ", n);
FIM.
Conceitos exigidos: um índice próprio (n) para o vetor de destino, incrementado só a cada cópia.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia dois vetores de 5 posições e gere um terceiro vetor de 10 posições com os elementos intercalados (A[1], B[1], A[2], B[2], ...).',
          'referencia' => 'Conceitos esperados: para cada i, gravar A[i] na posição 2i − 1 e B[i] na posição 2i do vetor resultante.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia dois vetores de 10 números inteiros e gere um vetor com a interseção entre eles (os valores que aparecem nos dois), sem repetir valores no resultado.',
          'referencia' => <<<'TXT'
Algoritmo IntersecaoVetores
VAR
    a, b, c: VETOR[1..10] DE INTEIRO;
    i, j, k, n: INTEIRO;
    existeEmB, jaNoResultado: LOGICO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(a[i]);
    FIMPARA
    PARA i DE 1 ATE 10 FACA
        LEIA(b[i]);
    FIMPARA
    n <- 0;
    PARA i DE 1 ATE 10 FACA
        existeEmB <- FALSO;
        PARA j DE 1 ATE 10 FACA
            SE a[i] = b[j] ENTAO
                existeEmB <- VERDADEIRO;
            FIMSE
        FIMPARA
        jaNoResultado <- FALSO;
        PARA k DE 1 ATE n FACA
            SE c[k] = a[i] ENTAO
                jaNoResultado <- VERDADEIRO;
            FIMSE
        FIMPARA
        SE existeEmB E NAO jaNoResultado ENTAO
            n <- n + 1;
            c[n] <- a[i];
        FIMSE
    FIMPARA
    PARA k DE 1 ATE n FACA
        ESCREVA(c[k]);
    FIMPARA
FIM.
Conceitos exigidos: verificar se cada elemento de A existe em B e se já está no resultado antes de inseri-lo.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Vetores — Lista 7: deslocamentos e remoções',
      'descricao' => 'Mover, inserir e remover elementos dentro do vetor.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e desloque todos os elementos uma posição para a direita, levando o último para a primeira posição (rotação).',
          'referencia' => 'Conceitos esperados: guardar o último elemento, deslocar do fim para o início (v[i] <- v[i − 1]) e colocar o valor guardado na primeira posição.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e troque o primeiro elemento com o último, exibindo o vetor depois da troca.',
          'referencia' => 'Conceitos esperados: troca de v[1] com v[10] por meio de uma variável auxiliar e exibição do vetor.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e uma posição P, remova o elemento da posição P deslocando os seguintes para a esquerda e exiba os 9 elementos restantes.',
          'referencia' => <<<'TXT'
Algoritmo RemovePosicao
VAR
    v: VETOR[1..10] DE INTEIRO;
    i, p: INTEIRO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
    FIMPARA
    LEIA(p);
    PARA i DE p ATE 9 FACA
        v[i] <- v[i + 1];
    FIMPARA
    PARA i DE 1 ATE 9 FACA
        ESCREVA(v[i]);
    FIMPARA
FIM.
Conceitos exigidos: deslocar da posição P em diante (v[i] <- v[i + 1]) e exibir apenas as 9 posições válidas.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia 9 números em ordem crescente em um vetor de 10 posições e insira um novo valor mantendo a ordem. Você deve usar deslocamentos a partir do fim do vetor, sem reordenar o vetor inteiro.',
          'referencia' => <<<'TXT'
Algoritmo InsereOrdenado
VAR
    v: VETOR[1..10] DE INTEIRO;
    i, x: INTEIRO;
INICIO
    PARA i DE 1 ATE 9 FACA
        LEIA(v[i]);
    FIMPARA
    LEIA(x);
    i <- 9;
    ENQUANTO (i >= 1) E (v[i] > x) FACA
        v[i + 1] <- v[i];
        i <- i - 1;
    FIMENQUANTO
    v[i + 1] <- x;
    PARA i DE 1 ATE 10 FACA
        ESCREVA(v[i]);
    FIMPARA
FIM.
Exigido: deslocar para a direita, a partir do fim, os elementos maiores que o novo valor e gravá-lo na posição livre; ordenar o vetor de novo não atende ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e gere um segundo vetor sem os elementos repetidos, mantendo a primeira ocorrência de cada valor.',
          'referencia' => <<<'TXT'
Algoritmo RemoveRepetidos
VAR
    v, unicos: VETOR[1..10] DE INTEIRO;
    i, j, n: INTEIRO;
    existe: LOGICO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
    FIMPARA
    n <- 0;
    PARA i DE 1 ATE 10 FACA
        existe <- FALSO;
        PARA j DE 1 ATE n FACA
            SE unicos[j] = v[i] ENTAO
                existe <- VERDADEIRO;
            FIMSE
        FIMPARA
        SE NAO existe ENTAO
            n <- n + 1;
            unicos[n] <- v[i];
        FIMSE
    FIMPARA
    PARA j DE 1 ATE n FACA
        ESCREVA(unicos[j]);
    FIMPARA
FIM.
Conceitos exigidos: verificar se o valor já foi copiado antes de inseri-lo no vetor de únicos, mantendo um contador próprio.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Vetores — Lista 8: frequências e histogramas',
      'descricao' => 'Vetores como contadores indexados.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 30 notas inteiras de 0 a 10 e exiba quantas vezes cada nota apareceu.',
          'referencia' => <<<'TXT'
Algoritmo FrequenciaDasNotas
VAR
    freq: VETOR[0..10] DE INTEIRO;
    i, nota: INTEIRO;
INICIO
    PARA i DE 0 ATE 10 FACA
        freq[i] <- 0;
    FIMPARA
    PARA i DE 1 ATE 30 FACA
        LEIA(nota);
        freq[nota] <- freq[nota] + 1;
    FIMPARA
    PARA i DE 0 ATE 10 FACA
        ESCREVA("Nota ", i, ": ", freq[i]);
    FIMPARA
FIM.
Conceitos exigidos: usar a própria nota como índice do vetor de contadores, iniciado em zero.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que simule 100 lançamentos de um dado lendo os resultados (1 a 6) e exiba quantas vezes cada face saiu. Você deve usar um vetor de 6 contadores indexado pela face.',
          'referencia' => <<<'TXT'
Algoritmo FacesDoDado
VAR
    faces: VETOR[1..6] DE INTEIRO;
    i, face: INTEIRO;
INICIO
    PARA i DE 1 ATE 6 FACA
        faces[i] <- 0;
    FIMPARA
    PARA i DE 1 ATE 100 FACA
        LEIA(face);
        faces[face] <- faces[face] + 1;
    FIMPARA
    PARA i DE 1 ATE 6 FACA
        ESCREVA("Face ", i, ": ", faces[i]);
    FIMPARA
FIM.
Exigido: um vetor de 6 contadores indexado pela face; seis variáveis separadas ou seis SEs não atendem ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 20 números de 1 a 5 e exiba um histograma com uma linha de asteriscos para cada valor, com tantos asteriscos quanto suas ocorrências.',
          'referencia' => 'Conceitos esperados: contar as ocorrências em um vetor indexado pelo valor e, para cada valor, exibir uma linha com o número correspondente de asteriscos.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia 12 números e exiba o valor que mais se repete.',
          'referencia' => 'Conceitos esperados: contar as ocorrências de cada elemento e guardar o valor com a maior contagem.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Uma pesquisa de satisfação recebe notas de 1 a 5 até que seja digitado 0. Escreva um algoritmo que exiba o percentual de cada nota. Você deve usar um vetor de contadores indexado pela nota e um contador geral de respostas.',
          'referencia' => <<<'TXT'
Algoritmo PesquisaSatisfacao
VAR
    contagem: VETOR[1..5] DE INTEIRO;
    i, nota, total: INTEIRO;
INICIO
    PARA i DE 1 ATE 5 FACA
        contagem[i] <- 0;
    FIMPARA
    total <- 0;
    LEIA(nota);
    ENQUANTO nota <> 0 FACA
        SE (nota >= 1) E (nota <= 5) ENTAO
            contagem[nota] <- contagem[nota] + 1;
            total <- total + 1;
        FIMSE
        LEIA(nota);
    FIMENQUANTO
    SE total > 0 ENTAO
        PARA i DE 1 ATE 5 FACA
            ESCREVA("Nota ", i, ": ", contagem[i] * 100 / total, "%");
        FIMPARA
    FIMSE
FIM.
Exigido: vetor de contadores indexado pela nota, contador geral, validação da faixa e percentuais calculados depois do laço.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Vetores — Lista 9: vetores paralelos',
      'descricao' => 'Dois ou mais vetores relacionados pela mesma posição.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia o nome e a idade de 5 pessoas em dois vetores e exiba o nome das pessoas com mais de 30 anos.',
          'referencia' => 'Conceitos esperados: dois vetores com o mesmo índice para nome e idade e exibição do nome quando a idade da mesma posição for maior que 30.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o nome e o preço de 10 produtos em vetores paralelos e exiba o nome do produto mais caro.',
          'referencia' => <<<'TXT'
Algoritmo ProdutoMaisCaro
VAR
    nomes: VETOR[1..10] DE CARACTERE;
    precos: VETOR[1..10] DE REAL;
    i, maisCaro: INTEIRO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(nomes[i], precos[i]);
    FIMPARA
    maisCaro <- 1;
    PARA i DE 2 ATE 10 FACA
        SE precos[i] > precos[maisCaro] ENTAO
            maisCaro <- i;
        FIMSE
    FIMPARA
    ESCREVA("Mais caro: ", nomes[maisCaro]);
FIM.
Conceitos exigidos: guardar a posição do maior preço e usá-la para buscar o nome no vetor paralelo.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia o nome e a nota de 10 alunos em vetores paralelos e exiba a lista ordenada da maior para a menor nota, com os nomes. Você deve usar trocas que mantenham nome e nota juntos nos dois vetores.',
          'referencia' => <<<'TXT'
Algoritmo RankingDeNotas
VAR
    nomes: VETOR[1..10] DE CARACTERE;
    notas: VETOR[1..10] DE REAL;
    i, j: INTEIRO;
    auxNota: REAL;
    auxNome: CARACTERE;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(nomes[i], notas[i]);
    FIMPARA
    PARA i DE 1 ATE 9 FACA
        PARA j DE 1 ATE 10 - i FACA
            SE notas[j] < notas[j + 1] ENTAO
                auxNota <- notas[j]; notas[j] <- notas[j + 1]; notas[j + 1] <- auxNota;
                auxNome <- nomes[j]; nomes[j] <- nomes[j + 1]; nomes[j + 1] <- auxNome;
            FIMSE
        FIMPARA
    FIMPARA
    PARA i DE 1 ATE 10 FACA
        ESCREVA(nomes[i], " - ", notas[i]);
    FIMPARA
FIM.
Exigido: toda troca de nota é acompanhada da troca do nome na mesma posição, para não desalinhar os vetores.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia a quantidade vendida e o preço unitário de 8 produtos em vetores paralelos e exiba o faturamento de cada produto e o total geral.',
          'referencia' => <<<'TXT'
Algoritmo FaturamentoPorProduto
VAR
    quantidade: VETOR[1..8] DE INTEIRO;
    preco: VETOR[1..8] DE REAL;
    i: INTEIRO;
    faturamento, total: REAL;
INICIO
    total <- 0;
    PARA i DE 1 ATE 8 FACA
        LEIA(quantidade[i], preco[i]);
        faturamento <- quantidade[i] * preco[i];
        total <- total + faturamento;
        ESCREVA("Produto ", i, ": ", faturamento);
    FIMPARA
    ESCREVA("Total: ", total);
FIM.
Conceitos exigidos: multiplicar os elementos de mesma posição e acumular o total geral.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o código e o estoque de 10 produtos em vetores paralelos e, dado um código, exiba o estoque correspondente ou "Produto não cadastrado".',
          'referencia' => 'Conceitos esperados: procurar o código no primeiro vetor e, ao encontrá-lo, exibir o estoque da mesma posição no segundo vetor.',
        ],
      ],
    ],
    [
      'titulo' => 'Vetores — Lista 10: problemas integradores',
      'descricao' => 'Aplicações combinando percurso, busca e cálculo.',
      'questoes' => [
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Um gabarito de prova com 10 questões (letras de A a E) está em um vetor. Escreva um algoritmo que leia o gabarito e as respostas de um aluno em outro vetor e exiba a nota (1 ponto por acerto) e os números das questões erradas. Você deve usar dois vetores de CARACTERE comparados posição a posição.',
          'referencia' => <<<'TXT'
Algoritmo CorrigeProva
VAR
    gabarito, respostas: VETOR[1..10] DE CARACTERE;
    i, nota: INTEIRO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(gabarito[i]);
    FIMPARA
    PARA i DE 1 ATE 10 FACA
        LEIA(respostas[i]);
    FIMPARA
    nota <- 0;
    PARA i DE 1 ATE 10 FACA
        SE respostas[i] = gabarito[i] ENTAO
            nota <- nota + 1;
        SENAO
            ESCREVA("Errou a questão ", i);
        FIMSE
    FIMPARA
    ESCREVA("Nota: ", nota);
FIM.
Exigido: dois vetores de CARACTERE e comparação da mesma posição para contar acertos e listar os erros.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e exiba a média dos elementos que estão nas posições ímpares.',
          'referencia' => 'Conceitos esperados: percorrer só as posições ímpares (1, 3, 5, 7, 9), somar os valores e dividir por 5.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia dois vetores de 5 números reais e exiba o produto escalar entre eles (soma dos produtos das posições correspondentes).',
          'referencia' => <<<'TXT'
Algoritmo ProdutoEscalar
VAR
    a, b: VETOR[1..5] DE REAL;
    i: INTEIRO;
    produto: REAL;
INICIO
    PARA i DE 1 ATE 5 FACA
        LEIA(a[i], b[i]);
    FIMPARA
    produto <- 0;
    PARA i DE 1 ATE 5 FACA
        produto <- produto + a[i] * b[i];
    FIMPARA
    ESCREVA("Produto escalar: ", produto);
FIM.
Conceitos exigidos: acumular a soma de a[i] × b[i] em uma variável iniciada em zero.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números em um vetor e exiba a soma acumulada até cada posição (a posição i do resultado é a soma de v[1] até v[i]). Você deve usar um segundo vetor para as somas acumuladas, calculando cada posição a partir da anterior.',
          'referencia' => <<<'TXT'
Algoritmo SomaAcumulada
VAR
    v, acumulado: VETOR[1..10] DE INTEIRO;
    i: INTEIRO;
INICIO
    PARA i DE 1 ATE 10 FACA
        LEIA(v[i]);
    FIMPARA
    acumulado[1] <- v[1];
    PARA i DE 2 ATE 10 FACA
        acumulado[i] <- acumulado[i - 1] + v[i];
    FIMPARA
    PARA i DE 1 ATE 10 FACA
        ESCREVA(acumulado[i]);
    FIMPARA
FIM.
Exigido: vetor de acumulados com acumulado[i] = acumulado[i − 1] + v[i]; recalcular a soma inteira para cada posição não atende ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia as vendas diárias de uma semana (7 dias) em um vetor e exiba os dias em que as vendas ficaram abaixo do dia anterior.',
          'referencia' => 'Conceitos esperados: comparar cada dia (do segundo em diante) com o anterior, v[i] < v[i − 1], e exibir o número do dia.',
        ],
      ],
    ],
  ],
];
