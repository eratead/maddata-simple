<?php

namespace App\Dtos;

use App\Enums\HealthStatus;

/**
 * One row in the health catalog (docs/specs/system-health-monitor.md §3).
 *
 * Deliberately dumb: threshold comparison lives in the check classes, this
 * only carries the outcome. `node` must always be the node the check really
 * belongs to — a CRIT tagged to the wrong node is invisible on the map.
 */
final readonly class HealthCheckResult
{
    public function __construct(
        public string $key,
        public string $label,
        public HealthStatus $status,
        public string $node,
        public ?string $value = null,
        public ?string $threshold = null,
        public ?string $link = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            label: $data['label'],
            status: HealthStatus::from($data['status']),
            node: $data['node'],
            value: $data['value'] ?? null,
            threshold: $data['threshold'] ?? null,
            link: $data['link'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'status' => $this->status->value,
            'node' => $this->node,
            'value' => $this->value,
            'threshold' => $this->threshold,
            'link' => $this->link,
        ];
    }
}
