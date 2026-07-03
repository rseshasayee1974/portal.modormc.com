<?php

namespace App\Services;

/**
 * QrCodeGenerator – pure PHP, zero dependencies.
 *
 * Generates a QR code as an inline SVG data-URI string.
 * Compatible with dompdf HTML-to-PDF rendering.
 *
 * Based on the QR Code specification (ISO/IEC 18004).
 * Supports Byte mode, Error Correction Level M, Versions 1–10.
 *
 * Usage:
 *   $dataUri = QrCodeGenerator::svgDataUri('Hello World');
 *   // Embed as: <img src="{{ $dataUri }}" width="100" height="100" />
 */
class QrCodeGenerator
{
    // ── Public API ──────────────────────────────────────────────────────────

    /**
     * Return an inline SVG data URI (base64-encoded).
     * Works in both browser <img> and dompdf.
     *
     * @param  string $data  Text to encode
     * @param  int    $size  Pixel size of each module
     * @param  int    $quiet Quiet-zone width in modules
     * @return string        data:image/svg+xml;base64,... URI
     */
    public static function svgDataUri(string $data, int $size = 4, int $quiet = 2): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($data, $size, $quiet));
    }

    /**
     * Return raw SVG markup.
     */
    public static function svg(string $data, int $size = 4, int $quiet = 2): string
    {
        $matrix = self::encode($data);
        $n      = count($matrix);
        $total  = $n + 2 * $quiet;
        $dim    = $total * $size;

        $rects = '';
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($matrix[$r][$c]) {
                    $x = ($c + $quiet) * $size;
                    $y = ($r + $quiet) * $size;
                    $rects .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$size}\" height=\"{$size}\" fill=\"#000\"/>";
                }
            }
        }

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$dim} {$dim}" width="{$dim}" height="{$dim}" shape-rendering="crispEdges">
<rect width="{$dim}" height="{$dim}" fill="#fff"/>
{$rects}
</svg>
SVG;
    }

    // ── GF(256) Arithmetic ──────────────────────────────────────────────────

    private static array $EXP = [];
    private static array $LOG = [];

    private static function initGF(): void
    {
        if (self::$EXP) return;
        self::$EXP = array_fill(0, 512, 0);
        self::$LOG = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$EXP[$i] = $x;
            self::$LOG[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11D;
        }
        for ($i = 255; $i < 512; $i++) {
            self::$EXP[$i] = self::$EXP[$i - 255];
        }
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) return 0;
        return self::$EXP[(self::$LOG[$a] + self::$LOG[$b]) % 255];
    }

    private static function gfPolyMul(array $p, array $q): array
    {
        $r = array_fill(0, count($p) + count($q) - 1, 0);
        foreach ($p as $j => $pj) {
            foreach ($q as $i => $qi) {
                $r[$i + $j] ^= self::gfMul($pj, $qi);
            }
        }
        return $r;
    }

    private static function gfPolyDiv(array $dividend, array $divisor): array
    {
        $msg  = $dividend;
        $dlen = count($divisor);
        for ($i = 0; $i < count($msg) - $dlen + 1; $i++) {
            $coef = $msg[$i];
            if ($coef === 0) continue;
            for ($j = 1; $j < $dlen; $j++) {
                if ($divisor[$j] !== 0) {
                    $msg[$i + $j] ^= self::gfMul($divisor[$j], $coef);
                }
            }
        }
        return array_slice($msg, count($msg) - $dlen + 1);
    }

    private static function rsGenerator(int $n): array
    {
        $g = [1];
        for ($i = 0; $i < $n; $i++) {
            $g = self::gfPolyMul($g, [1, self::$EXP[$i]]);
        }
        return $g;
    }

    private static function rsEncode(array $msg, int $n): array
    {
        $gen = self::rsGenerator($n);
        $pad = array_merge($msg, array_fill(0, $n, 0));
        $rem = self::gfPolyDiv($pad, $gen);
        return array_merge($msg, $rem);
    }

    // ── Version / Capacity (Byte mode, ECL-M) ──────────────────────────────
    // [version => [dataCodewords, ecCodewords]]
    private static array $CAPS = [
        1  => [16, 10],
        2  => [28, 16],
        3  => [44, 26],
        4  => [64, 36],
        5  => [86, 48],
        6  => [108, 64],
        7  => [124, 72],
        8  => [154, 88],
        9  => [182, 110],
        10 => [216, 130],
    ];

    // ── Main Encoder ────────────────────────────────────────────────────────

    public static function encode(string $data): array
    {
        self::initGF();

        $bytes   = array_values(unpack('C*', $data));
        $dataLen = count($bytes);

        // Select version
        $version = 10;
        foreach (self::$CAPS as $v => [$cap]) {
            if ($dataLen <= $cap) { $version = $v; break; }
        }

        [$dataCW, $ecCount] = self::$CAPS[$version];
        $bytes   = array_slice($bytes, 0, $dataCW); // truncate if needed
        $dataLen = count($bytes);
        $size    = 21 + ($version - 1) * 4;

        // Build bit stream
        $bits = [0, 1, 0, 0]; // Byte mode indicator
        for ($i = 7; $i >= 0; $i--) $bits[] = ($dataLen >> $i) & 1; // char count (8 bits)
        foreach ($bytes as $b) {
            for ($i = 7; $i >= 0; $i--) $bits[] = ($b >> $i) & 1;
        }
        // Terminator + byte-boundary padding
        for ($i = 0; $i < 4 && count($bits) < $dataCW * 8; $i++) $bits[] = 0;
        while (count($bits) % 8 !== 0) $bits[] = 0;
        // Padding codewords
        $pi = 0;
        while (count($bits) < $dataCW * 8) {
            $pb = ($pi % 2 === 0) ? 0xEC : 0x11;
            for ($i = 7; $i >= 0; $i--) $bits[] = ($pb >> $i) & 1;
            $pi++;
        }

        // Convert to codewords
        $codewords = [];
        for ($i = 0; $i < $dataCW; $i++) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) $byte = ($byte << 1) | ($bits[$i * 8 + $j] ?? 0);
            $codewords[] = $byte;
        }

        // Reed-Solomon
        $full = self::rsEncode($codewords, $ecCount);

        // Final bit stream
        $finalBits = [];
        foreach ($full as $cw) {
            for ($i = 7; $i >= 0; $i--) $finalBits[] = ($cw >> $i) & 1;
        }
        $remainders = [0, 7, 7, 7, 7, 7, 0, 0, 0, 0];
        $rem = $remainders[$version - 1] ?? 0;
        for ($i = 0; $i < $rem; $i++) $finalBits[] = 0;

        // Initialise matrix
        $matrix   = array_fill(0, $size, array_fill(0, $size, 0));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // Finder patterns (top-left, top-right, bottom-left)
        self::placeFinder($matrix, $reserved, 0, 0);
        self::placeFinder($matrix, $reserved, 0, $size - 7);
        self::placeFinder($matrix, $reserved, $size - 7, 0);

        // Separators
        self::placeSeparators($matrix, $reserved, $size);

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $v = ($i % 2 === 0) ? 1 : 0;
            $matrix[6][$i] = $v; $reserved[6][$i] = true;
            $matrix[$i][6] = $v; $reserved[$i][6] = true;
        }

        // Dark module
        $matrix[$size - 8][8] = 1;
        $reserved[$size - 8][8] = true;

        // Alignment patterns (v >= 2)
        if ($version >= 2) {
            $centers = self::alignCenters($version);
            foreach ($centers as $ar) {
                foreach ($centers as $ac) {
                    if (!$reserved[$ar][$ac]) {
                        self::placeAlignment($matrix, $reserved, $ar, $ac);
                    }
                }
            }
        }

        // Reserve format info area
        self::reserveFormat($reserved, $size);

        // Place data
        self::placeData($matrix, $reserved, $finalBits, $size);

        // Apply best mask
        [$mask, $masked] = self::bestMask($matrix, $reserved, $size);

        // Write format info
        self::writeFormat($masked, $size, $mask, 0b00 /* ECL-M */);

        // Version info (v >= 7)
        if ($version >= 7) {
            self::writeVersion($masked, $size, $version);
        }

        return $masked;
    }

    // ── Finder / Separator / Alignment ─────────────────────────────────────

    private static function placeFinder(array &$m, array &$r, int $row, int $col): void
    {
        $pat = [[1,1,1,1,1,1,1],[1,0,0,0,0,0,1],[1,0,1,1,1,0,1],
                [1,0,1,1,1,0,1],[1,0,1,1,1,0,1],[1,0,0,0,0,0,1],[1,1,1,1,1,1,1]];
        for ($dr = 0; $dr < 7; $dr++) {
            for ($dc = 0; $dc < 7; $dc++) {
                $m[$row + $dr][$col + $dc] = $pat[$dr][$dc];
                $r[$row + $dr][$col + $dc] = true;
            }
        }
    }

    private static function placeSeparators(array &$m, array &$r, int $sz): void
    {
        // 8-pixel wide separator strips around each finder
        $pairs = [
            // row, col pairs for the separating 0-modules
            fn($i) => [7, $i],   fn($i) => [$i, 7],          // top-left
            fn($i) => [7, $sz - 1 - $i], fn($i) => [$i, $sz - 8], // top-right
            fn($i) => [$sz - 8 + $i, 7], fn($i) => [$sz - 8, $i], // bottom-left
        ];
        $corners = [[7,0,7,0],[7,0,7,$sz-8],[0,$sz-8,7,0]]; // unused shorthand
        for ($i = 0; $i < 8; $i++) {
            $cells = [
                [7, $i], [$i, 7],
                [7, $sz - 1 - $i], [$i, $sz - 8],
                [$sz - 8 + $i < $sz ? $sz - 8 + $i : null, 7],
                [$sz - 8, $i],
            ];
            foreach ($cells as [$rr, $cc]) {
                if ($rr === null || $cc === null || $rr < 0 || $rr >= $sz || $cc < 0 || $cc >= $sz) continue;
                if (!$r[$rr][$cc]) { $m[$rr][$cc] = 0; $r[$rr][$cc] = true; }
            }
        }
    }

    private static function placeAlignment(array &$m, array &$r, int $row, int $col): void
    {
        $pat = [[1,1,1,1,1],[1,0,0,0,1],[1,0,1,0,1],[1,0,0,0,1],[1,1,1,1,1]];
        for ($dr = -2; $dr <= 2; $dr++) {
            for ($dc = -2; $dc <= 2; $dc++) {
                $m[$row + $dr][$col + $dc] = $pat[$dr + 2][$dc + 2];
                $r[$row + $dr][$col + $dc] = true;
            }
        }
    }

    private static function alignCenters(int $version): array
    {
        $table = [
            2  => [6,18], 3 => [6,22], 4 => [6,26], 5 => [6,30],
            6  => [6,34], 7 => [6,22,38], 8 => [6,24,42],
            9  => [6,26,46], 10 => [6,28,50],
        ];
        return $table[$version] ?? [];
    }

    // ── Format Area Reservation ─────────────────────────────────────────────

    private static function reserveFormat(array &$r, int $sz): void
    {
        for ($i = 0; $i <= 8; $i++) { $r[8][$i] = true; $r[$i][8] = true; }
        for ($i = 0; $i < 8; $i++) { $r[8][$sz - 1 - $i] = true; $r[$sz - 1 - $i][8] = true; }
    }

    // ── Data Placement ──────────────────────────────────────────────────────

    private static function placeData(array &$m, array &$r, array $bits, int $sz): void
    {
        $bi = 0; $up = true;
        for ($col = $sz - 1; $col >= 1; $col -= 2) {
            if ($col === 6) $col = 5;
            for ($i = 0; $i < $sz; $i++) {
                $row = $up ? $sz - 1 - $i : $i;
                foreach ([0, 1] as $dc) {
                    $c = $col - $dc;
                    if (!$r[$row][$c]) { $m[$row][$c] = $bits[$bi] ?? 0; $bi++; }
                }
            }
            $up = !$up;
        }
    }

    // ── Masking ─────────────────────────────────────────────────────────────

    private static function applyMask(array $matrix, array $reserved, int $sz, int $mask): array
    {
        $m = $matrix;
        for ($r = 0; $r < $sz; $r++) {
            for ($c = 0; $c < $sz; $c++) {
                if ($reserved[$r][$c]) continue;
                $flip = match ($mask) {
                    0 => ($r + $c) % 2 === 0,
                    1 => $r % 2 === 0,
                    2 => $c % 3 === 0,
                    3 => ($r + $c) % 3 === 0,
                    4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
                    5 => ($r * $c) % 2 + ($r * $c) % 3 === 0,
                    6 => (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0,
                    7 => (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0,
                    default => false,
                };
                if ($flip) $m[$r][$c] ^= 1;
            }
        }
        return $m;
    }

    private static function penalty(array $m, int $sz): int
    {
        $p = 0;
        for ($r = 0; $r < $sz; $r++) {
            for ($c = 0; $c < $sz - 4; $c++) {
                if ($m[$r][$c]===$m[$r][$c+1] && $m[$r][$c]===$m[$r][$c+2] &&
                    $m[$r][$c]===$m[$r][$c+3] && $m[$r][$c]===$m[$r][$c+4]) $p += 3;
            }
        }
        for ($c = 0; $c < $sz; $c++) {
            for ($r = 0; $r < $sz - 4; $r++) {
                if ($m[$r][$c]===$m[$r+1][$c] && $m[$r][$c]===$m[$r+2][$c] &&
                    $m[$r][$c]===$m[$r+3][$c] && $m[$r][$c]===$m[$r+4][$c]) $p += 3;
            }
        }
        return $p;
    }

    private static function bestMask(array $matrix, array $reserved, int $sz): array
    {
        $best = PHP_INT_MAX; $bestMask = 0; $bestMatrix = $matrix;
        for ($mask = 0; $mask < 8; $mask++) {
            $m = self::applyMask($matrix, $reserved, $sz, $mask);
            $p = self::penalty($m, $sz);
            if ($p < $best) { $best = $p; $bestMask = $mask; $bestMatrix = $m; }
        }
        return [$bestMask, $bestMatrix];
    }

    // ── Format / Version Info ───────────────────────────────────────────────

    private static function writeFormat(array &$m, int $sz, int $mask, int $ecl): void
    {
        // ECL-M = 00, ECL-L = 01, ECL-H = 10, ECL-Q = 11
        $data    = ($ecl << 3) | $mask;
        $rem     = $data << 10;
        $gen     = 0b10100110111;
        for ($i = 14; $i >= 10; $i--) {
            if ($rem & (1 << $i)) $rem ^= ($gen << ($i - 10));
        }
        $fmt = (($data << 10) | $rem) ^ 0b101010000010010;

        $pos1 = [[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],
                 [7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
        $pos2 = [[$sz-1,8],[$sz-2,8],[$sz-3,8],[$sz-4,8],[$sz-5,8],[$sz-6,8],[$sz-7,8],
                 [8,$sz-8],[8,$sz-7],[8,$sz-6],[8,$sz-5],[8,$sz-4],[8,$sz-3],[8,$sz-2],[8,$sz-1]];

        for ($i = 0; $i < 15; $i++) {
            $bit = ($fmt >> $i) & 1;
            $m[$pos1[$i][0]][$pos1[$i][1]] = $bit;
            $m[$pos2[$i][0]][$pos2[$i][1]] = $bit;
        }
    }

    private static function writeVersion(array &$m, int $sz, int $version): void
    {
        $gen = 0b1111100100101;
        $rem = $version << 12;
        for ($i = 17; $i >= 12; $i--) {
            if ($rem & (1 << $i)) $rem ^= ($gen << ($i - 12));
        }
        $info = ($version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $bit = ($info >> $i) & 1;
            $r   = intdiv($i, 3);
            $c   = ($i % 3) + $sz - 11;
            $m[$r][$c] = $bit;
            $m[$c][$r] = $bit;
        }
    }
}
