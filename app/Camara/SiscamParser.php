<?php

namespace App\Camara;

use App\Support\Text;
use Carbon\CarbonImmutable;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Lê as páginas do SisCam: a lista de sessões, a página de uma sessão
 * (presença e votações) e o roteiro (tudo o que foi lido na sessão).
 */
final class SiscamParser
{
    public function __construct(private string $baseUrl) {}

    /**
     * Sessões da tabela "Recentes", mais novas primeiro.
     *
     * @return list<array{id: int, title: string, date: string, time: string, notes: string}>
     */
    public function sessions(string $html): array
    {
        $sessions = [];

        foreach ((new Crawler(Text::toUtf8($html)))->filter('table tbody tr') as $row) {
            $cells = (new Crawler($row))->filter('td');
            $link = $cells->count() > 0 ? $cells->eq(0)->filter('a[href*="/Sessoes/Sessao/"]') : null;

            if ($link === null || $link->count() === 0 || ! preg_match('~/Sessoes/Sessao/(\d+)~', (string) $link->attr('href'), $m)) {
                continue;
            }

            $date = CarbonImmutable::createFromFormat('d/m/Y', trim($cells->eq(1)->text('')));

            $sessions[] = [
                'id' => (int) $m[1],
                'title' => Text::clean($link->text('')),
                'date' => $date ? $date->format('Y-m-d') : '',
                'time' => trim($cells->eq(2)->text('')),
                'notes' => $this->notes($cells->count() > 5 ? $cells->eq(5)->text('') : ''),
            ];
        }

        return $sessions;
    }

    /**
     * Página da sessão: título, data, presença, votações e o link do roteiro.
     */
    public function session(int $id, string $html): Session
    {
        $html = Text::toUtf8($html);
        // Presença e votações usam a mesma marcação ("A favor (17) - …"): separa as duas partes.
        $parts = preg_split('~<h3><strong>Vota~u', $html, 2) ?: [$html];
        $page = new Crawler($parts[0]);

        $title = Text::clean($page->filter('h3.page-header small')->text(''));
        $date = $this->labeled($page, 'Data');
        $roteiro = $page->filter('a[href*="/Sessoes/Documento/"]');
        $closed = preg_match('~Encerramento\s*-\s*(\d{1,2}:\d{2})~u', Text::clean($parts[0]), $m) ? $m[1] : null;
        $parsedDate = $date !== '' ? CarbonImmutable::createFromFormat('d/m/Y', $date) : null;

        return new Session(
            id: $id,
            title: ltrim($title, '- '),
            date: $parsedDate ? $parsedDate->format('Y-m-d') : '',
            time: $this->labeled($page, 'Hora'),
            notes: $this->notes($this->labeled($page, 'Observações')),
            url: $this->baseUrl.'/Sessoes/Sessao/'.$id,
            agendaUrl: $roteiro->count() > 0 ? $this->absolute((string) $roteiro->attr('href')) : null,
            closedAt: $closed,
            attendance: $this->tallies($page),
            votes: isset($parts[1]) ? $this->votes(new Crawler('<h3><strong>Vota'.$parts[1])) : [],
        );
    }

    /**
     * Roteiro: seções (Respostas, Indicações, Requerimentos…) com seus documentos.
     *
     * @return list<array{section: string, items: list<array{title: string, url: string|null, author: string, text: string}>}>
     */
    public function agenda(string $html): array
    {
        $html = Text::toUtf8($html);
        $start = strpos($html, '<h3>Matérias</h3>');

        if ($start === false) {
            return [];
        }

        $sections = [];

        foreach (array_slice(preg_split('~<h3>~u', substr($html, $start)) ?: [], 2) as $chunk) {
            $crawler = new Crawler('<h3>'.$chunk);
            $name = preg_replace('~^Roteiro\s*-\s*~u', '', Text::clean($crawler->filter('h3')->text(''))) ?? '';
            $items = [];

            foreach ($crawler->filter('.data-list-item > p') as $paragraph) {
                $p = new Crawler($paragraph);
                $link = $p->filter('strong a');

                if ($link->count() === 0) {
                    continue;
                }

                $rest = Text::clean(str_replace($p->filter('strong')->outerHtml(), '', $p->html('')));
                [$author, $text] = self::splitAuthor($rest);

                $items[] = [
                    'title' => Text::clean($link->text('')),
                    'url' => $this->absolute((string) $link->attr('href')),
                    'author' => $author,
                    'text' => $text,
                ];
            }

            if ($name !== '' && $items !== []) {
                $sections[] = ['section' => $name, 'items' => $items];
            }
        }

        return $sections;
    }

