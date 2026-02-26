<?php

namespace App\Contracts\FileVision;

/**
 * Describes a file (e.g. type, MIME, content) using vision/ML.
 *
 * Used to interpret stored files (e.g. snapshots or attachments). Implementations
 * may call an AI vision API. Result is FileInformation (path, extension, mime, confidence).
 */
interface FileVision
{
    /**
     * Analyze a file and return metadata (extension, mime type, confidence, etc.).
     *
     * @param  string  $filePath  Path to the file (e.g. on default disk)
     * @return FileInformation  Path, extension, mime type, confidence, optional description
     */
    public function describe(string $filePath): FileInformation;
}