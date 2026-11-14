<?php

namespace App\Data;

/**
 * Typed return object for a report rendered to disk and awaiting delivery.
 *
 * filePath is an absolute path on the local disk, not a storage-relative one,
 * so it can be handed straight to the mailer and to unlink().
 */
class GeneratedReportFile
{
    public string $filePath;
    public string $title;
}
