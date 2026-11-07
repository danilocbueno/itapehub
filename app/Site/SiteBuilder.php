<?php

namespace App\Site;

use App\Camara\SessionCover;
use App\Camara\SessionNews;
use App\Data\Source;
use App\Storage\ItemRepository;
use App\Storage\JsonFile;
use App\Storage\StateRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Filesystem\Filesystem;

/**
 * Gera o site estático em build/: home, arquivo por dia, fontes e feed.
 */
final class SiteBuilder
{
    public function __construct(
        private ViewFactory $views,
        private Filesystem $files,
    ) {}

    /**
     * @return array{pages: int, clusters: int, home: int}
     */
    public function build(string $dataPath, string $buildPath, CarbonImmutable $now): array
    {
        $items = new ItemRepository($dataPath.'/items');
        $state = new StateRepository($dataPath.'/state.json');
        $sources = Source::all();
        // Notícias das sessões da Câmara, escritas pelo ItapêHub: entram na linha do tempo como as outras.
        $sessionNews = SessionNews::load($dataPath.'/camara/noticias', (string) config('itape.site.url'));
        // Capa de cada sessão (número, tipo e dados), usada no card no lugar da foto simples.
        $sessionCovers = [];

        foreach ($sessionNews as $news) {
            $sessionCovers[$news['item']->url] = [
                'cover' => SessionCover::from((string) $news['news']['title'], $news['session']),
                'date' => $news['item']->date(),
            ];
        }

        $clusters = Timeline::clusters([...$items->all(), ...array_column($sessionNews, 'item')]);
        $days = Timeline::byDay($clusters);
        $home = $this->homeClusters($clusters, $now);
        $events = Events::upcoming($clusters, $now, (int) config('itape.agenda.days'));

        $shared = [
            'site' => config('itape.site'),
            'sources' => [...$sources, SessionNews::SOURCE => SessionNews::source()],
            'sessionCovers' => $sessionCovers,
            'now' => $now,
            'problems' => $this->problems($sources, $state, $now),
            'themes' => Themes::all(),
            'currentTheme' => null,
            'css' => $this->files->get(resource_path('assets/site.css')),
            'js' => $this->files->get(resource_path('assets/site.js')),
        ];

        $this->files->ensureDirectoryExists($buildPath);
        $this->files->cleanDirectory($buildPath);
        $pages = 0;

        // A capa pega as três notícias mais recentes, mesmo que venham de dias diferentes.
        $front = array_slice($home, 0, 3);

        $this->write($buildPath.'/index.html', 'home', [
            ...$shared,
            'front' => $front,
            'frontDay' => isset($front[0]) ? $front[0]->date()->format('Y-m-d') : null,
            'days' => Timeline::byDay(array_slice($home, 3)),
            'weather' => JsonFile::read($dataPath.'/weather.json'),
            'alerts' => JsonFile::read($dataPath.'/alerts.json')['alerts'] ?? [],
            'events' => array_slice($events, 0, 3),
        ]);
        $pages++;

        $dayKeys = array_keys($days);

        foreach ($dayKeys as $i => $day) {
            $this->write($buildPath.'/arquivo/'.str_replace('-', '/', $day).'/index.html', 'archive-day', [
                ...$shared,
                'day' => $day,
                'date' => CarbonImmutable::parse($day, $now->getTimezone()),
                'clusters' => $days[$day],
                'newer' => $dayKeys[$i - 1] ?? null,
                'older' => $dayKeys[$i + 1] ?? null,
            ]);
            $pages++;
        }

        foreach (Themes::all() as $slug => $theme) {
            $themed = array_filter($clusters, fn (Cluster $c) => in_array($slug, Themes::for($c->primary), true));

            $this->write($buildPath."/tema/{$slug}/index.html", 'theme', [
                ...$shared,
                'currentTheme' => $slug,
                'theme' => $theme,
                'clusters' => array_slice(array_values($themed), 0, (int) config('itape.theme_page.items')),
            ]);
            $pages++;
        }

        $months = [];

        foreach ($days as $day => $dayClusters) {
            $months[substr($day, 0, 7)][$day] = count($dayClusters);
        }

        $this->write($buildPath.'/agenda/index.html', 'agenda', [...$shared, 'events' => $events]);
        $pages++;

        $digest = Digest::select($clusters, $now);
        $this->write($buildPath.'/hoje/index.html', 'today', [
            ...$shared,
            'clusters' => $digest,
            'text' => Digest::text($digest, $now, $shared['site']['url'], fn (string $key) => isset($sources[$key]) ? $sources[$key]->short : $key),
        ]);
        $pages++;

        $this->write($buildPath.'/arquivo/index.html', 'archive-index', [...$shared, 'months' => $months]);
        foreach ($sessionNews as $news) {
            $this->write($buildPath.'/camara/'.$news['slug'].'/index.html', 'camara-session', [
                ...$shared,
                'slug' => $news['slug'],
                'news' => $news['news'],
                'session' => $news['session'],
                'date' => $news['item']->date(),
                'cover' => $sessionCovers[$news['item']->url]['cover'],
            ]);
            $pages++;
        }

        $this->write($buildPath.'/fontes/index.html', 'sources', [
            ...$shared,
            'sources' => $sources,
            'state' => $state->sources(),
            'counts' => array_count_values(array_map(fn ($item) => $item->source, array_filter($items->all(), fn ($item) => ! $item->hidden))),
        ]);
        $this->write($buildPath.'/404.html', 'not-found', $shared);
        $this->write($buildPath.'/feed.xml', 'feed', [
            ...$shared,
            'clusters' => array_slice($clusters, 0, (int) config('itape.feed.items')),
        ]);
        $pages += 4;

        // Fontes servidas pelo próprio site (sem enviar o IP dos visitantes a terceiros).
        $this->files->copyDirectory(resource_path('assets/fonts'), $buildPath.'/assets/fonts');
        $this->files->copy(resource_path(SessionNews::IMAGE), $buildPath.'/'.SessionNews::IMAGE);

        $this->files->put($buildPath.'/.nojekyll', '');
        $this->files->put($buildPath.'/robots.txt', "User-agent: *\nAllow: /\n");

        if ($domain = config('itape.site.domain')) {
            $this->files->put($buildPath.'/CNAME', $domain."\n");
        }

        return ['pages' => $pages, 'clusters' => count($clusters), 'home' => count($home)];
    }

