<?php
declare(strict_types=1);

namespace App\Services;

final class PdoDashboardService
{
    public static function achievement(?float $actual, ?float $target, ?float $baseline, string $method, string $direction): ?float
    {
        if ($actual === null || $target === null) {
            return null;
        }

        $value = match ($method) {
            'inverse_ratio' => $actual > 0 ? ($target / $actual) * 100 : null,
            'reduction_from_baseline' => ($baseline !== null && $baseline > 0 && $target > 0)
                ? (((($baseline - $actual) / $baseline) * 100) / $target) * 100
                : null,
            'manual_percentage' => $actual,
            default => $target > 0 ? ($actual / $target) * 100 : null,
        };

        if ($value === null) {
            return null;
        }

        if ($direction === 'lower' && !in_array($method, ['inverse_ratio', 'reduction_from_baseline'], true)) {
            $value = $actual > 0 ? ($target / $actual) * 100 : null;
        }

        return $value === null ? null : round(max(0, $value), 2);
    }

    public static function status(?float $achievement, float $onTrackThreshold = 90.0, ?float $exceededThreshold = 110.0): string
    {
        if ($achievement === null) {
            return 'no_data';
        }
        if ($exceededThreshold !== null && $achievement >= $exceededThreshold) {
            return 'exceeded';
        }
        if ($achievement >= 100) {
            return 'achieved';
        }
        return $achievement >= $onTrackThreshold ? 'on_track' : 'below_target';
    }

    public static function rows(array $records): array
    {
        return array_map(static function (array $row): array {
            $actual = $row['actual_value'] === null ? null : (float) $row['actual_value'];
            $target = $row['target_value'] === null ? null : (float) $row['target_value'];
            $baseline = $row['baseline_value'] === null ? null : (float) $row['baseline_value'];
            $method = (string) ($row['calculation_method'] ?? 'ratio');
            $direction = (string) ($row['direction'] ?? 'higher');
            $achievement = self::achievement($actual, $target, $baseline, $method, $direction);
            $performanceValue = $actual;
            if ($method === 'reduction_from_baseline') {
                $performanceValue = ($actual !== null && $baseline !== null && $baseline > 0)
                    ? round((($baseline - $actual) / $baseline) * 100, 2)
                    : null;
            }
            $threshold = (float) ($row['on_track_threshold'] ?? 90);
            $exceededThreshold = !empty($row['allow_overachievement'])
                ? (float) ($row['exceeded_threshold'] ?? 110)
                : null;

            return [
                'id' => (int) $row['id'],
                'type' => 'PDO',
                'code' => (string) $row['code'],
                'parentCode' => $row['parent_code'] ?? null,
                'component' => (string) $row['component'],
                'name' => (string) $row['name'],
                'definition' => (string) ($row['definition'] ?? ''),
                'unit' => (string) $row['unit'],
                'baseline' => $baseline,
                'year' => $row['fiscal_year'] ?? null,
                'period' => $row['reporting_period'] ?? null,
                'state' => $row['state'] ?? null,
                'target' => $target,
                'result' => $actual,
                'achievement' => $achievement,
                'performanceValue' => $performanceValue,
                'visualAchievement' => $achievement === null ? null : min((float) ($row['achievement_cap'] ?? 100), $achievement),
                'status' => self::status($achievement, $threshold, $exceededThreshold),
                'calculationMethod' => $method,
                'direction' => $direction,
                'frequency' => (string) ($row['frequency'] ?? ''),
                'reportingBasis' => (string) ($row['reporting_basis'] ?? 'cumulative'),
                'reportedAt' => $row['reported_at'] ?? null,
            ];
        }, $records);
    }
}
