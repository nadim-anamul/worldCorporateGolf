<?php

declare(strict_types=1);

class ProfilePhotoService
{
  private const MAX_BYTES = 5 * 1024 * 1024;
  private const ALLOWED_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

  public function validateUpload(array $file): void
  {
    if (!isset($file['tmp_name'], $file['error'])) {
      throw new RuntimeException('Please upload a profile photo.');
    }

    $error = (int)$file['error'];
    if ($error !== UPLOAD_ERR_OK) {
      $message = match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Profile photo is too large. Max file size is 5MB.',
        UPLOAD_ERR_PARTIAL => 'Profile photo upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Please upload a profile photo.',
        default => 'Profile photo upload failed. Please try again.',
      };
      throw new RuntimeException($message);
    }

    $tmpPath = (string)$file['tmp_name'];
    if ($tmpPath === '' || !is_readable($tmpPath)) {
      throw new RuntimeException('Please upload a profile photo.');
    }

    if (($file['size'] ?? 0) > self::MAX_BYTES) {
      throw new RuntimeException('Profile photo is too large. Max file size is 5MB.');
    }

    if (!is_uploaded_file($tmpPath) && @getimagesize($tmpPath) === false) {
      throw new RuntimeException('Please upload a profile photo.');
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
      throw new RuntimeException('Invalid profile photo file type. Please upload a JPG, PNG, GIF, or WebP image.');
    }

    if (!$allowed && $imageInfo === false) {
      throw new RuntimeException('Invalid profile photo file type. Please upload a JPG, PNG, GIF, or WebP image.');
    }
  }

  public function saveOptimized(array $file, string $targetPath): bool
  {
    $srcPath = $file['tmp_name'];

    if (!extension_loaded('gd')) {
      return false;
    }

    $info = getimagesize($srcPath);
    if (!$info) {
      return false;
    }

    $mime = $info['mime'];
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
    $maxDimension = 600;
    $newWidth = $origWidth;
    $newHeight = $origHeight;

    if ($origWidth > $maxDimension || $origHeight > $maxDimension) {
      if ($origWidth > $origHeight) {
        $newWidth = $maxDimension;
        $newHeight = (int)round(($origHeight / $origWidth) * $maxDimension);
      } else {
        $newHeight = $maxDimension;
        $newWidth = (int)round(($origWidth / $origHeight) * $maxDimension);
      }
    }

    $destImg = imagecreatetruecolor($newWidth, $newHeight);
    if (!$destImg) {
      imagedestroy($srcImg);
      return false;
    }

    if ($mime === 'image/png' || $mime === 'image/gif') {
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

    $success = imagejpeg($destImg, $targetPath, 80);
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
