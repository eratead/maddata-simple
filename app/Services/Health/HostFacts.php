<?php

namespace App\Services\Health;

use Throwable;

/**
 * The only reader of the host-facts file written by scripts/health-facts.sh.
 *
 * Nothing here executes a shell command — that is the whole point of the file
 * hand-off (spec §7, zero-grant design). Never throws: a missing, unreadable or
 * malformed file reads as null, which the host check surfaces as "monitoring
 * blind" rather than as a crash.
 *
 * Memoized per resolution so a single snapshot build touches the disk once.
 */
class HostFacts
{
    private ?array $facts = null;

    private bool $loaded = false;

    public function read(): ?array
    {
        if ($this->loaded) {
            return $this->facts;
        }

        $this->loaded = true;
        $this->facts = $this->load();

        return $this->facts;
    }

    /** Seconds since the facts were written, or null when there are none. */
    public function ageSeconds(): ?int
    {
        $ts = $this->get('ts');

        return is_numeric($ts) ? max(0, now()->getTimestamp() - (int) $ts) : null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->read()[$key] ?? $default;
    }

    /** Forget the memoized copy — used by long-running processes and tests. */
    public function flush(): void
    {
        $this->loaded = false;
        $this->facts = null;
    }

    private function load(): ?array
    {
        try {
            $path = config('health.facts_path');

            if (! is_string($path) || ! is_readable($path)) {
                return null;
            }

            $raw = file_get_contents($path);

            if ($raw === false || $raw === '') {
                return null;
            }

            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }
}
