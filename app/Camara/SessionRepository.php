<?php

namespace App\Camara;

use App\Storage\JsonFile;

/**
 * Sessões da Câmara salvas em data/camara/sessoes/<id>.json (uma por arquivo,
 * para o histórico do git mostrar o que mudou em cada sessão).
 */
final class SessionRepository
{
    public function __construct(private string $directory) {}

    public function find(int $id): ?Session
    {
        $data = JsonFile::read($this->path($id));

        return $data === null ? null : Session::fromArray($data);
    }

    public function save(Session $session): bool
    {
        return JsonFile::write($this->path($session->id), $session->toArray());
    }

    /**
     * @return list<Session> mais recentes primeiro
     */
    public function all(): array
    {
        $sessions = array_map(
            fn (string $file) => Session::fromArray(JsonFile::read($file) ?? []),
            glob($this->directory.'/*.json') ?: [],
        );

        usort($sessions, fn (Session $a, Session $b) => [$b->date, $b->id] <=> [$a->date, $a->id]);

        return $sessions;
    }

    private function path(int $id): string
    {
        return $this->directory.'/'.$id.'.json';
    }
}