    /**
     * Últimos N dias; em períodos fracos, completa até um mínimo de notícias.
     *
     * @param  list<Cluster>  $clusters
     * @return list<Cluster>
     */
    private function homeClusters(array $clusters, CarbonImmutable $now): array
    {
        $from = $now->startOfDay()->subDays((int) config('itape.home.days') - 1);
        $recent = array_values(array_filter($clusters, fn (Cluster $c) => $c->date() >= $from));
        $min = (int) config('itape.home.min_clusters');

        return count($recent) >= $min ? $recent : array_slice($clusters, 0, $min);
    }

    /**
     * Fontes com falhas seguidas ou sem coleta recente.
     *
     * @param  array<string, Source>  $sources
     * @return list<string>
     */
    private function problems(array $sources, StateRepository $state, CarbonImmutable $now): array
    {
        $problems = [];

        foreach ($sources as $key => $source) {
            if (! $source->enabled) {
                continue;
            }

            $info = $state->source($key);
            $last = isset($info['last_success']) ? CarbonImmutable::parse($info['last_success']) : null;

            if (($info['errors'] ?? 0) >= 3 || ($last !== null && $last->lessThan($now->subHours(3)))) {
                $problems[] = $source->short;
            }
        }

        return $problems;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function write(string $path, string $view, array $data): void
    {
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $this->views->make($view, $data)->render());
    }
}
