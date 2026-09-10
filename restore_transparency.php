<?php
// Remove white/near-white background from hero PNGs to restore transparency
$dir = __DIR__ . '/assets/images';
for ($i = 1; $i <= 9; $i++) {
    $path = "$dir/hero-$i.png";
    if (!file_exists($path)) continue;
    $src = imagecreatefrompng($path);
    if (!$src) { echo "Failed to load hero-$i.png\n"; continue; }
    $w = imagesx($src); $h = imagesy($src);
    imagealphablending($src, false);
    imagesavealpha($src, true);
    $bg = imagecreatetruecolor($w, $h);
    imagealphablending($bg, false);
    imagesavealpha($bg, true);
    // Fill with fully transparent
    $transparent = imagecolorallocatealpha($bg, 0, 0, 0, 127);
    imagefill($bg, 0, 0, $transparent);
    // Process each pixel
    for ($x = 0; $x < $w; $x++) {
        for ($y = 0; $y < $h; $y++) {
            $rgb = imagecolorat($src, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $alpha = ($rgb >> 24) & 0x7F;
            // If pixel is near-white (all channels > 230) and fully opaque, make transparent
            $dist = max($r, $g, $b) - min($r, $g, $b);
            if ($r > 230 && $g > 230 && $b > 230 && $dist < 30 && $alpha === 0) {
                $color = imagecolorallocatealpha($bg, $r, $g, $b, 127);
            } else {
                $color = imagecolorallocatealpha($bg, $r, $g, $b, $alpha);
            }
            imagesetpixel($bg, $x, $y, $color);
        }
    }
    imagepng($bg, $path, 9);
    imagedestroy($src); imagedestroy($bg);
    echo "Restored transparency for hero-$i.png\n";
}
echo "Done\n";
