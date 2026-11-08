<?php

namespace App\Programas;

use RuntimeException;

/**
 * Lê os vídeos da aba "Ao vivo" de um canal do YouTube. A página traz os dados
 * num JSON (ytInitialData); cada vídeo é um "lockupViewModel" com o id, o
 * título e, nas lives já encerradas, a duração ("1:29:11") num selo da miniatura.
 * Lives agendadas ou em andamento têm "Em breve" ou "AO VIVO" no lugar da duração.
 */
final class ChannelParser
{
    /**
     * @return list<array{id: string, title: string, seconds: int|null}>
     */
    public static function videos(string $html): array
    {
        // Busca de texto, não expressão regular: o JSON passa de meio milhão de caracteres
        // e estoura o limite de backtracking do PCRE (o preg_match falhava sem aviso).
        foreach (['var ytInitialData = ', 'window["ytInitialData"] = '] as $marker) {
            if (($start = strpos($html, $marker.'{')) !== false) {
                break;
            }
        }

        $end = $start === false ? false : strpos($html, '};</script>', $start);

        if ($start === false || $end === false) {
            throw new RuntimeException('página do YouTube sem ytInitialData (o formato mudou ou veio a página de consentimento)');
        }

        $start += strlen($marker);
        $data = json_decode(substr($html, $start, $end + 1 - $start), true);

        if (! is_array($data)) {
            throw new RuntimeException('ytInitialData inválido');
        }

        $videos = [];
        self::walk($data, $videos);

        return $videos;
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<array{id: string, title: string, seconds: int|null}>  $videos
     */
    private static function walk(array $node, array &$videos): void
    {
        if (isset($node['lockupViewModel']) && is_array($node['lockupViewModel'])) {
            $video = self::video($node['lockupViewModel']);

            if ($video !== null) {
                $videos[] = $video;
            }

            return;
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                self::walk($child, $videos);
            }
        }
    }

    /**
     * @param  array<mixed>  $lockup
     * @return array{id: string, title: string, seconds: int|null}|null
     */
    private static function video(array $lockup): ?array
    {
        $id = $lockup['contentId'] ?? null;
        $title = $lockup['metadata']['lockupMetadataViewModel']['title']['content'] ?? null;

        if (! is_string($id) || ! is_string($title) || ($lockup['contentType'] ?? 'LOCKUP_CONTENT_TYPE_VIDEO') !== 'LOCKUP_CONTENT_TYPE_VIDEO') {
            return null;
        }

        $seconds = null;

        foreach ($lockup['contentImage']['thumbnailViewModel']['overlays'] ?? [] as $overlay) {
            foreach ($overlay['thumbnailBottomOverlayViewModel']['badges'] ?? [] as $badge) {
                $seconds ??= self::seconds((string) ($badge['thumbnailBadgeViewModel']['text'] ?? ''));
            }
        }

        return ['id' => $id, 'title' => trim($title), 'seconds' => $seconds];
    }

    /**
     * "1:29:11" → 5351; "38:16" → 2296; outro texto → null.
     */
    public static function seconds(string $text): ?int
    {
        if (! preg_match('~^(?:(\d{1,2}):)?(\d{1,2}):(\d{2})$~', trim($text), $m)) {
            return null;
        }

        return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
    }
}
