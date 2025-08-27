<?php

namespace Phare\Http;

use Phare\Filesystem\Filesystem;

class UploadedFile
{
    protected string $path;
    protected ?string $originalName;
    protected ?string $mimeType;
    protected ?int $size;
    protected int $error;
    protected bool $test = false;
    protected Filesystem $files;

    public function __construct(
        string $path,
        ?string $originalName = null,
        ?string $mimeType = null,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        bool $test = false
    ) {
        $this->path = $path;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->error = $error;
        $this->test = $test;
        $this->files = new Filesystem();
    }

    public static function fake(): static
    {
        return new static(
            tempnam(sys_get_temp_dir(), 'fake'),
            'fake.txt',
            'text/plain',
            100,
            UPLOAD_ERR_OK,
            true
        );
    }

    public static function createFromArray(array $file): static
    {
        return new static(
            $file['tmp_name'],
            $file['name'] ?? null,
            $file['type'] ?? null,
            $file['size'] ?? null,
            $file['error'] ?? UPLOAD_ERR_OK
        );
    }

    public function store(string $path, ?string $name = null, string $disk = 'local'): string|false
    {
        return $this->storeAs($path, $name ?? $this->hashName(), $disk);
    }

    public function storeAs(string $path, string $name, string $disk = 'local'): string|false
    {
        $destination = rtrim($path, '/') . '/' . $name;

        if ($this->isValid()) {
            if ($this->test) {
                return $this->files->put($destination, $this->getContent()) ? $destination : false;
            }
            
            return move_uploaded_file($this->path, $destination) ? $destination : false;
        }

        return false;
    }

    public function move(string $directory, ?string $name = null): static
    {
        $name = $name ?? $this->hashName();
        $destination = $directory . '/' . $name;

        if ($this->isValid()) {
            if ($this->test || move_uploaded_file($this->path, $destination)) {
                $this->path = $destination;
                return $this;
            }
        }

        throw new \RuntimeException('Could not move uploaded file.');
    }

    public function getContent(): string|false
    {
        return file_get_contents($this->path);
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && $this->isFile();
    }

    public function isFile(): bool
    {
        return is_file($this->path);
    }

    public function getSize(): int
    {
        return $this->size ?? filesize($this->path) ?? 0;
    }

    public function getMimeType(): ?string
    {
        if (!$this->mimeType && $this->isFile()) {
            $this->mimeType = mime_content_type($this->path) ?: null;
        }

        return $this->mimeType;
    }

    public function guessExtension(): ?string
    {
        $mimeType = $this->getMimeType();
        
        if (!$mimeType) {
            return null;
        }

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/pdf' => 'pdf',
            'application/json' => 'json',
            'application/xml' => 'xml',
            'application/zip' => 'zip',
        ];

        return $extensions[$mimeType] ?? null;
    }

    public function getClientOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function getClientOriginalExtension(): ?string
    {
        if (!$this->originalName) {
            return null;
        }

        return pathinfo($this->originalName, PATHINFO_EXTENSION);
    }

    public function hashName(?string $path = null): string
    {
        $extension = $this->guessExtension() ?: $this->getClientOriginalExtension();
        $hash = hash('sha256', uniqid('', true));
        
        if ($path) {
            return $path . '/' . $hash . ($extension ? '.' . $extension : '');
        }

        return $hash . ($extension ? '.' . $extension : '');
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getRealPath(): string|false
    {
        return realpath($this->path);
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getErrorMessage(): string
    {
        static $errors = [
            UPLOAD_ERR_INI_SIZE => 'The file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
        ];

        return $errors[$this->error] ?? 'Unknown upload error';
    }

    public function hasError(): bool
    {
        return $this->error !== UPLOAD_ERR_OK;
    }

    public function getMaxFilesize(): int
    {
        $maxUpload = $this->parseSize(ini_get('upload_max_filesize'));
        $maxPost = $this->parseSize(ini_get('post_max_size'));
        $memoryLimit = $this->parseSize(ini_get('memory_limit'));

        return min($maxUpload, $maxPost, $memoryLimit);
    }

    protected function parseSize(string $size): int
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }

        return round($size);
    }

    public function __toString(): string
    {
        return $this->path;
    }
}