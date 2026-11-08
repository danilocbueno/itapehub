<?php

/*
|--------------------------------------------------------------------------
| Programas em vídeo
|--------------------------------------------------------------------------
|
| Programas locais no YouTube que viram notícia no ItapêHub. Os episódios são
| encontrados na aba "Ao vivo" do canal (as lives não aparecem no RSS do
| YouTube), e o texto é escrito pelo Gemini a partir do próprio vídeo.
|
| - pages: páginas do canal com a lista de vídeos (aba "Ao vivo" e, se o programa
|   também publicar como vídeo comum, a aba "Vídeos");
| - title: expressão com o grupo "date" (DD/MM/AAAA) e, se houver, "topic" (o
|   tema do episódio); o que não casar (cortes, jogos, patrocinados, outros
|   programas) é ignorado;
| - min_minutes: duração mínima (descarta cortes curtos e lives não iniciadas);
| - start: horário de início do programa, usado na data e hora do card;
| - fps: quadros por segundo enviados ao Gemini (padrão: 1). Nas rádios a imagem
|   é só o estúdio; com 0.2, um programa de 3h30 cabe numa chamada (~490 mil tokens).
|
*/

return [
    'telescopio' => [
        'name' => 'Telescópio',
        'station' => 'TV Itapê',
        // Alguns episódios são publicados como vídeo comum, sem live (05 e 06/11/2026).
        'pages' => ['https://www.youtube.com/@tvitape2/streams', 'https://www.youtube.com/@tvitape2/videos'],
        // Às vezes sem o traço depois do nome: "Telescópio  02/11/2026 - Dia de finados".
        'title' => '~^Telescópio\s*-?\s*(?<date>\d{2}/\d{2}/\d{4})\s*-\s*(?<topic>.+)$~iu',
        'min_minutes' => 20,
        'start' => '12:00',
        'color' => '#0f766e',
    ],

    // "Jornal do Meio-Dia - AO VIVO 🔴 - Rádio Super Difusora 94,5 FM - 06/11/2026"
    'jornal-do-meio-dia' => [
        'name' => 'Jornal do Meio-Dia',
        'station' => 'Rádio Super Difusora',
        'pages' => ['https://www.youtube.com/@SuperDifusoraOficial/streams'],
        'title' => '~^Jornal do Meio-Dia\b.*?(?<date>\d{2}/\d{2}/\d{4})\s*$~iu',
        'min_minutes' => 20,
        'start' => '12:00',
        'color' => '#1d4ed8',
        'fps' => 0.2,
    ],

    // "MANHÃ DA POP  -  06/11/2026" (às vezes com "|" e, na segunda parte, "ll" no fim).
    'manha-da-pop' => [
        'name' => 'Manhã da Pop',
        'station' => 'Pop FM',
        'pages' => ['https://www.youtube.com/@portaldapop/streams'],
        'title' => '~^Manhã da Pop\b[\s|-]*(?<date>\d{2}/\d{2}/\d{4})\b~iu',
        'min_minutes' => 20,
        'start' => '08:00',
        'color' => '#c2410c',
        'fps' => 0.2,
    ],
];
