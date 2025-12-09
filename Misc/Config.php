<?php

namespace Misc;

class Config
{
    private static array $data;
    public static function initialize(
        array $data
    ): void
    {
        self::$data = $data;
    }

    public static function get(string $key)
    {
        $data = self::$data;
        $parts = explode('.', $key);
        $find = function (int $i, array $data) use ($parts, &$find) {
            $next = $i + 1;
            if(!isset($parts[$next])) {
                return $data[$parts[$i]] ?? null;
            }
            return (isset($data[$parts[$i]]) && is_array($data[$parts[$i]])) ? $find($next, $data[$parts[$i]]) : null;
        };
        return $find(0, $data);
    }
}