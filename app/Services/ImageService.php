<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploaded photos on the public disk, scaled down and re-encoded as
 * WebP so phone photos (often 4–8 MB) don't slow the site down. Falls back
 * to storing the original file when GD can't handle it.
 */
class ImageService
{
    public const QUALITY = 82;

    /**
     * @return array{path: string, thumb: ?string, width: ?int, height: ?int}
     */
    public static function store(UploadedFile $file, string $dir, int $maxWidth, ?int $thumbWidth = null): array
    {
        $source = static::load($file->getRealPath());

        if (!$source) {
            $size = @getimagesize($file->getRealPath()) ?: [null, null];

            return ['path' => $file->store($dir, 'public'), 'thumb' => null, 'width' => $size[0], 'height' => $size[1]];
        }

        $full = static::scale($source, $maxWidth);
        $result = [
            'path'   => static::save($full, $dir),
            'thumb'  => null,
            'width'  => imagesx($full),
            'height' => imagesy($full),
        ];

        if ($thumbWidth && imagesx($source) > $thumbWidth) {
            $thumb = static::scale($source, $thumbWidth);
            $result['thumb'] = static::save($thumb, $dir . '/thumbs');
            imagedestroy($thumb);
        }

        if ($full !== $source) {
            imagedestroy($full);
        }
        imagedestroy($source);

        return $result;
    }

    public static function delete(?string ...$paths): void
    {
        $paths = array_values(array_filter($paths));
        if ($paths) {
            Storage::disk('public')->delete($paths);
        }
    }

    /** Decodes a JPEG/PNG/WebP into a true-colour GD image, upright. */
    private static function load(string $path): ?\GdImage
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            return null;
        }

        $info = @getimagesize($path);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }

        // A 12-megapixel photo needs ~50 MB decoded.
        $limit = ini_get('memory_limit');
        if ($limit !== '-1' && static::bytes($limit) < 256 * 1024 * 1024) {
            @ini_set('memory_limit', '256M');
        }

        $img = @imagecreatefromstring((string) file_get_contents($path));
        if (!$img) {
            return null;
        }

        if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);

        // Phones store rotation in EXIF instead of rotating the pixels.
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
            if ($angle) {
                $rotated = imagerotate($img, $angle, 0);
                if ($rotated) {
                    imagedestroy($img);
                    $img = $rotated;
                }
            }
        }

        return $img;
    }

    private static function scale(\GdImage $img, int $maxWidth): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= $maxWidth) {
            return $img;
        }

        $nh = max(1, (int) round($h * $maxWidth / $w));
        $out = imagecreatetruecolor($maxWidth, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $maxWidth, $nh, $w, $h);

        return $out;
    }

    private static function save(\GdImage $img, string $dir): string
    {
        ob_start();
        imagewebp($img, null, static::QUALITY);
        $data = ob_get_clean();

        $path = trim($dir, '/') . '/' . Str::random(32) . '.webp';
        Storage::disk('public')->put($path, $data);

        return $path;
    }

    private static function bytes(string $value): int
    {
        $n = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g'     => $n * 1024 ** 3,
            'm'     => $n * 1024 ** 2,
            'k'     => $n * 1024,
            default => $n,
        };
    }
}
