<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

migration_require_post();

migration_handle(static function (): void {
    $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['upload_id'] ?? ''));
    $chunkIndex = (int)($_POST['chunk_index'] ?? -1);
    $totalChunks = (int)($_POST['total_chunks'] ?? 0);
    $originalName = basename((string)($_POST['original_name'] ?? 'wordpress-import.sql'));

    if ($uploadId === '' || $chunkIndex < 0 || $totalChunks <= 0 || empty($_FILES['chunk']['tmp_name'])) {
        migration_json(['ok' => false, 'message' => 'Upload chunk is incomplete.'], 422);
    }

    $importDir = APP_ROOT . '/storage/imports';
    if (!is_dir($importDir)) {
        mkdir($importDir, 0775, true);
    }

    $partPath = $importDir . '/' . $uploadId . '.part';
    $finalName = $uploadId . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '-', $originalName);
    if (!str_ends_with(strtolower($finalName), '.sql')) {
        $finalName .= '.sql';
    }
    $finalPath = $importDir . '/' . $finalName;

    $input = fopen((string)$_FILES['chunk']['tmp_name'], 'rb');
    $output = fopen($partPath, $chunkIndex === 0 ? 'wb' : 'ab');
    if (!$input || !$output) {
        migration_json(['ok' => false, 'message' => 'Unable to write upload chunk.'], 500);
    }

    stream_copy_to_stream($input, $output);
    fclose($input);
    fclose($output);

    $complete = $chunkIndex + 1 >= $totalChunks;
    if ($complete) {
        rename($partPath, $finalPath);
        audit('migration.dump_uploaded', 'migration_dump', null, [], ['path' => $finalPath]);
    }

    migration_json([
        'ok' => true,
        'complete' => $complete,
        'source_path' => $complete ? $finalPath : null,
        'message' => $complete ? 'SQL dump uploaded.' : 'Chunk uploaded.',
    ]);
});
