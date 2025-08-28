<?php

namespace Phare\Http;

use Phare\Filesystem\Filesystem;
use Phalcon\Http\Response;

class FileResponse extends Response
{
    protected string $file;
    protected ?string $name = null;
    protected bool $deleteFileAfterSend = false;
    protected Filesystem $files;

    public function __construct(string $file, ?string $name = null, array $headers = [], ?string $disposition = null)
    {
        parent::__construct();
        
        $this->files = new Filesystem();
        $this->file = $file;
        $this->name = $name ?? basename($file);
        
        $this->setHeaders($headers);
        $this->setContentDisposition($disposition ?? 'attachment');
    }

    public static function create(string $file, ?string $name = null, array $headers = [], ?string $disposition = null): static
    {
        return new static($file, $name, $headers, $disposition);
    }

    public function send(): static
    {
        if (!$this->files->exists($this->file)) {
            throw new \RuntimeException("File does not exist at path: {$this->file}");
        }

        $this->setFileHeaders();
        $this->sendFile();

        if ($this->deleteFileAfterSend) {
            $this->files->delete($this->file);
        }

        return $this;
    }

    protected function setFileHeaders(): void
    {
        $size = $this->files->size($this->file);
        $mimeType = $this->files->mimeType($this->file) ?: 'application/octet-stream';

        $this->setContentType($mimeType);
        $this->setHeader('Content-Length', $size);
        $this->setHeader('Content-Description', 'File Transfer');
        $this->setHeader('Content-Transfer-Encoding', 'binary');
        $this->setHeader('Cache-Control', 'must-revalidate');
        $this->setHeader('Pragma', 'public');
        $this->setHeader('Accept-Ranges', 'bytes');
    }

    protected function setContentDisposition(string $disposition): void
    {
        $filename = $this->name;
        
        // Handle special characters in filename
        if (preg_match('/[^\x20-\x7e]|[%"]/', $filename)) {
            $fallbackName = preg_replace('/[^\x20-\x7e]/', '', $filename);
            $encodedName = rawurlencode($filename);
            
            $this->setHeader('Content-Disposition', 
                "{$disposition}; filename=\"{$fallbackName}\"; filename*=UTF-8''{$encodedName}");
        } else {
            $this->setHeader('Content-Disposition', "{$disposition}; filename=\"{$filename}\"");
        }
    }

    protected function sendFile(): void
    {
        $handle = fopen($this->file, 'rb');
        
        if (!$handle) {
            throw new \RuntimeException("Cannot open file for reading: {$this->file}");
        }

        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }

        fclose($handle);
    }

    public function deleteFileAfterSend(bool $delete = true): static
    {
        $this->deleteFileAfterSend = $delete;
        return $this;
    }

    public function stream(): StreamedResponse
    {
        return new StreamedResponse(function () {
            $this->sendFile();
        }, 200, $this->getHeaders()->toArray());
    }

    public function inline(?string $filename = null): static
    {
        $this->name = $filename ?? $this->name;
        $this->setContentDisposition('inline');
        return $this;
    }

    public function download(?string $filename = null): static
    {
        $this->name = $filename ?? $this->name;
        $this->setContentDisposition('attachment');
        return $this;
    }

    public function setHeaders(array $headers): static
    {
        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value);
        }
        return $this;
    }
}