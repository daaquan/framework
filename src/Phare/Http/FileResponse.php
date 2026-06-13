<?php

namespace Phare\Http;

use Phare\Filesystem\Filesystem;

class FileResponse extends Response
{
    protected string $filePath;

    protected ?string $name = null;

    protected bool $deleteFileAfterSend = false;

    protected Filesystem $files;

    /**
     * Byte offset to start streaming from (inclusive).
     */
    protected int $offset = 0;

    /**
     * Number of bytes to stream. Null means stream to end of file.
     */
    protected ?int $length = null;

    public function __construct(string $file, ?string $name = null, array $headers = [], ?string $disposition = null)
    {
        parent::__construct();

        $this->files = new Filesystem();
        $this->filePath = $file;
        $this->name = $name ?? basename($file);

        $this->withHeaders($headers);
        $this->setContentDisposition($disposition ?? 'attachment');
    }

    public static function create(string $file, ?string $name = null, array $headers = [], ?string $disposition = null): static
    {
        return new static($file, $name, $headers, $disposition);
    }

    public function send(): static
    {
        if (!$this->files->exists($this->filePath)) {
            throw new \RuntimeException("File does not exist at path: {$this->filePath}");
        }

        $this->setFileHeaders();
        $this->sendFile();

        if ($this->deleteFileAfterSend) {
            $this->files->delete($this->filePath);
        }

        return $this;
    }

    protected function setFileHeaders(): void
    {
        $size = $this->files->size($this->filePath);
        $mimeType = $this->files->mimeType($this->filePath) ?: 'application/octet-stream';

        $this->setContentType($mimeType);
        $this->setHeader('Content-Description', 'File Transfer');
        $this->setHeader('Content-Transfer-Encoding', 'binary');
        $this->setHeader('Cache-Control', 'must-revalidate');
        $this->setHeader('Pragma', 'public');
        $this->setHeader('Accept-Ranges', 'bytes');

        // Honor an HTTP Range request, falling back to the full body when the
        // header is absent or cannot be satisfied.
        $range = $this->resolveRange($size);

        if ($range === null) {
            $this->offset = 0;
            $this->length = null;
            $this->setHeader('Content-Length', $size);

            return;
        }

        [$start, $end] = $range;
        $this->offset = $start;
        $this->length = $end - $start + 1;

        $this->setStatusCode(206, 'Partial Content');
        $this->setHeader('Content-Range', "bytes {$start}-{$end}/{$size}");
        $this->setHeader('Content-Length', $this->length);
    }

    /**
     * Parse the request Range header into a [start, end] byte tuple.
     *
     * Returns null when no valid/satisfiable single range is requested, in
     * which case the full file should be served with a 200 status.
     *
     * @return array{0: int, 1: int}|null
     */
    protected function resolveRange(int $size): ?array
    {
        $header = $_SERVER['HTTP_RANGE'] ?? null;

        if (!is_string($header) || $header === '' || $size <= 0) {
            return null;
        }

        // Only "bytes" ranges are supported; ignore anything else.
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $matches)) {
            return null;
        }

        $startRaw = $matches[1];
        $endRaw = $matches[2];

        if ($startRaw === '' && $endRaw === '') {
            return null;
        }

        if ($startRaw === '') {
            // Suffix range: last N bytes.
            $length = (int)$endRaw;
            if ($length <= 0) {
                return null;
            }
            $start = max(0, $size - $length);
            $end = $size - 1;
        } else {
            $start = (int)$startRaw;
            $end = $endRaw === '' ? $size - 1 : (int)$endRaw;
        }

        if ($end > $size - 1) {
            $end = $size - 1;
        }

        // Unsatisfiable range — fall back to full content rather than 416 to
        // preserve backward-compatible behavior for clients.
        if ($start > $end || $start >= $size) {
            return null;
        }

        return [$start, $end];
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
        $handle = fopen($this->filePath, 'rb');

        if (!$handle) {
            throw new \RuntimeException("Cannot open file for reading: {$this->filePath}");
        }

        if ($this->offset > 0) {
            fseek($handle, $this->offset);
        }

        $remaining = $this->length;

        while (!feof($handle)) {
            $chunkSize = 8192;

            if ($remaining !== null) {
                if ($remaining <= 0) {
                    break;
                }
                $chunkSize = min($chunkSize, $remaining);
            }

            $buffer = fread($handle, $chunkSize);

            if ($buffer === false) {
                break;
            }

            echo $buffer;
            flush();

            if ($remaining !== null) {
                $remaining -= strlen($buffer);
            }
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
}
