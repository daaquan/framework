<?php

use Phare\Http\UploadedFile;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/phare-upload-' . bin2hex(random_bytes(6));
    mkdir($this->tempDir);
    $this->testFile = tempnam($this->tempDir, 'test_upload_');
    file_put_contents($this->testFile, 'test content');
});

afterEach(function () {
    foreach (glob($this->tempDir . '/*') as $path) {
        unlink($path);
    }

    rmdir($this->tempDir);
});

it('creates uploaded file from array', function () {
    $fileData = [
        'tmp_name' => $this->testFile,
        'name' => 'test.txt',
        'type' => 'text/plain',
        'size' => 12,
        'error' => UPLOAD_ERR_OK,
    ];

    $file = UploadedFile::createFromArray($fileData);

    expect($file->getClientOriginalName())->toBe('test.txt');
    expect($file->getMimeType())->toBe('text/plain');
    expect($file->getSize())->toBe(12);
    expect($file->getError())->toBe(UPLOAD_ERR_OK);
});

it('detects if file is valid', function () {
    $validFile = new UploadedFile($this->testFile, 'test.txt', 'text/plain', 12, UPLOAD_ERR_OK);
    expect($validFile->isValid())->toBeTrue();

    $invalidFile = new UploadedFile('/nonexistent', 'test.txt', 'text/plain', 12, UPLOAD_ERR_NO_FILE);
    expect($invalidFile->isValid())->toBeFalse();
});

it('gets file content', function () {
    $file = new UploadedFile($this->testFile, 'test.txt', 'text/plain', 12, UPLOAD_ERR_OK);
    expect($file->getContent())->toBe('test content');
});

it('gets file size', function () {
    $file = new UploadedFile($this->testFile, 'test.txt', 'text/plain', 12, UPLOAD_ERR_OK);
    expect($file->getSize())->toBe(12);
});

it('detects mime type', function () {
    $file = new UploadedFile($this->testFile, 'test.txt', null, 12, UPLOAD_ERR_OK);
    $mimeType = $file->getMimeType();
    expect($mimeType)->toContain('text');
});

it('guesses file extension', function () {
    $file = new UploadedFile($this->testFile, 'test.txt', 'text/plain', 12, UPLOAD_ERR_OK);
    expect($file->guessExtension())->toBe('txt');

    $imageFile = new UploadedFile($this->testFile, 'image.jpg', 'image/jpeg', 1000, UPLOAD_ERR_OK);
    expect($imageFile->guessExtension())->toBe('jpg');
});

it('gets client original extension', function () {
    $file = new UploadedFile($this->testFile, 'document.pdf', 'application/pdf', 1000, UPLOAD_ERR_OK);
    expect($file->getClientOriginalExtension())->toBe('pdf');
});

it('generates hash name', function () {
    $file = new UploadedFile($this->testFile, 'test.txt', 'text/plain', 12, UPLOAD_ERR_OK);
    $hashName = $file->hashName();

    expect($hashName)->toMatch('/^[a-f0-9]{64}\.txt$/');

    $hashNameWithPath = $file->hashName('uploads/docs');
    expect($hashNameWithPath)->toMatch('/^uploads\/docs\/[a-f0-9]{64}\.txt$/');
});

it('moves file to new location', function () {
    $file = new UploadedFile($this->testFile, 'test.txt', 'text/plain', 12, UPLOAD_ERR_OK, true);
    $destination = $this->tempDir . '/moved_file.txt';

    $movedFile = $file->move($this->tempDir, 'moved_file.txt');

    expect(file_exists($destination))->toBeTrue();
    expect($movedFile->getPath())->toBe($destination);

    // Cleanup
    unlink($destination);
});

it('stores file with custom name', function () {
    $file = new UploadedFile($this->testFile, 'test.txt', 'text/plain', 12, UPLOAD_ERR_OK, true);
    $path = $file->storeAs($this->tempDir, 'custom_name.txt');

    expect($path)->toBe($this->tempDir . '/custom_name.txt')
        ->and(file_get_contents($path))->toBe('test content');
});

it('rejects path traversal storage names', function () {
    $file = new UploadedFile($this->testFile, 'test.txt', 'text/plain', 12, UPLOAD_ERR_OK, true);

    expect(fn () => $file->storeAs('uploads', '../evil.txt'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $file->move($this->tempDir, 'nested/evil.txt'))->toThrow(InvalidArgumentException::class);
});

it('stores file with generated name', function () {
    $file = new UploadedFile($this->testFile, 'test.txt', 'text/plain', 12, UPLOAD_ERR_OK, true);
    $path = $file->store($this->tempDir);

    expect(dirname($path))->toBe($this->tempDir)
        ->and(basename($path))->toMatch('/^[a-f0-9]{64}\.txt$/')
        ->and(file_get_contents($path))->toBe('test content');
});

it('provides error messages', function () {
    $file = new UploadedFile($this->testFile, 'test.txt', 'text/plain', 12, UPLOAD_ERR_INI_SIZE);
    expect($file->hasError())->toBeTrue();
    expect($file->getErrorMessage())->toContain('upload_max_filesize');
});

it('creates fake files for testing', function () {
    $fakeFile = UploadedFile::fake();

    expect($fakeFile->getClientOriginalName())->toBe('fake.txt');
    expect($fakeFile->getMimeType())->toBe('text/plain');
    expect($fakeFile->getSize())->toBe(100);

    unlink($fakeFile->getPath());
});

it('gets max filesize from php ini', function () {
    $file = new UploadedFile($this->testFile, 'test.txt', 'text/plain', 12, UPLOAD_ERR_OK);
    $maxSize = $file->getMaxFilesize();

    expect($maxSize)->toBeGreaterThan(0);
});

it('converts to string', function () {
    $file = new UploadedFile($this->testFile, 'test.txt', 'text/plain', 12, UPLOAD_ERR_OK);
    expect((string)$file)->toBe($this->testFile);
});

it('parses ini size strings with and without units', function () {
    $file = new UploadedFile($this->testFile, 'test.txt', 'text/plain', 12, UPLOAD_ERR_OK);

    $method = new ReflectionMethod($file, 'parseSize');
    $parseSize = fn (string $size): int => $method->invoke($file, $size);

    expect($parseSize('-1'))->toBe(PHP_INT_MAX)
        ->and($parseSize('2M'))->toBe(2 * 1024 * 1024)
        ->and($parseSize('512K'))->toBe(512 * 1024)
        ->and($parseSize('1G'))->toBe(1024 * 1024 * 1024)
        ->and($parseSize('8388608'))->toBe(8388608)
        // No digits and no unit: must not blow up on preg_replace returning ''.
        ->and($parseSize(''))->toBe(0);
});
