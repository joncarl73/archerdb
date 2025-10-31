<?php

namespace App\Support;

class RulesetResolver
{
    // Deep merge: event overrides win; arrays of scalars overwrite; arrays of objects merge by 'id' if present.
    public static function deepMerge(array $base, array $ovr): array
    {
        foreach ($ovr as $k => $v) {
            if (! array_key_exists($k, $base)) {
                $base[$k] = $v;

                continue;
            }
            if (is_array($v) && is_array($base[$k])) {
                $base[$k] = self::mergeNode($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }

        return $base;
    }

    protected static function mergeNode(array $a, array $b): array
    {
        $isAssocA = self::isAssoc($a);
        $isAssocB = self::isAssoc($b);
        if ($isAssocA && $isAssocB) {
            return self::deepMerge($a, $b);
        }
        // arrays: prefer override unless both are list of objects with ids
        if (self::listHasIds($a) && self::listHasIds($b)) {
            $byId = [];
            foreach ($a as $it) {
                $byId[$it['id']] = $it;
            }
            foreach ($b as $it) {
                $byId[$it['id']] = self::deepMerge($byId[$it['id']] ?? [], $it);
            }

            return array_values($byId);
        }

        return $b; // scalar lists: replace
    }

    protected static function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    protected static function listHasIds(array $arr): bool
    {
        return ! empty($arr) && is_array($arr[0] ?? null) && array_key_exists('id', $arr[0]);
    }
}
