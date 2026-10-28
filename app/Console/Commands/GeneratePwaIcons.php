<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pwa:generate-icons')]
#[Description('Generate all required PWA icons, SVGs, and favicon')]
class GeneratePwaIcons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pwa:generate-icons';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate all required PWA icons, SVGs, and favicon';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating PWA icons...');
        $iconsDir = public_path('icons');

        if (!is_dir($iconsDir)) {
            mkdir($iconsDir, 0755, true);
        }

        // 1. Generate SVG icons
        $svgContent = $this->getSvgContent(false);
        $maskableSvgContent = $this->getSvgContent(true);

        file_put_contents($iconsDir . '/icon.svg', $svgContent);
        file_put_contents($iconsDir . '/icon-maskable.svg', $maskableSvgContent);
        file_put_contents(public_path('favicon.svg'), $svgContent);
        $this->line('  ✓ SVG vector icons generated.');

        // 2. Render standard icons
        $sizes = [
            16, 32, 72, 96, 128, 144, 152, 180, 192, 384, 512,
        ];

        $masterStandard = $this->renderMasterCanvas(false);
        foreach ($sizes as $size) {
            $dest = imagecreatetruecolor($size, $size);
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
            imagefill($dest, 0, 0, $transparent);

            imagecopyresampled($dest, $masterStandard, 0, 0, 0, 0, $size, $size, 1024, 1024);

            $filename = "icon-{$size}x{$size}.png";
            imagepng($dest, $iconsDir . '/' . $filename, 9);

            if ($size === 180) {
                // Apple touch icon
                imagepng($dest, $iconsDir . '/apple-touch-icon.png', 9);
                imagepng($dest, public_path('apple-touch-icon.png'), 9);
            }
            imagedestroy($dest);
        }
        $this->line('  ✓ Standard resolution icons generated (16x16 to 512x512).');

        // 3. Render maskable icons
        $masterMaskable = $this->renderMasterCanvas(true);
        foreach ([192, 512] as $size) {
            $dest = imagecreatetruecolor($size, $size);
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            imagecopyresampled($dest, $masterMaskable, 0, 0, 0, 0, $size, $size, 1024, 1024);
            imagepng($dest, $iconsDir . "/icon-maskable-{$size}x{$size}.png", 9);
            imagedestroy($dest);
        }
        $this->line('  ✓ Maskable icons generated (192x192 & 512x512).');

        // 4. Generate multi-resolution favicon.ico (16x16 and 32x32)
        $this->generateFavicon([
            $iconsDir . '/icon-16x16.png',
            $iconsDir . '/icon-32x32.png',
        ], public_path('favicon.ico'));
        $this->line('  ✓ Multi-resolution favicon.ico generated.');

        imagedestroy($masterStandard);
        imagedestroy($masterMaskable);

        $this->info('All PWA icons generated successfully!');
        return self::SUCCESS;
    }

    /**
     * Generate SVG vector code.
     */
    private function getSvgContent(bool $maskable): string
    {
        $rx = $maskable ? '0' : '112';
        $scale = $maskable ? 'transform="translate(51.2, 51.2) scale(0.8)"' : '';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="100%" height="100%">
  <defs>
    <linearGradient id="pwaBgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#2563eb"/>
      <stop offset="50%" stop-color="#1d4ed8"/>
      <stop offset="100%" stop-color="#0f172a"/>
    </linearGradient>
    <linearGradient id="pwaHeadGrad" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" stop-color="#ffffff"/>
      <stop offset="100%" stop-color="#e2e8f0"/>
    </linearGradient>
    <linearGradient id="pwaVisorGrad" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" stop-color="#090d16"/>
      <stop offset="100%" stop-color="#1e293b"/>
    </linearGradient>
    <filter id="pwaGlow" x="-20%" y="-20%" width="140%" height="140%">
      <feGaussianBlur stdDeviation="6" result="blur"/>
      <feComposite in="SourceGraphic" in2="blur" operator="over"/>
    </filter>
  </defs>

  <rect width="512" height="512" rx="{$rx}" fill="url(#pwaBgGrad)"/>

  <g {$scale}>
    <!-- Antenna -->
    <line x1="256" y1="135" x2="256" y2="80" stroke="#93c5fd" stroke-width="12" stroke-linecap="round"/>
    <circle cx="256" cy="75" r="18" fill="#38bdf8" filter="url(#pwaGlow)"/>

    <!-- Ear pieces / Headset -->
    <rect x="90" y="210" width="28" height="90" rx="14" fill="#60a5fa"/>
    <rect x="394" y="210" width="28" height="90" rx="14" fill="#60a5fa"/>

    <!-- Robot Head Frame -->
    <rect x="110" y="140" width="292" height="230" rx="56" fill="url(#pwaHeadGrad)"/>

    <!-- Visor Display Screen -->
    <rect x="142" y="176" width="228" height="136" rx="34" fill="url(#pwaVisorGrad)"/>

    <!-- Glowing Cyan Eyes -->
    <circle cx="205" cy="242" r="24" fill="#38bdf8" filter="url(#pwaGlow)"/>
    <circle cx="212" cy="236" r="7" fill="#ffffff"/>
    <circle cx="307" cy="242" r="24" fill="#38bdf8" filter="url(#pwaGlow)"/>
    <circle cx="314" cy="236" r="7" fill="#ffffff"/>

    <!-- Curved Smile / Audio Pulse -->
    <path d="M 216 335 Q 256 358 296 335" stroke="#2563eb" stroke-width="8" stroke-linecap="round" fill="none"/>

    <!-- Collar / Body Base -->
    <path d="M 190 395 L 322 395 L 340 435 L 172 435 Z" fill="#60a5fa" opacity="0.9"/>
    <circle cx="256" cy="415" r="8" fill="#ffffff"/>

    <!-- AI Sparkle -->
    <path d="M 395 105 Q 405 120 420 130 Q 405 140 395 155 Q 385 140 370 130 Q 385 120 395 105 Z" fill="#38bdf8"/>
  </g>
</svg>
SVG;
    }

    /**
     * Render a super-sharp 1024x1024 master canvas using GD primitives.
     */
    private function renderMasterCanvas(bool $maskable)
    {
        $im = imagecreatetruecolor(1024, 1024);
        imagealphablending($im, true);
        imagesavealpha($im, true);

        // Fill background with transparent first
        $trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $trans);

        // Draw rich gradient background
        // Top-left: #2563eb (37, 99, 235), Mid: #1d4ed8 (29, 78, 216), Bottom-right: #0f172a (15, 23, 42)
        $r1 = 37; $g1 = 99; $b1 = 235;
        $r2 = 15; $g2 = 23; $b2 = 42;

        $bgMask = imagecreatetruecolor(1024, 1024);
        imagealphablending($bgMask, true);
        imagesavealpha($bgMask, true);

        // Draw diagonal gradient into bgMask
        for ($y = 0; $y < 1024; $y++) {
            $t = $y / 1023.0;
            $r = (int)($r1 + ($r2 - $r1) * $t);
            $g = (int)($g1 + ($g2 - $g1) * $t);
            $b = (int)($b1 + ($b2 - $b1) * $t);
            $col = imagecolorallocate($bgMask, $r, $g, $b);
            imageline($bgMask, 0, $y, 1023, $y, $col);
        }

        if ($maskable) {
            // Maskable: Full bleed without rounding
            imagecopy($im, $bgMask, 0, 0, 0, 0, 1024, 1024);
        } else {
            // Standard: Rounded squircle with 224px radius
            $this->applyRoundedMask($im, $bgMask, 1024, 1024, 224);
        }
        imagedestroy($bgMask);

        // Determine offset and scale for inner artwork
        // Standard: 1.0 scale centered
        // Maskable: 0.8 scale centered so it strictly respects the 80% safe zone
        $scale = $maskable ? 0.78 : 0.96;
        $offsetY = $maskable ? 20 : 0;

        $this->drawRobotArtwork($im, $scale, $offsetY);

        return $im;
    }

    /**
     * Clip a source image to a rounded rectangle mask.
     */
    private function applyRoundedMask($dest, $src, int $w, int $h, int $radius): void
    {
        // Copy rectangle cross
        imagecopy($dest, $src, $radius, 0, $radius, 0, $w - 2 * $radius, $h);
        imagecopy($dest, $src, 0, $radius, 0, $radius, $radius, $h - 2 * $radius);
        imagecopy($dest, $src, $w - $radius, $radius, $w - $radius, $radius, $radius, $h - 2 * $radius);

        // Fill 4 corners with smooth circular arcs
        for ($cy = 0; $cy < $radius; $cy++) {
            for ($cx = 0; $cx < $radius; $cx++) {
                $dx = $radius - $cx;
                $dy = $radius - $cy;
                $distSq = ($dx * $dx) + ($dy * $dy);
                $radSq = $radius * $radius;

                if ($distSq <= $radSq) {
                    $alpha = 0;
                    if ($distSq > ($radius - 1) * ($radius - 1)) {
                        $dist = sqrt($distSq);
                        $alpha = (int)(($dist - ($radius - 1)) * 127);
                        $alpha = max(0, min(127, $alpha));
                    }

                    // Top-Left
                    $rgbTL = imagecolorat($src, $cx, $cy);
                    $colTL = imagecolorallocatealpha($dest, ($rgbTL >> 16) & 0xFF, ($rgbTL >> 8) & 0xFF, $rgbTL & 0xFF, $alpha);
                    imagesetpixel($dest, $cx, $cy, $colTL);

                    // Top-Right
                    $xTR = $w - 1 - $cx;
                    $rgbTR = imagecolorat($src, $xTR, $cy);
                    $colTR = imagecolorallocatealpha($dest, ($rgbTR >> 16) & 0xFF, ($rgbTR >> 8) & 0xFF, $rgbTR & 0xFF, $alpha);
                    imagesetpixel($dest, $xTR, $cy, $colTR);

                    // Bottom-Left
                    $yBL = $h - 1 - $cy;
                    $rgbBL = imagecolorat($src, $cx, $yBL);
                    $colBL = imagecolorallocatealpha($dest, ($rgbBL >> 16) & 0xFF, ($rgbBL >> 8) & 0xFF, $rgbBL & 0xFF, $alpha);
                    imagesetpixel($dest, $cx, $yBL, $colBL);

                    // Bottom-Right
                    $rgbBR = imagecolorat($src, $xTR, $yBL);
                    $colBR = imagecolorallocatealpha($dest, ($rgbBR >> 16) & 0xFF, ($rgbBR >> 8) & 0xFF, $rgbBR & 0xFF, $alpha);
                    imagesetpixel($dest, $xTR, $yBL, $colBR);
                }
            }
        }
    }

    /**
     * Draw robot character and accents.
     */
    private function drawRobotArtwork($im, float $scale, int $offsetY): void
    {
        $cx = 512;
        $cy = 512 + $offsetY;

        // Transform helper
        $tx = function ($x) use ($cx, $scale) {
            return (int)($cx + ($x - 512) * $scale);
        };
        $ty = function ($y) use ($cy, $scale) {
            return (int)($cy + ($y - 512) * $scale);
        };
        $ts = function ($val) use ($scale) {
            return (int)($val * $scale);
        };

        // Colors
        $antennaCol = imagecolorallocate($im, 147, 197, 253);
        $cyanGlow = imagecolorallocate($im, 56, 189, 248);
        $headsetCol = imagecolorallocate($im, 96, 165, 250);
        $headCol = imagecolorallocate($im, 248, 250, 252);
        $headShadow = imagecolorallocate($im, 226, 232, 240);
        $visorCol = imagecolorallocate($im, 15, 23, 42);
        $whiteCol = imagecolorallocate($im, 255, 255, 255);
        $blueCol = imagecolorallocate($im, 37, 99, 235);
        $collarCol = imagecolorallocate($im, 96, 165, 250);

        // 1. Antenna stalk
        imagesetthickness($im, max(4, $ts(24)));
        imageline($im, $tx(512), $ty(270), $tx(512), $ty(150), $antennaCol);

        // 2. Antenna glowing orb
        imagefilledellipse($im, $tx(512), $ty(140), $ts(76), $ts(76), $cyanGlow);
        imagefilledellipse($im, $tx(512), $ty(140), $ts(44), $ts(44), $whiteCol);

        // 3. Headset / Ears
        $this->drawFilledRoundRect($im, $tx(180), $ty(420), $ts(56), $ts(180), $ts(28), $headsetCol);
        $this->drawFilledRoundRect($im, $tx(788), $ty(420), $ts(56), $ts(180), $ts(28), $headsetCol);

        // 4. Robot Head Base
        $this->drawFilledRoundRect($im, $tx(220), $ty(280), $ts(584), $ts(460), $ts(112), $headShadow);
        $this->drawFilledRoundRect($im, $tx(220), $ty(270), $ts(584), $ts(450), $ts(112), $headCol);

        // 5. Visor Screen
        $this->drawFilledRoundRect($im, $tx(284), $ty(352), $ts(456), $ts(272), $ts(68), $visorCol);

        // 6. Glowing Eyes
        // Left Eye
        imagefilledellipse($im, $tx(410), $ty(484), $ts(96), $ts(96), $cyanGlow);
        imagefilledellipse($im, $tx(424), $ty(472), $ts(28), $ts(28), $whiteCol);

        // Right Eye
        imagefilledellipse($im, $tx(614), $ty(484), $ts(96), $ts(96), $cyanGlow);
        imagefilledellipse($im, $tx(628), $ty(472), $ts(28), $ts(28), $whiteCol);

        // 7. Smile (Curved arc)
        imagesetthickness($im, max(3, $ts(16)));
        imagearc($im, $tx(512), $ty(660), $ts(160), $ts(80), 20, 160, $blueCol);

        // 8. Collar Accent
        $collarY = $ty(790);
        $collarH = $ts(80);
        $this->drawFilledRoundRect($im, $tx(370), $collarY, $ts(284), $collarH, $ts(24), $collarCol);
        imagefilledellipse($im, $tx(512), $collarY + (int)($collarH / 2), $ts(24), $ts(24), $whiteCol);

        // 9. AI Sparkle in top-right
        $sx = $tx(790);
        $sy = $ty(210);
        $sr = $ts(50);
        imagefilledellipse($im, $sx, $sy, $sr, $sr / 3, $cyanGlow);
        imagefilledellipse($im, $sx, $sy, $sr / 3, $sr, $cyanGlow);
        imagefilledellipse($im, $sx, $sy, $sr / 4, $sr / 4, $whiteCol);
    }

    /**
     * Draw a filled rounded rectangle.
     */
    private function drawFilledRoundRect($im, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        $r = min($r, (int)($w / 2), (int)($h / 2));
        if ($r <= 0) {
            imagefilledrectangle($im, $x, $y, $x + $w, $y + $h, $color);
            return;
        }

        // Inner boxes
        imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($im, $x, $y + $r, $x + $r, $y + $h - $r, $color);
        imagefilledrectangle($im, $x + $w - $r, $y + $r, $x + $w, $y + $h - $r, $color);

        // Corner circles
        $d = $r * 2;
        imagefilledellipse($im, $x + $r, $y + $r, $d, $d, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $r, $d, $d, $color);
        imagefilledellipse($im, $x + $r, $y + $h - $r, $d, $d, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $h - $r, $d, $d, $color);
    }

    /**
     * Generate a multi-resolution favicon.ico containing PNG entries.
     */
    private function generateFavicon(array $pngPaths, string $outputPath): void
    {
        $images = [];
        foreach ($pngPaths as $path) {
            if (file_exists($path)) {
                $images[] = [
                    'data' => file_get_contents($path),
                    'size' => getimagesize($path),
                ];
            }
        }

        if (empty($images)) {
            return;
        }

        // ICO Header: 2 bytes reserved (0), 2 bytes type (1 = icon), 2 bytes image count
        $icoData = pack('vvv', 0, 1, count($images));
        $offset = 6 + (count($images) * 16);

        // Write directory entries
        foreach ($images as $img) {
            $w = $img['size'][0] >= 256 ? 0 : $img['size'][0];
            $h = $img['size'][1] >= 256 ? 0 : $img['size'][1];
            $len = strlen($img['data']);

            // 1 byte width, 1 byte height, 1 byte color palette (0), 1 byte reserved (0)
            // 2 bytes color planes (1), 2 bytes bpp (32)
            // 4 bytes data size, 4 bytes data offset
            $icoData .= pack('CCCCvvVV', $w, $h, 0, 0, 1, 32, $len, $offset);
            $offset += $len;
        }

        // Append image datas
        foreach ($images as $img) {
            $icoData .= $img['data'];
        }

        file_put_contents($outputPath, $icoData);
    }
}
