<?php

namespace App\Enums;

/**
 * Health status ladder, worst-last: OK → WARN → CRIT.
 *
 * STALE sits outside the ladder — it means "no data", which is not the same as
 * "bad data". It renders distinctly on the map (gray-striped) but collapses to
 * WARN for the header pill, because "I can't see" deserves attention without
 * claiming an outage.
 */
enum HealthStatus: string
{
    case OK = 'ok';
    case WARN = 'warn';
    case CRIT = 'crit';
    case STALE = 'stale';

    /**
     * Combine statuses, worst wins. Empty input is OK — a node with no checks
     * is not a failing node.
     */
    public static function worstOf(self ...$statuses): self
    {
        $worst = self::OK;

        foreach ($statuses as $status) {
            if ($status->severity() > $worst->severity()) {
                $worst = $status;
            }
        }

        return $worst;
    }

    /**
     * Rank used for worst-of comparison. STALE ranks alongside WARN: missing
     * data must outrank OK, but must never mask a real CRIT elsewhere.
     */
    public function severity(): int
    {
        return match ($this) {
            self::OK => 0,
            self::STALE => 1,
            self::WARN => 2,
            self::CRIT => 3,
        };
    }

    /** The pill has three colours, not four. */
    public function forPill(): self
    {
        return $this === self::STALE ? self::WARN : $this;
    }

    public function isFailing(): bool
    {
        return $this !== self::OK;
    }

    public function label(): string
    {
        return match ($this) {
            self::OK => 'All systems go',
            self::WARN => 'Degraded',
            self::CRIT => 'Outage',
            self::STALE => 'Unknown',
        };
    }

    /** Design-system colour token (docs/design-system.md). */
    public function colorToken(): string
    {
        return match ($this) {
            self::OK => 'emerald',
            self::WARN => 'amber',
            self::CRIT => 'red',
            self::STALE => 'gray',
        };
    }

    /** Process exit code for `health:check --fail-on=...`. */
    public function exitCode(): int
    {
        return match ($this) {
            self::OK => 0,
            self::WARN, self::STALE => 1,
            self::CRIT => 2,
        };
    }
}
