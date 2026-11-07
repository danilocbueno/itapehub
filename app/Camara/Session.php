<?php

namespace App\Camara;

/**
 * Uma sessão da Câmara como o SisCam publica: data, presença, votações e o
 * roteiro (tudo o que foi lido: pedidos, projetos, respostas, correspondências).
 *
 * @phpstan-type Doc array{title: string, url: string|null, author: string, text: string}
 * @phpstan-type Vote array{title: string, url: string|null, author: string, subject: string, phase: string, result: string, tallies: list<array{label: string, count: int, names: list<string>}>}
 */
final class Session
{
    /**
     * @param  list<array{label: string, count: int, names: list<string>}>  $attendance
     * @param  list<Vote>  $votes
     * @param  list<array{section: string, items: list<Doc>}>  $agenda
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $date,
        public string $time = '',
        public string $notes = '',
        public string $url = '',
        public ?string $agendaUrl = null,
        public ?string $closedAt = null,
        public array $attendance = [],
        public array $votes = [],
        public array $agenda = [],
    ) {}

    /**
     * A sessão terminou e o SisCam já registrou o encerramento.
     */
    public function complete(): bool
    {
        return $this->closedAt !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'date' => $this->date,
            'time' => $this->time,
            'notes' => $this->notes,
            'url' => $this->url,
            'agenda_url' => $this->agendaUrl,
            'closed_at' => $this->closedAt,
            'attendance' => $this->attendance,
            'votes' => $this->votes,
            'agenda' => $this->agenda,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            title: (string) $data['title'],
            date: (string) $data['date'],
            time: (string) ($data['time'] ?? ''),
            notes: (string) ($data['notes'] ?? ''),
            url: (string) ($data['url'] ?? ''),
            agendaUrl: $data['agenda_url'] ?? null,
            closedAt: $data['closed_at'] ?? null,
            attendance: $data['attendance'] ?? [],
            votes: $data['votes'] ?? [],
            agenda: $data['agenda'] ?? [],
        );
    }
}
