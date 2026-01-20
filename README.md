# BookStack Markdown Importer

Import Markdown files into BookStack books, including bulk ZIP uploads with optional chapter creation based on folder structure.

## Requirements

- BookStack v23+ (Laravel 10/11/12)
- PHP 8.2+
- PHP extensions: `zip`, `mbstring`, `dom`

## Installation

1) Install the package:

```bash
composer require pronomix-gmbh/bookstack-markdown-importer
```

2) Service provider registration:

- If auto-discovery is enabled (default in BookStack), no action is required.
- If auto-discovery is disabled, add the provider to `config/app.php`:

```php
BookStackMarkdownImporter\ServiceProvider::class,
```

3) Publish configuration (optional):

```bash
php artisan vendor:publish --tag=bookstack-markdown-importer-config
```

4) Clear caches:

```bash
php artisan cache:clear
php artisan view:clear
```

## Configuration

Config file: `config/bookstack-markdown-importer.php`

- `max_upload_mb` (int): Maximum upload size in MB.
- `allow_zip` (bool): Allow ZIP uploads containing multiple Markdown files.
- `create_chapters_from_folders_default` (bool): Default state for the "Create chapters from folders" checkbox.

Example:

```php
return [
    'max_upload_mb' => 20,
    'allow_zip' => true,
    'create_chapters_from_folders_default' => true,
];
```

You can also set the values via environment variables:

```
BOOKSTACK_MD_IMPORT_MAX_UPLOAD_MB=20
BOOKSTACK_MD_IMPORT_ALLOW_ZIP=true
BOOKSTACK_MD_IMPORT_CREATE_CHAPTERS_DEFAULT=true
```

## Usage

1) Open a book in BookStack.
2) Click **Import Markdown** in the book actions menu.
3) Upload a `.md` file or a `.zip` containing multiple `.md` files.
4) Optionally check **Create chapters from folders** for ZIP imports.
5) Submit to import pages. A summary is shown via flash messages.

Screenshot placeholders:

- `docs/screenshots/book-action.png`
- `docs/screenshots/import-form.png`
- `docs/screenshots/import-result.png`

## Behavior Details

- Single `.md` upload creates one page in the selected book.
- `.zip` uploads create multiple pages in alphabetical path order.
- Top-level ZIP folders become chapters (when enabled). Deeper folders are ignored for chapter naming, but files remain ordered by full path.
- Page title defaults to filename (without extension). If the first Markdown heading is `# Title`, it becomes the page title and is removed from the body.
- If a page name already exists in the same container (book or chapter), a numeric suffix is added: `My Page (2)`, `My Page (3)`, etc.

## Security Notes

- CSRF-protected form and authenticated routes.
- Permissions: Only users who can update the book and create pages can import. Chapter creation requires chapter create permission.
- Server-side validation of file type and size.
- Markdown is converted to HTML server-side using `league/commonmark` and sanitized with `HTMLPurifier`.
- ZIP handling uses PHP `ZipArchive` with safe-path checks; no external binaries are required.

## Uninstall

1) Remove the package:

```bash
composer remove pronomix-gmbh/bookstack-markdown-importer
```

2) Remove config file (if published):

```
config/bookstack-markdown-importer.php
```

3) Clear caches:

```bash
php artisan cache:clear
php artisan view:clear
```

## License

MIT
