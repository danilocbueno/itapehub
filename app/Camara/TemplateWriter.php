<?php

namespace App\Camara;

use App\Site\Timeline;
use Carbon\CarbonImmutable;

/**
 * Notícia da sessão montada sem IA, só com os dados do SisCam: pedidos por
 * tipo, votações de projetos (respeitando a fase) e o resto em uma linha.
 * É o caminho quando o Gemini não está configurado ou falha.
 */
final class TemplateWriter
{
    /**
     * @param  Session  $session  já com os nomes curtos
     */
    public function write(Session $session): Article
    {
        $date = CarbonImmutable::parse($session->date);
        $day = $date->day.' de '.Timeline::monthName($date);
        $sections = array_column($session->agenda, 'items', 'section');
        $services = $this->items($sections, '~^Indica~u');
        $requests = $this->items($sections, '~^Requer~u');
        $requestCount = count($services) + count($requests);
        $projects = array_values(array_filter($session->votes, fn (array $v) => str_starts_with($v['title'], 'Projeto')));

        $resumo = array_map(fn (array $vote) => $this->vote($vote), $projects);

        if ($requestCount > 0) {
            $resumo[] = sprintf('Os vereadores fizeram %s à Prefeitura e a outros órgãos.', $this->count($requestCount, 'pedido', 'pedidos'));
        }

        $new = $this->items($sections, '~^Novos Projetos~u');

        if ($new !== []) {
            $resumo[] = sprintf('%s começaram a tramitar.', ucfirst($this->count(count($new), 'novo projeto', 'novos projetos')));
        }

        $titles = array_values(array_filter($new, fn (array $i) => (bool) preg_match('~T[íi]tulo de Cidad~iu', $i['text'])));
        $bills = array_values(array_filter($new, fn (array $i) => ! in_array($i, $titles, true)));

        $secoes = array_values(array_filter([
            ['tema' => 'Pedidos de serviços', 'itens' => array_map(fn (array $i) => $this->line($i), $services)],
            ['tema' => 'Pedidos de informação e providências', 'itens' => array_map(fn (array $i) => $this->line($i), $requests)],
            ['tema' => 'Projetos apresentados', 'itens' => array_map(fn (array $i) => $this->sentence(preg_replace('~,?\s*e dá outras providências~iu', '', $i['text']) ?? $i['text']).($i['author'] !== '' ? " ({$i['author']})" : ''), $bills)],
        ], fn (array $s) => $s['itens'] !== []));

        $tambem = [];

        foreach ($this->items($sections, '~^Moç~u') as $motion) {
            $tambem[] = $this->line($motion);
        }

        if ($titles !== []) {
            $tambem[] = sprintf('%s concedem o título de cidadão itapetiningano (%s).', ucfirst($this->count(count($titles), 'projeto', 'projetos')), implode(', ', array_unique(array_filter(array_column($titles, 'author')))));
        }

        if ($answers = count($this->items($sections, '~^(Roteiro - )?Respostas~u'))) {
            $tambem[] = sprintf('A Prefeitura respondeu a %s feitos em sessões anteriores.', $this->count($answers, 'pedido', 'pedidos'));
        }

        $tambem[] = $this->attendance($session);

        return new Article(
            titulo: $requestCount > 0
                ? sprintf('Vereadores fazem %s na sessão de %s', $this->count($requestCount, 'pedido', 'pedidos'), $day)
                : sprintf('Câmara realiza a %s', $session->title),
            linhaFina: sprintf('Resumo da %s, com os pedidos, as votações e a presença dos vereadores.', $session->title),
            resumo: array_slice($resumo, 0, 5) ?: ['Sessão sem votação de projetos nem pedidos registrados.'],
            secoes: $secoes,
            tambem: $tambem,
        );
    }

    /**
     * Documentos das seções do roteiro cujo nome casa com $pattern.
     *
     * @param  array<string, list<array{title: string, url: string|null, author: string, text: string}>>  $sections
     * @return list<array{title: string, url: string|null, author: string, text: string}>
     */
    private function items(array $sections, string $pattern): array
    {
        $items = [];

        foreach ($sections as $name => $list) {
            if (preg_match($pattern, $name)) {
                array_push($items, ...$list);
            }
        }

        return $items;
    }

