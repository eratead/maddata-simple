<?php

namespace App\Services\Health\Checks;

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Services\Health\HealthFormat;
use App\Services\Health\HealthMarkers;
use App\Services\Health\HostFacts;
use Throwable;

/**
 * B1-B4 — backups.
 *
 * Two independent sources on purpose: scripts/backup-production.sh writes a
 * marker when it finishes, and the facts script separately stats the backup
 * directory. A marker that never appears at all has to be detectable, and only
 * the filesystem can prove that.
 *
 * B2 is the check a naive "did the backup run?" monitor misses entirely:
 * mysqldump can exit 0 having written half a database. A dump that suddenly
 * weighs half what the last seven did is the signal.
 */
class BackupCheck extends HealthCheck
{
    public function __construct(private readonly HostFacts $facts) {}

    public function run(): array
    {
        $marker = $this->marker();
        $backups = $this->backupsFromFacts();

        // "I cannot read the marker" and "the marker is not there" are
        // different facts, and collapsing them cost a live false OUTAGE: the
        // marker sat inside a 700 root directory, so the scheduler (www-data)
        // reported CRIT while a root shell reported OK on the same box.
        if ($marker === null && $this->markerExistsButUnreadable()) {
            return [
                new HealthCheckResult(
                    key: 'B1',
                    label: 'Local backup age',
                    status: HealthStatus::STALE,
                    node: 'backups',
                    value: 'marker unreadable by '.(function_exists('posix_getpwuid') && function_exists('posix_geteuid')
                        ? (posix_getpwuid(posix_geteuid())['name'] ?? 'this user')
                        : 'this user').' — check directory permissions',
                    threshold: 'needs traverse+read on '.dirname((string) config('health.backup_marker_path')),
                    link: config('health.links.backups'),
                ),
                $this->sizeSanity($backups, null),
                new HealthCheckResult(
                    key: 'B3',
                    label: 'Off-site backup',
                    status: HealthStatus::STALE,
                    node: 'backups',
                    value: 'marker unreadable',
                    threshold: 'needs traverse+read on '.dirname((string) config('health.backup_marker_path')),
                    link: config('health.links.backups'),
                ),
                $this->restoreDrill(),
            ];
        }

        return [
            $this->localAge($marker, $backups),
            $this->sizeSanity($backups, $marker),
            $this->offsite($marker),
            $this->restoreDrill(),
        ];
    }

    /** B1 — an unverifiable backup is not a backup. */
    private function localAge(?array $marker, array $backups): HealthCheckResult
    {
        return $this->guard('B1', 'Local backup age', 'backups', function () use ($marker, $backups) {
            $thresholds = $this->thresholds('backup_age');

            if ($marker === null && $backups !== []) {
                return new HealthCheckResult(
                    key: 'B1',
                    label: 'Local backup age',
                    status: HealthStatus::CRIT,
                    node: 'backups',
                    value: 'marker missing ('.count($backups).' backup dirs on disk)',
                    threshold: 'backup script must write '.basename((string) config('health.backup_marker_path')),
                    link: config('health.links.backups'),
                );
            }

            $ts = $marker['ts'] ?? ($backups[0]['ts'] ?? null);

            return $this->ageResult(
                key: 'B1',
                label: 'Local backup age',
                node: 'backups',
                timestamp: is_numeric($ts) ? (int) $ts : null,
                thresholds: $thresholds,
                missingValue: 'no backup recorded',
                link: config('health.links.backups'),
            );
        });
    }

    /** B2 — silent truncation detector. Lower is worse. */
    private function sizeSanity(array $backups, ?array $marker): HealthCheckResult
    {
        return $this->guard('B2', 'Backup size sanity', 'backups', function () use ($backups, $marker) {
            $thresholds = $this->thresholds('backup_size_pct_of_median');
            $backups = $this->completedOnly($backups, $marker);

            $sizes = array_values(array_filter(
                array_map(fn ($b) => (int) ($b['bytes'] ?? 0), $backups),
                fn ($bytes) => $bytes > 0
            ));

            // Needs the newest plus at least three to compare against; below
            // that a "median" is noise and would flap on normal variation.
            if (count($sizes) < 4) {
                return new HealthCheckResult(
                    key: 'B2',
                    label: 'Backup size sanity',
                    status: HealthStatus::OK,
                    node: 'backups',
                    value: count($sizes) === 0 ? 'no history' : 'insufficient history ('.count($sizes).' of 4)',
                    threshold: $this->describeThreshold($thresholds, '% of median', '≤'),
                );
            }

            $newest = array_shift($sizes);
            $median = $this->median($sizes);
            $pct = $median > 0 ? $newest / $median * 100 : 100;

            return new HealthCheckResult(
                key: 'B2',
                label: 'Backup size sanity',
                status: $this->evaluateUnder($pct, $thresholds),
                node: 'backups',
                value: HealthFormat::bytes($newest).' vs '.HealthFormat::bytes((int) $median).' median ('.HealthFormat::percent($pct).')',
                threshold: $this->describeThreshold($thresholds, '% of median', '≤'),
                link: config('health.links.backups'),
            );
        });
    }

