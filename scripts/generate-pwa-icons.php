<?php

/**
 * Generates the PWA placeholder icons into public/icons/.
 *
 * Placeholder look: primary (#2952E3) square, white bold "NT" centered.
 * Re-run any time with:  php scripts/generate-pwa-icons.php
 *
 * Requires the GD extension with FreeType. On Windows it uses Arial Bold;
 * override with a TTF path in the ICON_FONT env var if needed.
 */

if (! extension_loaded('gd') || ! function_exists('imagettftext')) {
    fwrite(STDERR, "GD with FreeType is required.\n");
    exit(1);
}

$font = getenv('ICON_FONT') ?: null;
foreach ([$font, 'C:/Windows/Fonts/arialbd.ttf', 'C:/Windows/Fonts/arial.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'] as $candidate) {
    if ($candidate && is_file($candidate)) {
        $font = $candidate;
        break;
    }
}
if (! $font || ! is_file($font)) {
    fwrite(STDERR, "No usable TTF font found. Set ICON_FONT=/path/to/font.ttf\n");
    exit(1);
}

$outDir = __DIR__.'/../public/icons';
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

// #2952E3
$brand = [0x29, 0x52, 0xE3];

/**
 * @param  bool  $rounded  transparent rounded corners (for "any" icons)
 * @param  bool  $maskable  shrink the text into the ~80% safe zone
 */
function makeIcon(int $size, string $path, array $brand, bool $rounded, bool $maskable): void
{
    global $font;

    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    imagealphablending($im, false);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127)); // transparent
    imagealphablending($im, true);

    $blue = imagecolorallocate($im, $brand[0], $brand[1], $brand[2]);
    $white = imagecolorallocate($im, 255, 255, 255);

    if ($rounded) {
        $r = (int) round($size * 0.22);
        imagefilledrectangle($im, $r, 0, $size - $r, $size, $blue);
        imagefilledrectangle($im, 0, $r, $size, $size - $r, $blue);
        foreach ([[$r, $r], [$size - $r, $r], [$r, $size - $r], [$size - $r, $size - $r]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, 2 * $r, 2 * $r, $blue);
        }
    } else {
        imagefilledrectangle($im, 0, 0, $size, $size, $blue);
    }

    // Fit "NT" to a target width.
    $target = $size * ($maskable ? 0.46 : 0.58);
    $fontSize = $size * 0.5;
    for ($i = 0; $i < 8; $i++) {
        $box = imagettfbbox($fontSize, 0, $font, 'NT');
        $textWidth = abs($box[2] - $box[0]);
        if ($textWidth <= 0) {
            break;
        }
        $fontSize *= $target / $textWidth;
    }

    $box = imagettfbbox($fontSize, 0, $font, 'NT');
    $textWidth = abs($box[2] - $box[0]);
    $textHeight = abs($box[7] - $box[1]);
    $x = (int) round(($size - $textWidth) / 2 - min($box[0], $box[6]));
    $y = (int) round(($size - $textHeight) / 2 + $textHeight - abs($box[1]));

    imagettftext($im, $fontSize, 0, $x, $y, $white, $font, 'NT');

    imagepng($im, $path);
    imagedestroy($im);
    echo "wrote $path\n";
}

makeIcon(192, "$outDir/icon-192.png", $brand, rounded: true, maskable: false);
makeIcon(512, "$outDir/icon-512.png", $brand, rounded: true, maskable: false);
makeIcon(512, "$outDir/icon-maskable-512.png", $brand, rounded: false, maskable: true);
makeIcon(180, "$outDir/icon-180.png", $brand, rounded: false, maskable: false); // apple-touch-icon

echo "done\n";
