<?php
declare(strict_types=1);
namespace App\Services;

final class Avatar
{
    public static function svg(string $seed): string
    {
        $hash = hash('sha256', $seed);
        $palette = ['#b4d5fc','#c6b9ef','#ffc2ab','#b8e5d4','#f8da90','#f4b4d0'];
        $bg = $palette[hexdec($hash[0]) % count($palette)];
        $skin = ['#f2c9a0','#c98961','#87573f'][hexdec($hash[1]) % 3];
        $angle = (hexdec($hash[2]) % 5 - 2) * 8;
        $eye = 43 + hexdec($hash[3]) % 5;
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" role="img" aria-label="Generated thumbs avatar">'
            . '<rect width="80" height="80" rx="22" fill="' . $bg . '"/>'
            . '<g transform="rotate(' . $angle . ' 40 40)"><path d="M25 62V38l9-10V17c0-8 13-8 13 3v12h10c9 0 12 7 9 15l-4 12c-1 5-5 8-12 8H30Z" fill="' . $skin . '"/>'
            . '<rect x="16" y="38" width="14" height="29" rx="5" fill="#34445d"/>'
            . '<circle cx="42" cy="' . $eye . '" r="2" fill="#222"/><circle cx="54" cy="' . $eye . '" r="2" fill="#222"/>'
            . '<path d="M43 54q5 6 10 0" fill="none" stroke="#222" stroke-width="2" stroke-linecap="round"/></g></svg>';
    }
}
