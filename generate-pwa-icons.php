<?php

declare(strict_types=1);

$sourcePath = __DIR__ . '/public/assets/images/logo.png';
$outputPath = __DIR__ . '/public/images/app';

$sizes = [
    32,
    48,
    72,
    96,
    128,
    144,
    152,
    180,
    192,
    384,
    512,
];

$backgroundColor = '#0D493F';

if (! extension_loaded('gd')) {
    exit("خطأ: PHP GD غير مفعلة.\n");
}

if (! file_exists($sourcePath)) {
    exit("الشعار غير موجود: {$sourcePath}\n");
}

if (! is_dir($outputPath)) {
    mkdir($outputPath, 0755, true);
}

$sourceImage = imagecreatefrompng($sourcePath);

if (! $sourceImage) {
    exit("تعذر قراءة ملف الشعار.\n");
}

imagealphablending($sourceImage, true);
imagesavealpha($sourceImage, true);

$sourceWidth = imagesx($sourceImage);
$sourceHeight = imagesy($sourceImage);

/*
|--------------------------------------------------------------------------
| اكتشاف حدود المحتوى الفعلي
|--------------------------------------------------------------------------
|
| يتم تجاهل جميع المساحات الشفافة الموجودة حول الشعار.
|
*/

$minimumX = $sourceWidth;
$minimumY = $sourceHeight;
$maximumX = 0;
$maximumY = 0;
$contentFound = false;

for ($y = 0; $y < $sourceHeight; $y++) {
    for ($x = 0; $x < $sourceWidth; $x++) {
        $pixel = imagecolorat(
            $sourceImage,
            $x,
            $y
        );

        $alpha = ($pixel >> 24) & 0x7F;

        /*
         * 127 = شفاف بالكامل.
         * نستخدم 120 حتى نتجاهل الحواف الشفافة الضعيفة.
         */
        if ($alpha < 120) {
            $minimumX = min($minimumX, $x);
            $minimumY = min($minimumY, $y);
            $maximumX = max($maximumX, $x);
            $maximumY = max($maximumY, $y);

            $contentFound = true;
        }
    }
}

if (! $contentFound) {
    exit("لم يتم العثور على محتوى ظاهر داخل الشعار.\n");
}

$contentWidth = ($maximumX - $minimumX) + 1;
$contentHeight = ($maximumY - $minimumY) + 1;

[$red, $green, $blue] = sscanf(
    ltrim($backgroundColor, '#'),
    '%02x%02x%02x'
);

foreach ($sizes as $size) {
    $canvas = imagecreatetruecolor(
        $size,
        $size
    );

    imagealphablending($canvas, true);
    imagesavealpha($canvas, true);

    $background = imagecolorallocate(
        $canvas,
        $red,
        $green,
        $blue
    );

    imagefill(
        $canvas,
        0,
        0,
        $background
    );

    /*
     * تكبير الشعار ليستخدم 90% من مساحة الأيقونة.
     */
    $safeArea = (int) round($size * 0.90);

    $scale = min(
        $safeArea / $contentWidth,
        $safeArea / $contentHeight
    );

    $targetWidth = max(
        1,
        (int) round($contentWidth * $scale)
    );

    $targetHeight = max(
        1,
        (int) round($contentHeight * $scale)
    );

    $destinationX = (int) round(
        ($size - $targetWidth) / 2
    );

    $destinationY = (int) round(
        ($size - $targetHeight) / 2
    );

    imagecopyresampled(
        $canvas,
        $sourceImage,
        $destinationX,
        $destinationY,
        $minimumX,
        $minimumY,
        $targetWidth,
        $targetHeight,
        $contentWidth,
        $contentHeight
    );

    $destinationFile =
        $outputPath . "/icon-{$size}.png";

    imagepng(
        $canvas,
        $destinationFile,
        9
    );

    imagedestroy($canvas);

    echo "Created: {$destinationFile}\n";
}

imagedestroy($sourceImage);

echo "\nتم قص الفراغ الشفاف وتكبير جميع الأيقونات.\n";