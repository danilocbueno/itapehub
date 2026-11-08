<?php

namespace App\Programas;

use App\Data\Item;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Os episódios com notícia (data/programas) prontos para o site: um item para
 * a linha do tempo, que leva à página própria em /programas/<programa>/<slug>/.
 * Episódios sem notícia (Gemini ainda não escreveu) não aparecem.
 */
final class ProgramNews
{
    /**
     * @param  array<string, Program>  $programs
     * @return list<array{program: Program, slug: string, episode: array<string, mixed>, item: Item}> mais recentes primeiro
     */
    public static function load(string $directory, string $siteUrl, array $programs): array
    {
        $all = [];
        $repository = new EpisodeRepository($directory);

        foreach ($programs as $program) {
            $used = [];

            // Do mais antigo para o mais novo: a segunda parte de um dia ganha o id do vídeo no endereço.
            foreach (array_reverse($repository->all($program->key)) as $episode) {
                // Data e tema do título do vídeo (só a data em programas sem tema no título):
                // o endereço não muda se o texto for reescrito.
                $topic = Str::slug((string) ($episode['topic'] ?? ''));
                $slug = $episode['date'].($topic !== '' ? '-'.$topic : '');
                $slug = isset($used[$slug]) ? $slug.'-'.Str::lower((string) $episode['video']) : $slug;
                $used[$slug] = true;

                // Conta no endereço mesmo sem notícia, para o endereço da outra parte não mudar depois.
                if (! isset($episode['article']['titulo'])) {
                    continue;
                }

                // Hora aproximada do fim da transmissão.
                $end = CarbonImmutable::parse($episode['date'].' '.$program->start, 'America/Sao_Paulo')->addSeconds((int) $episode['seconds']);

                $all[] = [
                    'program' => $program,
                    'slug' => $slug,
                    'episode' => $episode,
                    'item' => new Item(
                        source: $program->sourceKey(),
                        url: rtrim($siteUrl, '/').'/programas/'.$program->key.'/'.$slug.'/',
                        title: (string) $episode['article']['titulo'],
                        summary: (string) ($episode['article']['linha_fina'] ?? ''),
                        image: 'https://i.ytimg.com/vi/'.$episode['video'].'/hqdefault.jpg',
                        publishedAt: $end,
                    ),
                ];
            }
        }

        usort($all, fn (array $a, array $b) => $b['item']->date() <=> $a['item']->date());

        return $all;
    }
}
