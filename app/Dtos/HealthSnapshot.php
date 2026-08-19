<?php

namespace App\Dtos;

use App\Enums\HealthStatus;
use Carbon\CarbonImmutable;

/**
 * The whole system's health at one instant.
 *
 * `toArray()` IS the JSON contract polled by /admin/monitor/data and emitted by
 * `health:check --json`. Treat it as public API — the Blade map, the Alpine
 * poller and any external consumer all read this shape:
 *
 *   {
 *     "overall":      "ok|warn|crit|stale",
 *     "generated_at": "2026-08-19T10:15:00+00:00",
 *     "nodes": [
 *       {"key":"data","label":"Data","status":"warn","check_keys":["D1","D2"]}
 *     ],
 *     "checks": [
 *       {"key":"D1","label":"MySQL reachable","status":"ok","node":"data",
 *        "value":"12ms","threshold":"warn >100ms","link":null}
 *     ]
 *   }
 *
 * Nodes are emitted in config('health.nodes') order so the UI's columns stay
 * fixed regardless of which checks happened to run.
 */
final readonly class HealthSnapshot
{
    /**
     * @param  array<int, array{key: string, label: string, status: HealthStatus, check_keys: array<int, string>}>  $nodes
     * @param  array<int, HealthCheckResult>  $checks
     */
    public function __construct(
        public HealthStatus $overall,
        public array $nodes,
        public array $checks,
        public CarbonImmutable $generatedAt,
    ) {}

    public static function fromResults(array $checks, ?CarbonImmutable $generatedAt = null): self
    {
        $labels = config('health.nodes', []);
        $nodes = [];

        foreach ($labels as $key => $label) {
            $own = array_values(array_filter($checks, fn (HealthCheckResult $c) => $c->node === $key));

            if ($own === []) {
                continue;
            }

            $nodes[] = [
                'key' => $key,
                'label' => $label,
                'status' => HealthStatus::worstOf(...array_map(fn ($c) => $c->status, $own)),
                'check_keys' => array_map(fn ($c) => $c->key, $own),
            ];
        }

        return new self(
            overall: HealthStatus::worstOf(...array_map(fn (HealthCheckResult $c) => $c->status, $checks)),
            nodes: $nodes,
            checks: array_values($checks),
            generatedAt: $generatedAt ?? CarbonImmutable::now(),
        );
    }

    /** Failing checks, worst first — what the UI auto-expands and the alert mail lists. */
    public function failing(): array
    {
        $failing = array_values(array_filter($this->checks, fn (HealthCheckResult $c) => $c->status->isFailing()));

        usort($failing, fn ($a, $b) => $b->status->severity() <=> $a->status->severity());

        return $failing;
    }

    /**
     * Failing checks that are allowed to wake somebody up.
     *
     * A check's NODE decides its delivery channel: anything in
     * health.alert_excluded_nodes reports on the page and in the CLI, but is
     * routed to the weekly dependency digest instead of the alert mail. A
     * composer advisory is real, and it is not a 2am page — mixing the two
     * teaches you to ignore outage mail.
     *
     * Node membership rather than a per-check flag: a flag would need setting
     * in a dozen places and would drift the first time someone added a
     * thirteenth check. The node is already what the UI groups by, and the
     * exclusion list is one config line to audit.
     *
     * @return array<int, HealthCheckResult>
     */
    public function alertable(): array
    {
        return $this->partitionFailing(alertable: true);
    }

    /**
     * The other half: failing checks the weekly digest owns.
     *
     * @return array<int, HealthCheckResult>
     */
    public function digestable(): array
    {
        return $this->partitionFailing(alertable: false);
    }

    /**
     * What health:alert decides on. Deliberately NOT `overall`: `overall` stays
     * honest, so a CRIT advisory still turns the page and the header pill red —
     * the page is pull, alerting is push, and only the push half is filtered.
     */
    public function alertStatus(): HealthStatus
    {
        return HealthStatus::worstOf(
            ...array_map(fn (HealthCheckResult $c) => $c->status, $this->alertable())
        );
    }

    /** @return array<int, HealthCheckResult> */
    private function partitionFailing(bool $alertable): array
    {
        $excluded = (array) config('health.alert_excluded_nodes', []);

        return array_values(array_filter(
            $this->failing(),
            fn (HealthCheckResult $c) => in_array($c->node, $excluded, true) !== $alertable
        ));
    }

    /**
     * Stable identity of the current problem set, used by health:alert to tell
     * "same problem, still going" from "something new broke".
     *
     * Built from alertable() so that a digest-only check flipping can never
     * change the alert signature — otherwise a new advisory would read as
     * "something new broke" and page someone about a dependency.
     */
    public function signature(): string
    {
        $keys = array_map(fn (HealthCheckResult $c) => $c->key.':'.$c->status->value, $this->alertable());
        sort($keys);

        return $keys === [] ? 'all-ok' : implode('|', $keys);
    }

    /**
     * Rehydrate from toArray(). The cache stores the ARRAY, not the object:
     * a serialized DTO written before a deploy that changes this class would
     * fatal on unserialize, and the monitor must survive its own deploys.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            overall: HealthStatus::from($data['overall']),
            nodes: array_map(
                fn (array $n) => [...$n, 'status' => HealthStatus::from($n['status'])],
                $data['nodes'] ?? []
            ),
            checks: array_map(
                fn (array $c) => HealthCheckResult::fromArray($c),
                $data['checks'] ?? []
            ),
            generatedAt: CarbonImmutable::parse($data['generated_at']),
        );
    }

    public function toArray(): array
    {
        return [
            'overall' => $this->overall->value,
            'generated_at' => $this->generatedAt->toIso8601String(),
            'nodes' => array_map(fn ($n) => [...$n, 'status' => $n['status']->value], $this->nodes),
            'checks' => array_map(fn (HealthCheckResult $c) => $c->toArray(), $this->checks),
        ];
    }
}
