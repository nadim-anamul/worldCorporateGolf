<?php

declare(strict_types=1);

class TournamentHeroBackgroundService
{
    private const MAX_BYTES = 8 * 1024 * 1024;
    private const MAX_DIMENSION = 1920;
    private const JPEG_QUALITY = 82;
    private const ALLOWED_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    private const UPLOAD_SUBDIR = 'uploads/tournament_backgrounds';

    public function validateUpload(array $file): void
    {
        if (!isset($file['tmp_name'], $file['error'])) {
            throw new RuntimeException('Please upload a hero background image.');
        }

        $error = (int)$file['error'];
        if ($error !== UPLOAD_ERR_OK) {
            $message = match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Hero background is too large. Max file size is 8MB.',
                UPLOAD_ERR_PARTIAL => 'Hero background upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_FILE => 'Please upload a hero background image.',
                default => 'Hero background upload failed. Please try again.',
            };
            throw new RuntimeException($message);
        }

        $tmpPath = (string)$file['tmp_name'];
        if ($tmpPath === '' || !is_readable($tmpPath)) {
            throw new RuntimeException('Please upload a hero background image.');
        }

        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('Hero background is too large. Max file size is 8MB.');
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
            throw new RuntimeException('Invalid hero background type. Please upload a JPG or PNG image.');
        }

        if (!$allowed && $imageInfo === false) {
            throw new RuntimeException('Invalid hero background type. Please upload a JPG or PNG image.');
        }
    }

    public function hasUpload(array $file): bool
    {
        return isset($file['error'])
            && (int)$file['error'] !== UPLOAD_ERR_NO_FILE
            && (string)($file['tmp_name'] ?? '') !== '';
    }

    /**
     * @return string Web-relative path e.g. uploads/tournament_backgrounds/3_abc.jpg
     */
    public function saveForTournament(array $file, int $tournamentId): string
    {
        $this->validateUpload($file);

        $uploadDir = dirname(__DIR__) . '/' . self::UPLOAD_SUBDIR;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Failed to prepare hero background upload directory.');
        }

        $fileName = $tournamentId . '_' . bin2hex(random_bytes(8)) . '.jpg';
        $targetPath = $uploadDir . '/' . $fileName;

        if (!$this->saveOptimized($file, $targetPath)) {
            throw new RuntimeException('Failed to process hero background. Please upload a JPG or PNG image.');
        }

        return self::UPLOAD_SUBDIR . '/' . $fileName;
    }

    public function deleteIfExists(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (str_contains($relativePath, '..')) {
            return;
        }

        $fullPath = dirname(__DIR__) . '/' . $relativePath;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function saveOptimized(array $file, string $targetPath): bool
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
            if ($origWidth >= $origHeight) {
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

        imagecopyresampled($destImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        $success = imagejpeg($destImg, $targetPath, self::JPEG_QUALITY);

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