    /**
     * "Pedido de urgência aprovado" é diferente de "projeto aprovado".
     *
     * @param  array{title: string, author: string, subject: string, phase: string, result: string, tallies: list<array{label: string, count: int, names: list<string>}>}  $vote
     */
    private function vote(array $vote): string
    {
        $favor = 0;

        foreach ($vote['tallies'] as $tally) {
            $favor += str_contains(mb_strtolower($tally['label']), 'favor') ? $tally['count'] : 0;
        }

        $approved = str_starts_with(mb_strtolower($vote['result']), 'aprovad');
        $what = str_contains(mb_strtolower($vote['phase']), 'urgência') ? 'a urgência do' : 'o';
        $verb = $approved ? 'aprovou' : 'rejeitou';
        $by = match ($vote['author']) {
            '' => '',
            'Prefeitura' => ', da Prefeitura',
            default => ', de '.$vote['author'],
        };
        $title = preg_replace('~\s*-\s*(Executivo|Legislativo)$~u', '', $vote['title']);

        return sprintf('A Câmara %s %s %s%s (%s): %s', $verb, $what, $title, $by, $this->count($favor, 'voto a favor', 'votos a favor'), $this->sentence($vote['subject']));
    }

    /**
     * @param  array{title: string, url: string|null, author: string, text: string}  $item
     */
    private function line(array $item): string
    {
        return $this->sentence($this->request($item['text'])).($item['author'] !== '' ? " ({$item['author']})" : '');
    }

    /**
     * Tira a fórmula de ofício do começo do pedido: "Solicito à Prefeitura de
     * Itapetininga a realização de reparo…" → "Realização de reparo…".
     */
    private function request(string $text): string
    {
        $recipient = '(?:(?:à|ao|a)\s+(?:Prefeitura(?:\s+Municipal)?(?:\s+de\s+Itapetininga)?|Poder\s+Executivo(?:\s+Municipal)?|Executivo\s+Municipal|Prefeito\s+Municipal)[,\s]*(?:através\s+da\s+secretaria\s+competente,?\s*)?)';
        $text = (string) preg_replace('~^DIRIGIDO:.*?-\s*~u', '', trim($text));
        $text = (string) preg_replace('~^[^:]{0,90}(?:Prefeitura|Secretári)[^:]{0,60}:\s*~u', '', $text);
        $text = (string) preg_replace('~^(?:Solicit\w*|Requer\w*)(?:\s+de(?=\s))?\s+'.$recipient.'?~iu', '', $text);
        $text = (string) preg_replace('~^(?:a|o|as|os)\s+(?=\p{Ll})~u', '', $text);
        $text = (string) preg_replace('~,?\s*no\s+município\s+de\s+Itapetininga(?:/SP)?~iu', '', $text);
        $text = (string) preg_replace('~,?\s*(?:neste|deste)\s+Município~iu', '', $text);
        $text = (string) preg_replace('~\s*\(Foto anexada[^)]*\)\.?~iu', '', $text);

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    private function attendance(Session $session): string
    {
        $present = 0;
        $absent = [];

        foreach ($session->attendance as $tally) {
            if (str_starts_with($tally['label'], 'Presente')) {
                $present = $tally['count'];
            } elseif ($tally['names'] !== []) {
                $absent[] = implode(', ', $tally['names']).' ('.mb_strtolower($tally['label']).')';
            }
        }

        $line = sprintf('Presença: %s', $this->count($present, 'vereador', 'vereadores'));
        $line .= $absent !== [] ? '; ausências: '.implode('; ', $absent) : '';
        $line .= $session->closedAt !== null ? sprintf('. A sessão começou às %s e terminou às %s.', $this->hour($session->time), $this->hour($session->closedAt)) : '.';

        return $line;
    }

    /**
     * Uma frase legível: sem CAIXA ALTA, sem prefixos de ofício, com ponto final.
     */
    private function sentence(string $text): string
    {
        $text = trim((string) preg_replace('~^DIRIGIDO:.*?-\s*~u', '', trim($text)));
        $letters = preg_replace('~[^\p{L}]~u', '', $text) ?? '';

        if ($letters !== '' && mb_strtoupper($letters) === $letters) {
            $text = mb_strtoupper(mb_substr($text, 0, 1)).mb_strtolower(mb_substr($text, 1));
        }

        $text = rtrim($text, ' .;');

        return mb_strlen($text) > 280 ? mb_substr($text, 0, mb_strrpos(mb_substr($text, 0, 280), ' ') ?: 280).'…' : $text.'.';
    }

    /**
     * "19:00" → "19h"; "20:21" → "20h21".
     */
    private function hour(string $time): string
    {
        return preg_match('~^(\d{1,2}):(\d{2})~', $time, $m) ? (int) $m[1].'h'.($m[2] === '00' ? '' : $m[2]) : $time;
    }

    private function count(int $n, string $one, string $many): string
    {
        return $n.' '.($n === 1 ? $one : $many);
    }
}
