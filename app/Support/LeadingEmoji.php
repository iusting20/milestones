<?php

namespace App\Support;

class LeadingEmoji
{
    /**
     * If $title starts with an emoji grapheme cluster (Extended_Pictographic,
     * including VS/ZWJ/skin-tone/flag sequences), return [emoji, remainder]
     * with following whitespace stripped from the remainder. Otherwise null.
     * Plain symbols like ◆ and letters/digits are not treated as emoji.
     *
     * @return array{emoji: string, title: string}|null
     */
    public static function split(string $title): ?array
    {
        if ($title === '') {
            return null;
        }

        if (! preg_match('/^(\X)/u', $title, $matches)) {
            return null;
        }

        $cluster = $matches[1];

        if (! self::isEmojiCluster($cluster)) {
            return null;
        }

        $remainder = preg_replace('/^\s+/u', '', substr($title, strlen($cluster))) ?? '';

        return [
            'emoji' => $cluster,
            'title' => $remainder,
        ];
    }

    public static function isEmojiCluster(string $cluster): bool
    {
        if ($cluster === '') {
            return false;
        }

        // Flags: a pair of regional indicator symbols forms one flag grapheme.
        if (preg_match('/^\p{Regional_Indicator}{2}$/u', $cluster) === 1) {
            return true;
        }

        // Emoji presentation / pictographic (covers ZWJ sequences, skin tones, keycaps, etc.)
        return preg_match('/\p{Extended_Pictographic}/u', $cluster) === 1;
    }
}
