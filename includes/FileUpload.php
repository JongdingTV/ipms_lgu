<?php
// ============================================================
// includes/FileUpload.php — shared upload validation + storage.
// Consolidates the pattern already used in citizen/register.php,
// contractor/api/portal.php ('document' action), and engineer/api/portal.php
// ('photo' action): UPLOAD_ERR_OK check -> size cap -> extension whitelist ->
// content-sniff (images via getimagesize, PDFs via magic bytes) -> safe random
// filename -> move_uploaded_file(). Existing call-sites are left as-is this
// phase; only new code (superadmin document uploads) is wired to this class.
// ============================================================

final class FileUploadException extends RuntimeException
{
}

final class FileUpload
{
    private const DEFAULT_MAX_SIZE = 10 * 1024 * 1024;
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg'];

    /**
     * Returns null if valid, else a human-readable error message.
     * $constraints: required(bool, default true), max_size(bytes, default 10MB),
     * extensions(string[] lowercase no-dot), sniff_images(bool, default true), sniff_pdf(bool, default true).
     */
    public static function validate(?array $fileEntry, array $constraints): ?string
    {
        $required = $constraints['required'] ?? true;
        $error = $fileEntry['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($fileEntry === null || $error === UPLOAD_ERR_NO_FILE) {
            return $required ? 'A file is required.' : null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            return 'Upload failed. Please choose a valid file.';
        }

        $maxSize = (int) ($constraints['max_size'] ?? self::DEFAULT_MAX_SIZE);
        if ((int) ($fileEntry['size'] ?? 0) > $maxSize) {
            return 'File size must be ' . self::formatBytes($maxSize) . ' or smaller.';
        }

        $extensions = array_map('strtolower', $constraints['extensions'] ?? []);
        $originalName = (string) ($fileEntry['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extensions !== [] && !in_array($extension, $extensions, true)) {
            return 'Allowed files: ' . strtoupper(implode(', ', $extensions)) . '.';
        }

        $tmpName = (string) ($fileEntry['tmp_name'] ?? '');
        if (!is_uploaded_file($tmpName)) {
            return 'Upload failed. Please choose a valid file.';
        }

        if (($constraints['sniff_images'] ?? true) && in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            if (@getimagesize($tmpName) === false) {
                return 'Image content is not a valid image.';
            }
        }

        if (($constraints['sniff_pdf'] ?? true) && $extension === 'pdf' && !self::looksLikePdf($tmpName)) {
            return 'PDF content is not a valid PDF file.';
        }

        return null;
    }

    /**
     * Re-validates internally (cannot be skipped), then moves the file into storage.
     * Returns ['original_name','stored_path','file_size','mime_type','extension']. Throws FileUploadException on failure.
     */
    public static function store(array $fileEntry, string $subfolder, array $constraints): array
    {
        $error = self::validate($fileEntry, $constraints);
        if ($error !== null) {
            throw new FileUploadException($error);
        }

        $originalName = (string) $fileEntry['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $subfolder = trim($subfolder, '/');

        $uploadRoot = dirname(__DIR__) . '/uploads/' . $subfolder . '/' . date('Y');
        if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0775, true) && !is_dir($uploadRoot)) {
            throw new FileUploadException('Unable to prepare upload folder.');
        }

        $safeName = self::safeBaseName(pathinfo($originalName, PATHINFO_FILENAME));
        $storedName = time() . '-' . bin2hex(random_bytes(4)) . '-' . $safeName . '.' . $extension;
        $destination = $uploadRoot . '/' . $storedName;

        if (!move_uploaded_file($fileEntry['tmp_name'], $destination)) {
            throw new FileUploadException('Unable to save uploaded file.');
        }

        $mimeType = function_exists('mime_content_type') ? (mime_content_type($destination) ?: null) : ($fileEntry['type'] ?? null);

        return [
            'original_name' => $originalName,
            'stored_path' => 'uploads/' . $subfolder . '/' . date('Y') . '/' . $storedName,
            'file_size' => (int) ($fileEntry['size'] ?? (is_file($destination) ? filesize($destination) : 0)),
            'mime_type' => $mimeType,
            'extension' => $extension,
        ];
    }

    /** Re-maps $_FILES['documents']['name'][$i]/['tmp_name'][$i]/... into a single-file array, or null if that row is empty. */
    public static function fromNestedFiles(array $filesField, int $index): ?array
    {
        if (!isset($filesField['name'][$index])) {
            return null;
        }

        $name = (string) ($filesField['name'][$index] ?? '');
        $error = $filesField['error'][$index] ?? UPLOAD_ERR_NO_FILE;

        if ($name === '' && $error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return [
            'name' => $name,
            'type' => $filesField['type'][$index] ?? '',
            'tmp_name' => $filesField['tmp_name'][$index] ?? '',
            'error' => $error,
            'size' => $filesField['size'][$index] ?? 0,
        ];
    }

    private static function safeBaseName(string $name): string
    {
        $name = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
        $name = trim($name, '.-');
        return $name !== '' ? substr($name, 0, 80) : 'file';
    }

    private static function looksLikePdf(string $tmpPath): bool
    {
        $handle = @fopen($tmpPath, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = fread($handle, 5);
        fclose($handle);
        return $header === '%PDF-';
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . 'MB';
        }
        return round($bytes / 1024) . 'KB';
    }
}
