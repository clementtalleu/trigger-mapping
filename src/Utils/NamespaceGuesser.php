<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Utils;

final class NamespaceGuesser
{
    /**
     * @param list<string> $candidates
     */
    public static function findClosest(string $target, array $candidates): ?string
    {
        [, $t] = self::normalizeNamespace($target);
        $bestNs = null;
        $bestCommon = -1;
        $bestDist = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            [$cOrig, $c] = self::normalizeNamespace($candidate);
            $common = self::commonHead($t, $c);
            $dist = (count($t) - $common) + (count($c) - $common);

            if ($common > $bestCommon || ($common === $bestCommon && $dist < $bestDist)) {
                $bestCommon = $common;
                $bestDist = $dist;
                $bestNs = $cOrig;
            }
        }

        return $bestNs;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private static function normalizeNamespace(string $ns): array
    {
        $original = trim($ns, "\\ \t\n\r\0\x0B");
        $parts = array_values(array_filter(explode('\\', $original)));
        $lower = array_map('strtolower', $parts);

        return [$original, $lower];
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     */
    private static function commonHead(array $a, array $b): int
    {
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            if ($a[$i] !== $b[$i]) {
                return $i;
            }
        }

        return $n;
    }
}
