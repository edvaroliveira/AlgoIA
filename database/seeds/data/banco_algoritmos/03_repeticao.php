<?php

declare(strict_types=1);

return [
  'tema' => 'Estruturas de Repetição',
  'exercicios' => [
    [
      'titulo' => 'Repetição — Lista 1: laço PARA',
      'descricao' => 'Contagens e sequências com número conhecido de passos.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que exiba os números inteiros de 1 a 100.',
          'referencia' => 'Conceitos esperados: um laço de 1 a 100 com uma variável de controle e a exibição do valor a cada passo.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro N e exiba a tabuada de multiplicação de N, de 1 a 10.',
          'referencia' => <<<'TXT'
Algoritmo Tabuada
VAR
    n, i: INTEIRO;
INICIO
    LEIA(n);
    PARA i DE 1 ATE 10 FACA
        ESCREVA(n, " x ", i, " = ", n * i);
    FIMPARA
FIM.
Conceitos exigidos: laço de 1 a 10 e exibição de cada linha da tabuada com o produto n × i.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que exiba os números pares de 2 a 50. Você deve usar o laço PARA com passo 2, sem testar a paridade dentro do laço.',
          'referencia' => <<<'TXT'
Algoritmo ParesComPasso
VAR
    i: INTEIRO;
INICIO
    PARA i DE 2 ATE 50 PASSO 2 FACA
        ESCREVA(i);
    FIMPARA
FIM.
Exigido: PARA com PASSO 2 começando em 2. Laços de 1 em 1 com teste MOD não atendem ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que exiba os números de 20 a 1, em ordem decrescente.',
          'referencia' => 'Conceitos esperados: um laço que começa em 20 e decrementa até 1, exibindo cada valor.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro N e exiba a soma de todos os inteiros de 1 até N.',
          'referencia' => <<<'TXT'
Algoritmo SomaAteN
VAR
    n, i, soma: INTEIRO;
INICIO
    LEIA(n);
    soma <- 0;
    PARA i DE 1 ATE n FACA
        soma <- soma + i;
    FIMPARA
    ESCREVA("Soma: ", soma);
FIM.
Conceitos exigidos: acumulador iniciado em zero antes do laço e somado a cada passo.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Repetição — Lista 2: ENQUANTO e condição de parada',
      'descricao' => 'Laços controlados por condição, com sentinela.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia números inteiros até que seja digitado o valor 0 e exiba a soma dos números lidos.',
          'referencia' => <<<'TXT'
Algoritmo SomaAteZero
VAR
    n, soma: INTEIRO;
INICIO
    soma <- 0;
    LEIA(n);
    ENQUANTO n <> 0 FACA
        soma <- soma + n;
        LEIA(n);
    FIMENQUANTO
    ESCREVA("Soma: ", soma);
FIM.
Conceitos exigidos: leitura antes do laço, condição de parada no valor sentinela 0 e nova leitura ao final de cada repetição.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia idades até que seja informada uma idade negativa e exiba quantas idades válidas foram digitadas.',
          'referencia' => 'Conceitos esperados: laço com condição de parada para idade negativa e um contador incrementado a cada idade válida.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o valor de um depósito mensal e um valor objetivo e exiba em quantos meses o objetivo será alcançado, sem considerar juros.',
          'referencia' => 'Conceitos esperados: acumular o depósito mês a mês enquanto o total for menor que o objetivo e contar os meses.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro positivo e exiba quantos dígitos ele tem. Você deve usar o laço ENQUANTO com divisões inteiras por 10.',
          'referencia' => <<<'TXT'
Algoritmo ContaDigitos
VAR
    n, digitos: INTEIRO;
INICIO
    LEIA(n);
    digitos <- 1;
    ENQUANTO n >= 10 FACA
        n <- n DIV 10;
        digitos <- digitos + 1;
    FIMENQUANTO
    ESCREVA("Dígitos: ", digitos);
FIM.
Exigido: ENQUANTO dividindo o número por 10 (DIV) e contando as divisões. Converter o número em texto não atende ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'A população de uma cidade A é de 80.000 habitantes e cresce 3% ao ano; a de uma cidade B é de 200.000 e cresce 1,5% ao ano. Escreva um algoritmo que calcule em quantos anos a população de A ultrapassará a de B.',
          'referencia' => <<<'TXT'
Algoritmo UltrapassaPopulacao
VAR
    popA, popB: REAL;
    anos: INTEIRO;
INICIO
    popA <- 80000;
    popB <- 200000;
    anos <- 0;
    ENQUANTO popA <= popB FACA
        popA <- popA * 1.03;
        popB <- popB * 1.015;
        anos <- anos + 1;
    FIMENQUANTO
    ESCREVA("Anos: ", anos);
FIM.
Conceitos exigidos: atualizar as duas populações a cada ano dentro do laço, contar os anos e parar quando A passar B.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Repetição — Lista 3: REPITA-ATE e validação',
      'descricao' => 'Laços que executam pelo menos uma vez, para validar entradas e menus.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia uma nota e repita a leitura enquanto ela estiver fora do intervalo de 0 a 10, exibindo a nota válida ao final.',
          'referencia' => 'Conceitos esperados: repetir a leitura até que a nota esteja entre 0 e 10 e exibir o valor aceito.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que exiba um menu com as opções 1 - Somar, 2 - Subtrair e 0 - Sair, leia dois números para cada operação escolhida e repita até que a opção 0 seja escolhida. Você deve usar o laço REPITA...ATE.',
          'referencia' => <<<'TXT'
Algoritmo MenuOperacoes
VAR
    opcao: INTEIRO;
    a, b: REAL;
INICIO
    REPITA
        ESCREVA("1 - Somar  2 - Subtrair  0 - Sair");
        LEIA(opcao);
        SE (opcao = 1) OU (opcao = 2) ENTAO
            LEIA(a, b);
            SE opcao = 1 ENTAO
                ESCREVA(a + b);
            SENAO
                ESCREVA(a - b);
            FIMSE
        FIMSE
    ATE opcao = 0
FIM.
Exigido: REPITA...ATE com a condição de saída na opção 0, garantindo que o menu apareça ao menos uma vez.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que peça uma senha numérica até que o usuário digite 1234, permitindo no máximo 3 tentativas, e informe se o acesso foi liberado ou bloqueado.',
          'referencia' => <<<'TXT'
Algoritmo TresTentativas
VAR
    senha, tentativas: INTEIRO;
INICIO
    tentativas <- 0;
    REPITA
        LEIA(senha);
        tentativas <- tentativas + 1;
    ATE (senha = 1234) OU (tentativas = 3)
    SE senha = 1234 ENTAO
        ESCREVA("Acesso liberado");
    SENAO
        ESCREVA("Acesso bloqueado");
    FIMSE
FIM.
Conceitos exigidos: contador de tentativas, condição de saída composta (senha certa OU limite atingido) e verificação final do motivo da saída.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o sexo de uma pessoa ("M" ou "F") e repita a leitura até que um desses valores seja informado.',
          'referencia' => 'Conceitos esperados: repetir a leitura enquanto o caractere for diferente de "M" e de "F" (condição composta) e encerrar quando for válido.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo de "adivinhe o número": o número secreto é 42; o usuário dá palpites e recebe as dicas "maior" ou "menor" até acertar, e ao final o algoritmo exibe quantos palpites foram feitos. Você deve usar REPITA...ATE e um contador de palpites.',
          'referencia' => <<<'TXT'
Algoritmo AdivinheNumero
VAR
    palpite, contador: INTEIRO;
INICIO
    contador <- 0;
    REPITA
        LEIA(palpite);
        contador <- contador + 1;
        SE palpite < 42 ENTAO
            ESCREVA("O número é maior");
        SENAO
            SE palpite > 42 ENTAO
                ESCREVA("O número é menor");
            FIMSE
        FIMSE
    ATE palpite = 42
    ESCREVA("Acertou em ", contador, " palpites");
FIM.
Exigido: REPITA...ATE parando no acerto, dicas dentro do laço e contador incrementado a cada palpite.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Repetição — Lista 4: contadores e acumuladores',
      'descricao' => 'Somas, médias e contagens sobre séries de dados.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia as notas de 10 alunos e exiba a média da turma.',
          'referencia' => <<<'TXT'
Algoritmo MediaDaTurma
VAR
    i: INTEIRO;
    nota, soma, media: REAL;
INICIO
    soma <- 0;
    PARA i DE 1 ATE 10 FACA
        LEIA(nota);
        soma <- soma + nota;
    FIMPARA
    media <- soma / 10;
    ESCREVA("Média da turma: ", media);
FIM.
Conceitos exigidos: acumulador iniciado em zero, leitura dentro do laço e divisão pelo total de alunos depois do laço.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia 15 números inteiros e exiba quantos deles são positivos.',
          'referencia' => 'Conceitos esperados: laço de 15 leituras e um contador incrementado só quando o número for maior que zero.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 20 números inteiros e exiba a soma dos pares e a soma dos ímpares. Você deve usar dois acumuladores separados, um para cada grupo.',
          'referencia' => <<<'TXT'
Algoritmo SomaParesImpares
VAR
    i, n, somaPares, somaImpares: INTEIRO;
INICIO
    somaPares <- 0;
    somaImpares <- 0;
    PARA i DE 1 ATE 20 FACA
        LEIA(n);
        SE n MOD 2 = 0 ENTAO
            somaPares <- somaPares + n;
        SENAO
            somaImpares <- somaImpares + n;
        FIMSE
    FIMPARA
    ESCREVA("Pares: ", somaPares, " Ímpares: ", somaImpares);
FIM.
Exigido: dois acumuladores distintos, ambos iniciados em zero antes do laço.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia a idade e o sexo ("M" ou "F") de 30 pessoas e exiba quantos homens e quantas mulheres têm mais de 18 anos.',
          'referencia' => <<<'TXT'
Algoritmo AdultosPorSexo
VAR
    i, idade, homens, mulheres: INTEIRO;
    sexo: CARACTERE;
INICIO
    homens <- 0;
    mulheres <- 0;
    PARA i DE 1 ATE 30 FACA
        LEIA(idade, sexo);
        SE idade > 18 ENTAO
            SE sexo = "M" ENTAO
                homens <- homens + 1;
            SENAO
                mulheres <- mulheres + 1;
            FIMSE
        FIMSE
    FIMPARA
    ESCREVA("Homens: ", homens, " Mulheres: ", mulheres);
FIM.
Conceitos exigidos: dois contadores, decisão sobre a idade e depois sobre o sexo dentro do laço.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o preço de vários produtos até que seja digitado 0 e exiba o valor total da compra e o preço médio dos produtos.',
          'referencia' => 'Conceitos esperados: laço com sentinela 0, acumulador do total, contador de produtos e cálculo da média só se ao menos um produto for lido.',
        ],
      ],
    ],
    [
      'titulo' => 'Repetição — Lista 5: maior, menor e extremos',
      'descricao' => 'Encontrar extremos e suas posições em séries.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 números e exiba o maior e o menor valor lido.',
          'referencia' => 'Conceitos esperados: iniciar maior e menor com o primeiro valor lido e atualizá-los a cada nova leitura dentro do laço.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia a altura de 15 atletas e exiba a maior altura e em qual posição da leitura ela apareceu.',
          'referencia' => <<<'TXT'
Algoritmo MaiorAltura
VAR
    i, posicao: INTEIRO;
    altura, maior: REAL;
INICIO
    LEIA(altura);
    maior <- altura;
    posicao <- 1;
    PARA i DE 2 ATE 15 FACA
        LEIA(altura);
        SE altura > maior ENTAO
            maior <- altura;
            posicao <- i;
        FIMSE
    FIMPARA
    ESCREVA("Maior altura: ", maior, " na posição ", posicao);
FIM.
Conceitos exigidos: o primeiro valor como referência, atualização do maior e da posição juntos dentro do laço.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia a temperatura registrada a cada hora durante 24 horas e exiba a menor temperatura do dia.',
          'referencia' => 'Conceitos esperados: laço de 24 leituras e uma variável para a menor temperatura, iniciada com a primeira leitura.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia o nome e a nota de alunos até que seja digitado o nome "FIM" e exiba o nome do aluno com a maior nota. Você deve usar o laço ENQUANTO com a leitura do nome antes do laço e no final de cada repetição.',
          'referencia' => <<<'TXT'
Algoritmo MelhorAluno
VAR
    nome, melhorNome: CARACTERE;
    nota, maiorNota: REAL;
INICIO
    maiorNota <- -1;
    melhorNome <- "";
    LEIA(nome);
    ENQUANTO nome <> "FIM" FACA
        LEIA(nota);
        SE nota > maiorNota ENTAO
            maiorNota <- nota;
            melhorNome <- nome;
        FIMSE
        LEIA(nome);
    FIMENQUANTO
    SE maiorNota >= 0 ENTAO
        ESCREVA("Maior nota: ", melhorNome, " (", maiorNota, ")");
    FIMSE
FIM.
Exigido: ENQUANTO com leitura antecipada do nome e nova leitura ao final do laço; guardar o nome junto com a maior nota.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 12 valores de vendas mensais e exiba o mês (1 a 12) com a menor venda. Você deve usar o laço PARA e guardar o número do mês da menor venda em uma variável.',
          'referencia' => <<<'TXT'
Algoritmo MesMenorVenda
VAR
    mes, mesMenor: INTEIRO;
    venda, menor: REAL;
INICIO
    PARA mes DE 1 ATE 12 FACA
        LEIA(venda);
        SE (mes = 1) OU (venda < menor) ENTAO
            menor <- venda;
            mesMenor <- mes;
        FIMSE
    FIMPARA
    ESCREVA("Mês com menor venda: ", mesMenor);
FIM.
Exigido: PARA de 1 a 12 e a variável mesMenor atualizada junto com a menor venda.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Repetição — Lista 6: sequências numéricas',
      'descricao' => 'Fibonacci, progressões e séries.',
      'questoes' => [
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que exiba os 15 primeiros termos da sequência de Fibonacci (1, 1, 2, 3, 5, ...). Você deve usar três variáveis (anterior, atual e próximo) e o laço PARA.',
          'referencia' => <<<'TXT'
Algoritmo Fibonacci15
VAR
    i, anterior, atual, proximo: INTEIRO;
INICIO
    anterior <- 0;
    atual <- 1;
    PARA i DE 1 ATE 15 FACA
        ESCREVA(atual);
        proximo <- anterior + atual;
        anterior <- atual;
        atual <- proximo;
    FIMPARA
FIM.
Exigido: as três variáveis atualizadas a cada passo do PARA, sem guardar a sequência em vetor.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia o primeiro termo e a razão de uma progressão aritmética e exiba os 10 primeiros termos.',
          'referencia' => 'Conceitos esperados: exibir o termo atual e somar a razão a cada passo, repetindo 10 vezes.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro N e calcule a soma S = 1 + 1/2 + 1/3 + ... + 1/N.',
          'referencia' => <<<'TXT'
Algoritmo SerieHarmonica
VAR
    n, i: INTEIRO;
    s: REAL;
INICIO
    LEIA(n);
    s <- 0;
    PARA i DE 1 ATE n FACA
        s <- s + 1 / i;
    FIMPARA
    ESCREVA("S = ", s);
FIM.
Conceitos exigidos: acumulador REAL e soma de 1 / i para i de 1 a N.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o primeiro termo e a razão de uma progressão geométrica e exiba os 8 primeiros termos e a soma deles.',
          'referencia' => 'Conceitos esperados: multiplicar o termo pela razão a cada passo, exibir cada termo e acumular a soma dos 8 termos.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que calcule a soma S = 1/1 − 2/4 + 3/9 − 4/16 + ... alternando os sinais, até o décimo termo.',
          'referencia' => <<<'TXT'
Algoritmo SerieAlternada
VAR
    i: INTEIRO;
    s, sinal: REAL;
INICIO
    s <- 0;
    sinal <- 1;
    PARA i DE 1 ATE 10 FACA
        s <- s + sinal * i / (i * i);
        sinal <- -sinal;
    FIMPARA
    ESCREVA("S = ", s);
FIM.
Conceitos exigidos: termo i / i², variável de sinal invertida a cada passo e acumulador REAL.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Repetição — Lista 7: fatorial, potência e divisores',
      'descricao' => 'Cálculos matemáticos clássicos com laços.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro N (N ≥ 0) e exiba o seu fatorial.',
          'referencia' => 'Conceitos esperados: acumulador multiplicativo iniciado em 1 e multiplicação pelos valores de 1 (ou 2) até N; 0! = 1.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro positivo e exiba todos os seus divisores.',
          'referencia' => 'Conceitos esperados: percorrer os candidatos de 1 até o número e exibir os que deixam resto zero na divisão.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro maior que 1 e informe se ele é primo.',
          'referencia' => <<<'TXT'
Algoritmo VerificaPrimo
VAR
    n, i, divisores: INTEIRO;
INICIO
    LEIA(n);
    divisores <- 0;
    PARA i DE 1 ATE n FACA
        SE n MOD i = 0 ENTAO
            divisores <- divisores + 1;
        FIMSE
    FIMPARA
    SE divisores = 2 ENTAO
        ESCREVA("Primo");
    SENAO
        ESCREVA("Não é primo");
    FIMSE
FIM.
Conceitos exigidos: contar os divisores (ou procurar um divisor entre 2 e n − 1) e concluir que o número é primo quando só tem dois divisores.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia uma base inteira B e um expoente inteiro E (E ≥ 0) e exiba B elevado a E. Obrigatoriamente, a potência deve ser calculada com multiplicações sucessivas em um laço, sem operador ou função de potência.',
          'referencia' => <<<'TXT'
Algoritmo PotenciaPorLaco
VAR
    base, expoente, i, resultado: INTEIRO;
INICIO
    LEIA(base, expoente);
    resultado <- 1;
    PARA i DE 1 ATE expoente FACA
        resultado <- resultado * base;
    FIMPARA
    ESCREVA(base, "^", expoente, " = ", resultado);
FIM.
Exigido: acumulador iniciado em 1 e multiplicado pela base E vezes; não é permitido usar ^ ou POTENCIA.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia dois números inteiros positivos e exiba o máximo divisor comum (MDC) entre eles pelo algoritmo de Euclides.',
          'referencia' => <<<'TXT'
Algoritmo MdcEuclides
VAR
    a, b, resto: INTEIRO;
INICIO
    LEIA(a, b);
    ENQUANTO b <> 0 FACA
        resto <- a MOD b;
        a <- b;
        b <- resto;
    FIMENQUANTO
    ESCREVA("MDC: ", a);
FIM.
Conceitos exigidos: repetir a troca a <- b, b <- a MOD b até que b seja zero; o MDC é o valor final de a.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Repetição — Lista 8: laços aninhados',
      'descricao' => 'Um laço dentro de outro para padrões e combinações.',
      'questoes' => [
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que exiba as tabuadas de multiplicação de 1 a 9, cada uma de 1 a 10.',
          'referencia' => <<<'TXT'
Algoritmo TodasAsTabuadas
VAR
    t, i: INTEIRO;
INICIO
    PARA t DE 1 ATE 9 FACA
        PARA i DE 1 ATE 10 FACA
            ESCREVA(t, " x ", i, " = ", t * i);
        FIMPARA
    FIMPARA
FIM.
Conceitos exigidos: laço externo para as tabuadas e laço interno para os multiplicadores.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um número N e desenhe um triângulo de asteriscos com N linhas, em que a linha i tem i asteriscos. Você deve usar dois laços PARA aninhados.',
          'referencia' => <<<'TXT'
Algoritmo TrianguloAsteriscos
VAR
    n, linha, coluna: INTEIRO;
INICIO
    LEIA(n);
    PARA linha DE 1 ATE n FACA
        PARA coluna DE 1 ATE linha FACA
            ESCREVA("*");
        FIMPARA
        ESCREVAL("");
    FIMPARA
FIM.
Exigido: PARA externo nas linhas e PARA interno indo até o número da linha, com quebra de linha ao final de cada uma.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que exiba todos os pares ordenados (x, y) com x e y variando de 1 a 3.',
          'referencia' => 'Conceitos esperados: dois laços aninhados, um para x e outro para y, exibindo os 9 pares.',
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que exiba todos os números primos entre 2 e 100.',
          'referencia' => 'Conceitos esperados: laço externo pelos candidatos e laço interno procurando divisores; exibir o número quando nenhum divisor próprio for encontrado.',
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que encontre e exiba todas as combinações de moedas de 5, 10 e 25 centavos cuja soma seja exatamente R$ 1,00. Obrigatoriamente, a busca deve ser feita com três laços aninhados, um para cada tipo de moeda.',
          'referencia' => <<<'TXT'
Algoritmo CombinacoesMoedas
VAR
    m5, m10, m25: INTEIRO;
INICIO
    PARA m25 DE 0 ATE 4 FACA
        PARA m10 DE 0 ATE 10 FACA
            PARA m5 DE 0 ATE 20 FACA
                SE m25 * 25 + m10 * 10 + m5 * 5 = 100 ENTAO
                    ESCREVA(m25, " de 25, ", m10, " de 10, ", m5, " de 5");
                FIMSE
            FIMPARA
        FIMPARA
    FIMPARA
FIM.
Exigido: três laços aninhados com limites compatíveis com R$ 1,00 e teste da soma igual a 100 centavos.
TXT,
        ],
      ],
    ],
    [
      'titulo' => 'Repetição — Lista 9: estatísticas de dados',
      'descricao' => 'Percentuais, médias e contagens combinadas.',
      'questoes' => [
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia o salário de 50 funcionários e exiba quantos ganham acima da média salarial, considerando que a média já foi informada no início.',
          'referencia' => 'Conceitos esperados: ler a média antes do laço e contar, dentro do laço, os salários maiores que ela.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Em uma eleição com os candidatos 1 e 2, os votos são lidos até que seja digitado 0 (voto 3 é nulo). Escreva um algoritmo que exiba o total de votos de cada candidato e de nulos.',
          'referencia' => <<<'TXT'
Algoritmo ApuraVotos
VAR
    voto, c1, c2, nulos: INTEIRO;
INICIO
    c1 <- 0; c2 <- 0; nulos <- 0;
    LEIA(voto);
    ENQUANTO voto <> 0 FACA
        ESCOLHA voto
            CASO 1: c1 <- c1 + 1;
            CASO 2: c2 <- c2 + 1;
            OUTROCASO: nulos <- nulos + 1;
        FIMESCOLHA
        LEIA(voto);
    FIMENQUANTO
    ESCREVA("Candidato 1: ", c1, " Candidato 2: ", c2, " Nulos: ", nulos);
FIM.
Conceitos exigidos: laço com sentinela 0, um contador por resultado e seleção do contador a cada voto.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que leia a idade de pessoas até que seja digitada a idade 0 e exiba o percentual de menores de idade, de adultos (18 a 59) e de idosos (60 ou mais). Você deve usar um contador por faixa e um contador geral, calculando os percentuais só depois do laço.',
          'referencia' => <<<'TXT'
Algoritmo PercentualPorFaixa
VAR
    idade, total, menores, adultos, idosos: INTEIRO;
INICIO
    total <- 0; menores <- 0; adultos <- 0; idosos <- 0;
    LEIA(idade);
    ENQUANTO idade <> 0 FACA
        total <- total + 1;
        SE idade < 18 ENTAO
            menores <- menores + 1;
        SENAO
            SE idade < 60 ENTAO
                adultos <- adultos + 1;
            SENAO
                idosos <- idosos + 1;
            FIMSE
        FIMSE
        LEIA(idade);
    FIMENQUANTO
    SE total > 0 ENTAO
        ESCREVA(menores * 100 / total, "% menores, ", adultos * 100 / total, "% adultos, ", idosos * 100 / total, "% idosos");
    FIMSE
FIM.
Exigido: três contadores por faixa, um contador geral e o cálculo dos percentuais depois do laço, protegido contra total zero.
TXT,
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia 10 notas e exiba quantas ficaram abaixo de 5, quantas entre 5 e 7 (inclusive) e quantas acima de 7.',
          'referencia' => <<<'TXT'
Algoritmo NotasPorFaixa
VAR
    i, abaixo, meio, acima: INTEIRO;
    nota: REAL;
INICIO
    abaixo <- 0; meio <- 0; acima <- 0;
    PARA i DE 1 ATE 10 FACA
        LEIA(nota);
        SE nota < 5 ENTAO
            abaixo <- abaixo + 1;
        SENAO
            SE nota <= 7 ENTAO
                meio <- meio + 1;
            SENAO
                acima <- acima + 1;
            FIMSE
        FIMSE
    FIMPARA
    ESCREVA(abaixo, " abaixo de 5, ", meio, " entre 5 e 7, ", acima, " acima de 7");
FIM.
Conceitos exigidos: três contadores e faixas sem sobreposição, com 5 e 7 incluídos na faixa do meio.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia 8 números e exiba a média apenas dos números maiores que 10.',
          'referencia' => 'Conceitos esperados: somar e contar só os números maiores que 10 e dividir a soma pela contagem, tratando o caso de nenhum número válido.',
        ],
      ],
    ],
    [
      'titulo' => 'Repetição — Lista 10: problemas integradores',
      'descricao' => 'Simulações e problemas com várias repetições e decisões.',
      'questoes' => [
        [
          'rigidez' => 'rigida', 'pontos' => 3.0,
          'enunciado' => 'Escreva um algoritmo que simule um caixa eletrônico: leia um valor inteiro de saque e exiba a quantidade de cédulas de 100, 50, 20 e 10 necessárias, recusando valores que não sejam múltiplos de 10. Você deve usar um laço ENQUANTO que subtraia a maior cédula possível do valor restante.',
          'referencia' => <<<'TXT'
Algoritmo CaixaEletronico
VAR
    valor, n100, n50, n20, n10: INTEIRO;
INICIO
    LEIA(valor);
    SE valor MOD 10 <> 0 ENTAO
        ESCREVA("Valor inválido");
    SENAO
        n100 <- 0; n50 <- 0; n20 <- 0; n10 <- 0;
        ENQUANTO valor > 0 FACA
            SE valor >= 100 ENTAO
                valor <- valor - 100; n100 <- n100 + 1;
            SENAO
                SE valor >= 50 ENTAO
                    valor <- valor - 50; n50 <- n50 + 1;
                SENAO
                    SE valor >= 20 ENTAO
                        valor <- valor - 20; n20 <- n20 + 1;
                    SENAO
                        valor <- valor - 10; n10 <- n10 + 1;
                    FIMSE
                FIMSE
            FIMSE
        FIMENQUANTO
        ESCREVA(n100, "x100 ", n50, "x50 ", n20, "x20 ", n10, "x10");
    FIMSE
FIM.
Exigido: validar o múltiplo de 10 e usar ENQUANTO subtraindo a maior cédula possível a cada repetição; a solução direta com DIV/MOD não atende ao enunciado.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro positivo e informe se ele é um número perfeito (igual à soma de seus divisores menores que ele, como 6 = 1 + 2 + 3).',
          'referencia' => 'Conceitos esperados: somar os divisores de 1 até n − 1 em um laço e comparar a soma com o próprio número.',
        ],
        [
          'rigidez' => 'padrao', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro positivo e exiba a soma dos seus dígitos.',
          'referencia' => <<<'TXT'
Algoritmo SomaDosDigitos
VAR
    n, soma: INTEIRO;
INICIO
    LEIA(n);
    soma <- 0;
    ENQUANTO n > 0 FACA
        soma <- soma + n MOD 10;
        n <- n DIV 10;
    FIMENQUANTO
    ESCREVA("Soma dos dígitos: ", soma);
FIM.
Conceitos exigidos: somar o último dígito (MOD 10) e removê-lo (DIV 10) até o número chegar a zero.
TXT,
        ],
        [
          'rigidez' => 'rigida', 'pontos' => 2.0,
          'enunciado' => 'Escreva um algoritmo que leia um número inteiro positivo e informe se ele é um palíndromo (igual quando lido de trás para frente, como 12321). Você deve usar um laço para montar o número invertido e compará-lo com o original.',
          'referencia' => <<<'TXT'
Algoritmo NumeroPalindromo
VAR
    n, original, invertido: INTEIRO;
INICIO
    LEIA(n);
    original <- n;
    invertido <- 0;
    ENQUANTO n > 0 FACA
        invertido <- invertido * 10 + n MOD 10;
        n <- n DIV 10;
    FIMENQUANTO
    SE invertido = original ENTAO
        ESCREVA("Palíndromo");
    SENAO
        ESCREVA("Não é palíndromo");
    FIMSE
FIM.
Exigido: guardar o original, montar o invertido no laço (invertido × 10 + último dígito) e comparar os dois.
TXT,
        ],
        [
          'rigidez' => 'flexivel', 'pontos' => 1.0,
          'enunciado' => 'Escreva um algoritmo que leia 5 pares de números e, para cada par, exiba o maior dos dois.',
          'referencia' => 'Conceitos esperados: um laço de 5 repetições, leitura do par em cada passo e comparação para exibir o maior.',
        ],
      ],
    ],
  ],
];