    /**
     * Separa "de autoria do Vereador Fulano, Beltrano. Solicita …" em autores e texto.
     * Nomes vêm separados por vírgula; o autor termina no primeiro ponto ou no
     * primeiro trecho que não parece nome ("Dispõe sobre…", "enviado por…").
     *
     * @return array{0: string, 1: string}
     */
    public static function splitAuthor(string $text): array
    {
        $text = ltrim($text, ", \t\n");

        if (! preg_match('~^de autoria d[aeo]s?\s+(?:Vereador(?:a|es|as)?\s+)?~u', $text, $m)) {
            return ['', $text];
        }

        $rest = substr($text, strlen($m[0]));
        $names = [];
        $name = '~^(?:[\p{Lu}][\p{L}\'’.-]*|d[aeo]s?|e)(?:\s+(?:[\p{Lu}][\p{L}\'’.-]*|d[aeo]s?|e))*$~u';

        while (preg_match('~^([^,.]*)([,.])\s*~u', $rest, $part)) {
            $segment = trim($part[1]);

            if ($segment !== '' && (! preg_match($name, $segment) || count(preg_split('~\s+~u', $segment) ?: []) > 10)) {
                break;
            }

            if ($segment !== '') {
                $names[] = $segment;
            }

            $rest = substr($rest, strlen($part[0]));

            // Um ponto encerra a autoria; uma vírgula pode trazer mais um nome.
            if ($part[2] === '.' || $segment === '') {
                break;
            }
        }

        return [implode(', ', $names), trim($rest)];
    }

    /**
     * Votações: título, autoria, assunto, fase, contagem por opção e resultado.
     *
     * @return list<array{title: string, url: string|null, author: string, subject: string, phase: string, result: string, tallies: list<array{label: string, count: int, names: list<string>}>}>
     */
    private function votes(Crawler $crawler): array
    {
        $votes = [];

        foreach ($crawler->filter('.data-list-item') as $node) {
            $item = new Crawler($node);
            $link = $item->filter('p strong a');

            $votes[] = [
                'title' => Text::clean($link->text('')),
                'url' => $link->count() > 0 ? $this->absolute((string) $link->attr('href')) : null,
                'author' => $this->labeled($item, 'Autoria'),
                'subject' => $this->labeled($item, 'Assunto'),
                'phase' => $this->labeled($item, 'Fase'),
                'result' => $this->labeled($item, 'Resultado'),
                'tallies' => $this->tallies($item),
            ];
        }

        return $votes;
    }

    /**
     * Linhas "Presentes (18) - Fulano, Beltrano" ou "A favor (17) - …".
     *
     * @return list<array{label: string, count: int, names: list<string>}>
     */
    private function tallies(Crawler $crawler): array
    {
        $tallies = [];

        foreach ($crawler->filter('p') as $paragraph) {
            $text = Text::clean((new Crawler($paragraph))->text(''));

            if (preg_match('~^([\p{L} ]+?)\s*\((\d+)\)\s*(?:-\s*(.*))?$~u', $text, $m)) {
                $tallies[] = [
                    'label' => $m[1],
                    'count' => (int) $m[2],
                    'names' => array_values(array_filter(array_map('trim', explode(',', $m[3] ?? '')))),
                ];
            }
        }

        return $tallies;
    }

    /**
     * Valor de um campo "<strong>Rótulo:</strong> valor".
     */
    private function labeled(Crawler $crawler, string $label): string
    {
        foreach ($crawler->filter('p') as $paragraph) {
            $text = Text::clean((new Crawler($paragraph))->text(''));

            if (str_starts_with($text, $label.':')) {
                return trim(substr($text, strlen($label) + 1));
            }
        }

        return '';
    }

    private function notes(string $notes): string
    {
        return trim(Text::clean($notes), " -\t\n");
    }

    private function absolute(string $href): string
    {
        return str_starts_with($href, 'http') ? $href : rtrim($this->baseUrl, '/').'/'.ltrim($href, '/');
    }
}
