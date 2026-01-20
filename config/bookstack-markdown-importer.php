<?php

return [
    // Maximum upload size in megabytes.
    'max_upload_mb' => env('BOOKSTACK_MD_IMPORT_MAX_UPLOAD_MB', 20),

    // Allow ZIP uploads containing multiple Markdown files.
    'allow_zip' => env('BOOKSTACK_MD_IMPORT_ALLOW_ZIP', true),

    // Default state for the "Create chapters from folders" option.
    'create_chapters_from_folders_default' => env('BOOKSTACK_MD_IMPORT_CREATE_CHAPTERS_DEFAULT', true),
];