    /** B3 — off-site copy in DO Spaces. A local-only backup dies with the droplet. */
    private function offsite(?array $marker): HealthCheckResult
    {
        return $this->guard('B3', 'Off-site backup', 'backups', function () use ($marker) {
            $thresholds = $this->thresholds('backup_age');

            if ($marker === null) {
                return new HealthCheckResult(
                    key: 'B3',
                    label: 'Off-site backup',
                    status: HealthStatus::STALE,
                    node: 'backups',
                    value: 'no backup marker',
                    threshold: $this->describeAgeThreshold($thresholds),
                    link: config('health.links.backups'),
                );
            }

            if (! ($marker['remote_ok'] ?? false)) {
                return new HealthCheckResult(
                    key: 'B3',
                    label: 'Off-site backup',
                    status: HealthStatus::WARN,
                    node: 'backups',
                    value: 'last upload failed',
                    threshold: 'DO Spaces upload must succeed',
                    link: config('health.links.backups'),
                );
            }

            $result = $this->ageResult(
                key: 'B3',
                label: 'Off-site backup',
                node: 'backups',
                timestamp: is_numeric($marker['ts'] ?? null) ? (int) $marker['ts'] : null,
                thresholds: $thresholds,
                missingValue: 'never uploaded',
                link: config('health.links.backups'),
            );

            $bytes = (int) ($marker['remote_bytes'] ?? 0);

            return new HealthCheckResult(
                key: $result->key,
                label: $result->label,
                status: $result->status,
                node: $result->node,
                value: $result->value.($bytes > 0 ? ' · '.HealthFormat::bytes($bytes) : ''),
                threshold: $result->threshold,
                link: $result->link,
            );
        });
    }

    /** B4 — a restore path nobody has walked in a year is a hope, not a plan. */
    private function restoreDrill(): HealthCheckResult
    {
        return $this->guard('B4', 'Restore drill age', 'backups', function () {
            $days = $this->thresholds('restore_drill_age_days');
            $thresholds = array_map(fn ($d) => $d * 86400, $days);

            $marker = $this->store()->get(HealthMarkers::RESTORE_DRILL);
            $ts = is_array($marker) ? ($marker['ts'] ?? null) : $marker;

            return $this->ageResult(
                key: 'B4',
                label: 'Restore drill age',
                node: 'backups',
                timestamp: is_numeric($ts) ? (int) $ts : null,
                thresholds: $thresholds,
                missingStatus: HealthStatus::WARN,
                missingValue: 'never recorded — run health:mark-restore-drill',
                link: config('health.links.backups'),
            );
        });
    }

    /**
     * Drop any backup directory newer than the last completed backup.
     *
     * The facts cron stats the backup directory every minute, including while
     * a backup is being written — so during the nightly run the newest entry is
     * a half-finished directory. Comparing that against the median reported a
     * CRIT "the dump shrank" every single night. The marker is written last, so
     * anything newer than it is still in flight.
     */
    private function completedOnly(array $backups, ?array $marker): array
    {
        if (! isset($marker['ts']) || ! is_numeric($marker['ts'])) {
            return $backups;
        }

        $completedAt = (int) $marker['ts'];

        return array_values(array_filter(
            $backups,
            fn ($backup) => (int) ($backup['ts'] ?? 0) <= $completedAt
        ));
    }

    /** Newest first, as the facts script writes them. */
    private function backupsFromFacts(): array
    {
        $backups = $this->facts->get('backups');

        return is_array($backups) ? array_values($backups) : [];
    }

    /**
     * The marker file is present but this process cannot read it — almost
     * always a directory-permission problem rather than a backup problem.
     */
    private function markerExistsButUnreadable(): bool
    {
        try {
            $path = config('health.backup_marker_path');

            return is_string($path) && file_exists($path) && ! is_readable($path);
        } catch (Throwable) {
            return false;
        }
    }

    private function marker(): ?array
    {
        try {
            $path = config('health.backup_marker_path');

            if (! is_string($path) || ! is_readable($path)) {
                return null;
            }

            $decoded = json_decode((string) file_get_contents($path), true);

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function median(array $values): float
    {
        sort($values);
        $count = count($values);

        if ($count === 0) {
            return 0;
        }

        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : (float) $values[$middle];
    }
}
