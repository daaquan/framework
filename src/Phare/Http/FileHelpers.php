<?php

namespace Phare\Http;

trait FileHelpers
{
    public function file(?string $key = null): ?UploadedFile
    {
        return $this->hasFile($key) ? $this->convertUploadedFiles()[$key] : null;
    }

    public function allFiles(): array
    {
        return $this->convertUploadedFiles();
    }

    public function hasFile(string $key): bool
    {
        $files = $this->convertUploadedFiles();

        return !empty($files[$key]);
    }

    protected function convertUploadedFiles(): array
    {
        static $convertedFiles = null;

        if ($convertedFiles === null) {
            $convertedFiles = [];

            if (!empty($_FILES)) {
                foreach ($_FILES as $key => $file) {
                    $convertedFiles[$key] = $this->createUploadedFile($file);
                }
            }
        }

        return $convertedFiles;
    }

    protected function createUploadedFile(array $file): ?UploadedFile
    {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return UploadedFile::createFromArray($file);
    }

    public function validateFileUpload(UploadedFile $file, array $rules = []): array
    {
        $errors = [];

        // Check for upload errors
        if ($file->hasError()) {
            $errors[] = $file->getErrorMessage();

            return $errors;
        }

        // Validate file size
        if (isset($rules['max_size'])) {
            $maxSize = $rules['max_size'] * 1024; // Convert KB to bytes
            if ($file->getSize() > $maxSize) {
                $errors[] = "File size exceeds maximum allowed size of {$rules['max_size']} KB.";
            }
        }

        // Validate MIME type
        if (isset($rules['mime_types']) && is_array($rules['mime_types'])) {
            if (!in_array($file->getMimeType(), $rules['mime_types'])) {
                $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $rules['mime_types']);
            }
        }

        // Validate file extension
        if (isset($rules['extensions']) && is_array($rules['extensions'])) {
            $extension = $file->getClientOriginalExtension();
            if (!in_array(strtolower($extension), array_map('strtolower', $rules['extensions']))) {
                $errors[] = 'File extension not allowed. Allowed extensions: ' . implode(', ', $rules['extensions']);
            }
        }

        return $errors;
    }

    public function validateImage(UploadedFile $file, array $rules = []): array
    {
        $errors = $this->validateFileUpload($file, $rules);

        if (!empty($errors)) {
            return $errors;
        }

        // Check if it's a valid image
        if (!$this->isValidImage($file)) {
            $errors[] = 'The uploaded file is not a valid image.';

            return $errors;
        }

        // Get image dimensions
        $imageInfo = getimagesize($file->getPath());
        if (!$imageInfo) {
            $errors[] = 'Unable to read image dimensions.';

            return $errors;
        }

        [$width, $height] = $imageInfo;

        // Validate dimensions
        if (isset($rules['min_width']) && $width < $rules['min_width']) {
            $errors[] = "Image width must be at least {$rules['min_width']} pixels.";
        }

        if (isset($rules['max_width']) && $width > $rules['max_width']) {
            $errors[] = "Image width must not exceed {$rules['max_width']} pixels.";
        }

        if (isset($rules['min_height']) && $height < $rules['min_height']) {
            $errors[] = "Image height must be at least {$rules['min_height']} pixels.";
        }

        if (isset($rules['max_height']) && $height > $rules['max_height']) {
            $errors[] = "Image height must not exceed {$rules['max_height']} pixels.";
        }

        return $errors;
    }

    protected function isValidImage(UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType();

        $validImageTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
        ];

        return in_array($mimeType, $validImageTypes);
    }
}
