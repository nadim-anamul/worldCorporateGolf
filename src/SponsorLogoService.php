<?php

declare(strict_types=1);

class SponsorLogoService
{
    private const MAX_BYTES = 3 * 1024 * 1024;
    private const MAX_DIMENSION = 320;
    private const ALLOWED_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    private const UPLOAD_SUBDIR = 'uploads/sponsor_logos';

    public function validateUpload(array $file): void
    {
        if (!isset($file['tmp_name'], $file['error'])) {
            throw new RuntimeException('Please upload a sponsor logo.');
        }

        $error = (int)$file['error'];
        if ($error !== UPLOAD_ERR_OK) {
            $message = match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Logo file is too large. Max file size is 3MB.',
                UPLOAD_ERR_PARTIAL => 'Logo upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_FILE => 'Please upload a sponsor logo.',
                default => 'Logo upload failed. Please try again.',
            };
            throw new RuntimeException($message);
        }

        $tmpPath = (string)$file['tmp_name'];
        if ($tmpPath === '' || !is_readable($tmpPath)) {
            throw new RuntimeException('Please upload a sponsor logo.');
        }

        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('Logo file is too large. Max file size is 3MB.');
        }

        $mime = '';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)($finfo->file($tmpPath) ?: '');
        }

        $imageInfo = @getimagesize($tmpPath);
        $detectedMime = is_array($imageInfo) ? (string)($imageInfo['mime'] ?? '') : '';

        $allowed = in_array($mime, self::ALLOWED_MIMES, true)
            || in_array($detectedMime, self::ALLOWED_MIMES, true);

        if (!$allowed && $mime !== 'application/octet-stream') {
            throw new RuntimeException('Invalid logo file type. Please upload a JPG, PNG, GIF, or WebP image.');
        }

        if (!$allowed && $imageInfo === false) {
            throw new RuntimeException('Invalid logo file type. Please upload a JPG, PNG, GIF, or WebP image.');
        }
    }

    public function hasUpload(array $file): bool
    {
        return isset($file['error'])
            && (int)$file['error'] !== UPLOAD_ERR_NO_FILE
            && (string)($file['tmp_name'] ?? '') !== '';
    }

    /**
     * @return string Web-relative path e.g. uploads/sponsor_logos/3_abc.png
     */
    public function saveForSponsor(array $file, int $tournamentId): string
    {
        $this->validateUpload($file);

        $uploadDir = dirname(__DIR__) . '/' . self::UPLOAD_SUBDIR;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Failed to prepare sponsor logo upload directory.');
        }

        $ext = $this->resolveOutputExtension($file);
        $fileName = $tournamentId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $targetPath = $uploadDir . '/' . $fileName;

        if (!$this->saveOptimized($file, $targetPath, $ext)) {
            throw new RuntimeException('Failed to process logo. Please upload a JPG or PNG image.');
        }

        return self::UPLOAD_SUBDIR . '/' . $fileName;
    }

    public function deleteIfExists(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (str_contains($relativePath, '..') || !str_starts_with($relativePath, self::UPLOAD_SUBDIR . '/')) {
            return;
        }

        $fullPath = dirname(__DIR__) . '/' . $relativePath;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function resolveOutputExtension(array $file): string
    {
        $tmpPath = (string)$file['tmp_name'];
        $info = @getimagesize($tmpPath);
        $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';

        if (in_array($mime, ['image/png', 'image/gif', 'image/webp'], true)) {
            return 'png';
        }

        return 'jpg';
    }

    private function saveOptimized(array $file, string $targetPath, string $outputExt): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        $srcPath = (string)$file['tmp_name'];
        $info = getimagesize($srcPath);
        if (!$info) {
            return false;
        }

        $mime = (string)$info['mime'];
        $srcImg = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($srcPath),
            'image/png' => @imagecreatefrompng($srcPath),
            'image/gif' => @imagecreatefromgif($srcPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false,
            default => false,
        };

        if (!$srcImg) {
            return false;
        }

        if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
            $srcImg = $this->applyExifOrientation($srcImg, $srcPath);
        }

        $origWidth = imagesx($srcImg);
        $origHeight = imagesy($srcImg);
        $newWidth = $origWidth;
        $newHeight = $origHeight;

        if ($origWidth > self::MAX_DIMENSION || $origHeight > self::MAX_DIMENSION) {
            if ($origWidth > $origHeight) {
                $newWidth = self::MAX_DIMENSION;
                $newHeight = (int)round(($origHeight / $origWidth) * self::MAX_DIMENSION);
            } else {
                $newHeight = self::MAX_DIMENSION;
                $newWidth = (int)round(($origWidth / $origHeight) * self::MAX_DIMENSION);
            }
        }

        $destImg = imagecreatetruecolor($newWidth, $newHeight);
        if (!$destImg) {
            imagedestroy($srcImg);
            return false;
        }

        $preserveAlpha = $outputExt === 'png';
        if ($preserveAlpha) {
            imagealphablending($destImg, false);
            imagesavealpha($destImg, true);
            $transparent = imagecolorallocatealpha($destImg, 255, 255, 255, 127);
            imagefilledrectangle($destImg, 0, 0, $newWidth, $newHeight, $transparent);
        }

        if (!imagecopyresampled($destImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight)) {
            imagedestroy($srcImg);
            imagedestroy($destImg);
            return false;
        }

        $success = $outputExt === 'png'
            ? imagepng($destImg, $targetPath, 6)
            : imagejpeg($destImg, $targetPath, 85);

        imagedestroy($srcImg);
        imagedestroy($destImg);

        return $success;
    }

    private function applyExifOrientation(\GdImage $image, string $srcPath): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($srcPath);
        if (!is_array($exif) || empty($exif['Orientation'])) {
            return $image;
        }
        switch ((int)$exif['Orientation']) {
            case 2: imageflip($image, IMG_FLIP_HORIZONTAL); break;
            case 3: $image = imagerotate($image, 180, 0); break;
            case 4: imageflip($image, IMG_FLIP_VERTICAL); break;
            case 5: $image = imagerotate($image, -90, 0); imageflip($image, IMG_FLIP_HORIZONTAL); break;
            case 6: $image = imagerotate($image, -90, 0); break;
            case 7: $image = imagerotate($image, 90, 0); imageflip($image, IMG_FLIP_HORIZONTAL); break;
            case 8: $image = imagerotate($image, 90, 0); break;
        }
        return $image;
    }
}
