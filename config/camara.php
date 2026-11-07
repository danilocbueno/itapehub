<?php

/*
|--------------------------------------------------------------------------
| Vereadores: nome como aparece no SisCam => nome curto usado nas notícias
|--------------------------------------------------------------------------
|
| O SisCam traz o nome civil completo. A notícia usa o nome pelo qual o
| vereador é conhecido; a troca é feita antes de mandar os dados ao LLM, para
| ele não precisar encurtar (nem errar) nomes. Quem não está aqui aparece com
| o nome do SisCam.
|
*/

return [

    'vereadores' => [
        'Bispo André' => 'Bispo André',
        'André Luiz Bueno' => 'Bispo André',
        'Catarina Aparecida Nanini Motta' => 'Catarina Motta',
        'Christiane Aparecida Gomes de Siqueira Almeida' => 'Christiane Almeida',
        'Cristiane de Fátima Cardena' => 'Cristiane Cardena',
        'Denise Franci Martins de Castro' => 'Denise Castro',
        'Eduardo Vinícius Venturelli de Almeida Prando' => 'Eduardo Prando',
        'Francisco Roberto Ferreira' => 'Francisco Roberto',
        'George Felipe Oliveira de Araújo' => 'George Araújo',
        'Guilherme Luís Morelli' => 'Guilherme Morelli',
        'Itamar José Martins' => 'Itamar Martins',
        'Jeferson Machado Cruz' => 'Jeferson Cruz',
        'José Carlos Felipe de Almeida' => 'José Carlos Felipe',
        'Júlia Martins Nery' => 'Júlia Nery',
        'Júlia Nunes Machado' => 'Júlia Machado',
        'Luíz Hilário da Mota Conceição' => 'Luiz Hilário',
        'Marina Nalesso Mariano' => 'Marina Nalesso',
        'Mario Carneiro Neto' => 'Mario Carneiro',
        'Matheus Ribeiro de Almeida Santos' => 'Matheus Ribeiro',
        'Miguel Arcanjo Máximo de Jesus' => 'Miguel Arcanjo',
        'Ricardo Conegundes' => 'Ricardo Conegundes',
        'Uanderson Clayton de Oliveira Moreira' => 'Uanderson Moreira',
        'Chefe do Poder Executivo' => 'Prefeitura',
    ],

];
