<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

trait ImageConverterTrait
{
    /**
     * Store uploaded image as optimized WebP.
     *
     * Keeps original dimensions (aspect ratio preserved, no padding).
     * Only downsizes when width or height exceeds the max (default 3000px).
     *
     * Returns path relative to the public disk, with a leading slash:
     * /products/filename.webp
     */
    protected function storeImageAsWebp(
        UploadedFile $file,
        string $folder = 'products',
        int $quality = 85,
        int $maxWidth = 3000,
        int $maxHeight = 3000
    ): string {
        $filename = Str::uuid() . '.webp';

        $diskPath = storage_path("app/public/{$folder}");

        if (!is_dir($diskPath)) {
            mkdir($diskPath, 0755, true);
        }

        $fullPath = "{$diskPath}/{$filename}";

        /*
        |--------------------------------------------------------------------------
        | Imagick
        |--------------------------------------------------------------------------
        */

        if (extension_loaded('imagick')) {
            $imagick = new \Imagick($file->getRealPath());

            // Read first frame for formats such as GIF
            if ($imagick->getNumberImages() > 1) {
                $imagick->setIteratorIndex(0);
            }

            // Auto rotate according to EXIF orientation
            $imagick->autoOrient();

            // Downsize only if larger than the max, keeping aspect ratio (no padding)
            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();

            if ($width > $maxWidth || $height > $maxHeight) {
                // bestfit = true, fill = false
                $imagick->thumbnailImage(
                    $maxWidth,
                    $maxHeight,
                    true,
                    false
                );
            }

            /*
            |--------------------------------------------------------------------------
            | WebP
            |--------------------------------------------------------------------------
            */

            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality($quality);

            // Remove unnecessary metadata
            $imagick->stripImage();

            $imagick->writeImage($fullPath);
            $imagick->clear();
            $imagick->destroy();

            return "/{$folder}/{$filename}";
        }

        /*
        |--------------------------------------------------------------------------
        | GD
        |--------------------------------------------------------------------------
        */

        if (extension_loaded('gd')) {
            $this->convertWithGd(
                $file->getRealPath(),
                $fullPath,
                $quality,
                $maxWidth,
                $maxHeight
            );

            return "/{$folder}/{$filename}";
        }

        /*
        |--------------------------------------------------------------------------
        | No Image Extension
        |--------------------------------------------------------------------------
        */

        // Fallback: store original file
        return '/' . ltrim($file->store($folder, 'public'), '/');
    }

    /**
     * Convert image to WebP using GD.
     */
    private function convertWithGd(
        string $sourcePath,
        string $destPath,
        int $quality,
        int $maxWidth,
        int $maxHeight
    ): void {
        $mime = mime_content_type($sourcePath);

        $src = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/png'  => imagecreatefrompng($sourcePath),
            'image/gif'  => imagecreatefromgif($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),

            default => throw new \RuntimeException(
                "Unsupported image type: {$mime}"
            ),
        };

        if (!$src) {
            throw new \RuntimeException(
                'Unable to create image resource.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Original Dimensions
        |--------------------------------------------------------------------------
        */

        $originalWidth = imagesx($src);
        $originalHeight = imagesy($src);

        /*
        |--------------------------------------------------------------------------
        | Calculate New Dimensions (never upscale, keep aspect ratio)
        |--------------------------------------------------------------------------
        */

        $ratio = min(
            $maxWidth / $originalWidth,
            $maxHeight / $originalHeight,
            1
        );

        $newWidth = (int) round($originalWidth * $ratio);
        $newHeight = (int) round($originalHeight * $ratio);

        /*
        |--------------------------------------------------------------------------
        | Resize
        |--------------------------------------------------------------------------
        */

        if (
            $newWidth !== $originalWidth ||
            $newHeight !== $originalHeight
        ) {
            $resized = imagecreatetruecolor(
                $newWidth,
                $newHeight
            );

            // Preserve transparency
            imagealphablending($resized, false);
            imagesavealpha($resized, true);

            imagecopyresampled(
                $resized,
                $src,
                0,
                0,
                0,
                0,
                $newWidth,
                $newHeight,
                $originalWidth,
                $originalHeight
            );

            imagedestroy($src);

            $src = $resized;
        }

        /*
        |--------------------------------------------------------------------------
        | Preserve Transparency
        |--------------------------------------------------------------------------
        */

        imagealphablending($src, false);
        imagesavealpha($src, true);

        /*
        |--------------------------------------------------------------------------
        | Save WebP
        |--------------------------------------------------------------------------
        */

        imagewebp(
            $src,
            $destPath,
            $quality
        );

        imagedestroy($src);
    }
}