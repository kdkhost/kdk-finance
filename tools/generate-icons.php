<?php

/**
 * ============================================================================
 * SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 * ============================================================================
 * Desenvolvedor : George Marcelo de Oliveira Pimentel
 * Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 * Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 * Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 * ============================================================================
 *
 * Gera os ícones do PWA (favicon.ico, apple-touch-icon e icon-192/512).
 * Uso: php tools/generate-icons.php
 */

$public = dirname(__DIR__).DIRECTORY_SEPARATOR.'public';
$icons = $public.DIRECTORY_SEPARATOR.'icons';

if (! is_dir($icons)) {
    mkdir($icons, 0775, true);
}

$fonts = [
    'C:\\Windows\\Fonts\\seguisb.ttf',
    'C:\\Windows\\Fonts\\arialbd.ttf',
];
$font = null;
foreach ($fonts as $candidate) {
    if (is_file($candidate)) {
        $font = $candidate;
        break;
    }
}

/**
 * Desenha o ícone KDK em um canvas quadrado.
 */
function drawIcon(int $size, ?string $font): \GdImage
{
    $image = imagecreatetruecolor($size, $size);
    imagesavealpha($image, true);
    imageantialias($image, true);

    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);

    $radius = (int) round($size * 0.22);

    // Fundo em gradiente vertical (navy -> navy claro).
    for ($y = 0; $y < $size; $y++) {
        $t = $y / max(1, $size - 1);
        $r = (int) round(10 + (22 - 10) * $t);
        $g = (int) round(37 + (50 - 37) * $t);
        $b = (int) round(64 + (80 - 64) * $t);
        $color = imagecolorallocate($image, $r, $g, $b);
        imageline($image, 0, $y, $size, $y, $color);
    }

    // Máscara arredondada: recolore com transparência apenas os cantos.
    imagealphablending($image, false);
    $corner = imagecolorallocatealpha($image, 0, 0, 0, 127);
    for ($x = 0; $x < $size; $x++) {
        for ($y = 0; $y < $size; $y++) {
            $dx = 0;
            $dy = 0;

            if ($x < $radius) {
                $dx = $radius - $x;
            } elseif ($x > $size - $radius) {
                $dx = $x - ($size - $radius);
            }

            if ($y < $radius) {
                $dy = $radius - $y;
            } elseif ($y > $size - $radius) {
                $dy = $y - ($size - $radius);
            }

            if ($dx && $dy && sqrt($dx * $dx + $dy * $dy) > $radius) {
                imagesetpixel($image, $x, $y, $corner);
            }
        }
    }

    // Barra de acento em verde-água.
    imagealphablending($image, true);
    $teal = imagecolorallocate($image, 15, 157, 118);
    $barWidth = (int) round($size * 0.055);
    $barX = (int) round($size * 0.12);
    $barTop = (int) round($size * 0.70);
    $barBottom = (int) round($size * 0.88);
    imagefilledrectangle($image, $barX, $barTop, $barX + $barWidth * 4, $barBottom, $teal);

    // Monograma KDK.
    $white = imagecolorallocate($image, 244, 249, 255);
    $text = 'KDK';

    if ($font && function_exists('imagettftext')) {
        $fontSize = $size * 0.215;
        $box = imagettfbbox($fontSize, 0, $font, $text);
        $textWidth = abs($box[2] - $box[0]);
        $textHeight = abs($box[7] - $box[1]);
        $x = (int) round(($size - $textWidth) / 2);
        $y = (int) round(($size + $textHeight) / 2 - $size * 0.04);

        imagettftext($image, $fontSize, 0, $x, $y, $white, $font, $text);
    } else {
        $fontHeight = (int) round($size / 6);
        $textWidth = imagefontwidth(5) * strlen($text) * ($fontHeight / imagefontheight(5));
        imagestring($image, 5, (int) (($size - $textWidth) / 2), (int) (($size - $fontHeight) / 2), $text, $white);
    }

    return $image;
}

foreach ([192 => 'icon-192.png', 512 => 'icon-512.png', 180 => 'apple-touch-icon.png', 32 => 'icon-32.png'] as $size => $file) {
    $image = drawIcon($size, $font);
    imagepng($image, $icons.DIRECTORY_SEPARATOR.$file, 9);
    imagedestroy($image);
    echo "Gerado: icons/{$file}\n";
}

// favicon.ico (container ICO com payload PNG de 32x32).
$png = file_get_contents($icons.DIRECTORY_SEPARATOR.'icon-32.png');
$ico = pack('vvv', 0, 1, 1)
    .pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 22)
    .$png;
file_put_contents($public.DIRECTORY_SEPARATOR.'favicon.ico', $ico);
echo "Gerado: favicon.ico\n";

echo "Concluído.\n";
