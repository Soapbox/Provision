<?php

namespace App;

class Replacer
{
    public static function replace(string $text, array $replacements): string
    {
        $keys = array_map(function ($key) {
            return '{{' . $key . '}}';
        }, array_keys($replacements));

        return str_replace($keys, $replacements, $text);
    }
}
