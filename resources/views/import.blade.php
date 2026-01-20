@extends('layouts.simple')

@push('head')
    <style>
        .md-import-card {
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
        }

        .md-import-hero {
            padding: 16px 18px;
            margin-bottom: 16px;
            border-radius: 6px;
            background: #fff;
            border: 1px solid rgba(30, 86, 49, 0.14);
        }

        .md-import-hero h1 {
            margin: 0 0 6px 0;
        }

        .md-import-section {
            margin-bottom: 16px;
        }

        .md-dropzone {
            position: relative;
            width: 100%;
            border: 1px dashed rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 16px;
            background: #f8faf9;
            transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
        }

        .md-dropzone.is-dragover {
            border-color: rgba(30, 86, 49, 0.7);
            background: #f2f7f3;
            box-shadow: 0 0 0 3px rgba(30, 86, 49, 0.12);
        }

        .md-dropzone-input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .md-dropzone-content {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .md-dropzone-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: rgba(30, 86, 49, 0.12);
            color: #1e5631;
            flex-shrink: 0;
        }

        .md-dropzone-text {
            flex: 1 1 240px;
        }

        .md-dropzone-title {
            margin: 0;
            font-size: 1rem;
        }

        .md-dropzone-subtitle {
            margin: 2px 0 0;
            font-size: 0.875rem;
            color: rgba(0, 0, 0, 0.6);
        }

        .md-file-list {
            margin-top: 12px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 6px;
            background: #fff;
        }

        .md-file-list ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .md-file-list li {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            font-size: 0.9rem;
        }

        .md-file-list li:last-child {
            border-bottom: none;
        }

        .md-file-empty {
            color: rgba(0, 0, 0, 0.6);
            font-style: italic;
        }

        .md-file-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .md-file-size {
            color: rgba(0, 0, 0, 0.6);
            white-space: nowrap;
        }

        .md-import-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .md-import-info {
            width: 100%;
            padding: 12px 14px;
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .md-import-info h4 {
            margin: 0 0 8px;
        }

        .md-import-info p {
            margin: 0 0 6px;
        }

        .md-import-hint {
            margin: 8px 0 0;
            color: rgba(0, 0, 0, 0.6);
            font-size: 0.85rem;
        }

        .md-import-error {
            border: 1px solid rgba(180, 20, 30, 0.35);
            background: rgba(180, 20, 30, 0.06);
            border-radius: 6px;
            padding: 12px 14px;
        }
    </style>
@endpush

@section('body')
    <div class="container small">
        <div class="my-s">
            @include('entities.breadcrumbs', ['crumbs' => [
                $book,
                $book->getUrl('/markdown-import') => [
                    'text' => trans('bookstack-markdown-importer::messages.import'),
                    'icon' => 'file'
                ]
            ]])
        </div>

        <main class="content-wrap card md-import-card">
            <div class="md-import-hero">
                <h1 class="list-heading">{{ trans('bookstack-markdown-importer::messages.import') }}</h1>
                <p class="text-muted">{{ trans('bookstack-markdown-importer::messages.hero_description') }}</p>
            </div>

            @if ($errors->any())
                <div class="text-neg mb-m md-import-error">
                    <p class="mb-xs">{{ trans('bookstack-markdown-importer::messages.errors_heading') }}</p>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ $book->getUrl('/markdown-import') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="md-import-section">
                    <div class="md-dropzone" id="md-import-dropzone">
                        <input id="markdown-file"
                               class="md-dropzone-input"
                               type="file"
                               name="file[]"
                               required
                               multiple
                               accept=".md,.markdown,.txt,.html,.htm,.zip">

                        <div class="md-dropzone-content">
                            <div class="md-dropzone-icon">@icon('file')</div>
                            <div class="md-dropzone-text">
                                <p class="md-dropzone-title">{{ trans('bookstack-markdown-importer::messages.dropzone_title') }}</p>
                                <p class="md-dropzone-subtitle">{{ trans('bookstack-markdown-importer::messages.dropzone_subtitle') }}</p>
                            </div>
                            <button type="button" class="button outline" id="md-import-browse">{{ trans('bookstack-markdown-importer::messages.dropzone_browse') }}</button>
                        </div>
                    </div>

                    <div class="md-file-list" aria-live="polite">
                        <ul id="md-import-file-list">
                            <li class="md-file-empty">{{ trans('bookstack-markdown-importer::messages.dropzone_no_files') }}</li>
                        </ul>
                    </div>

                    <p class="md-import-hint">{{ trans('bookstack-markdown-importer::messages.hint_max_size', ['size' => (int) config('bookstack-markdown-importer.max_upload_mb', 20)]) }}</p>
                </div>

                <div class="md-import-section">
                    @include('form.custom-checkbox', [
                        'name' => 'create_chapters',
                        'value' => 'true',
                        'checked' => old('create_chapters', $createChaptersDefault),
                        'label' => trans('bookstack-markdown-importer::messages.checkbox_label')
                    ])
                    <p class="text-muted small">{{ trans('bookstack-markdown-importer::messages.checkbox_help') }}</p>
                </div>

                <div class="md-import-section md-import-actions">
                    <button type="submit" class="button">{{ trans('bookstack-markdown-importer::messages.action_import') }}</button>
                    <a href="{{ $book->getUrl() }}" class="button outline">{{ trans('bookstack-markdown-importer::messages.action_cancel') }}</a>
                </div>

                <div class="md-import-info">
                    <h4>{{ trans('bookstack-markdown-importer::messages.sidebar_title') }}</h4>
                    <p>{{ trans('bookstack-markdown-importer::messages.sidebar_item_title') }}</p>
                    <p>{{ trans('bookstack-markdown-importer::messages.sidebar_item_order') }}</p>
                    <p>{{ trans('bookstack-markdown-importer::messages.sidebar_item_chapters') }}</p>
                    <p>{{ trans('bookstack-markdown-importer::messages.sidebar_item_collisions') }}</p>
                </div>
            </form>
        </main>
    </div>
@stop

@push('body-end')
    <script nonce="{{ $cspNonce ?? '' }}">
        (function () {
            var dropzone = document.getElementById('md-import-dropzone');
            var input = document.getElementById('markdown-file');
            var list = document.getElementById('md-import-file-list');
            var browse = document.getElementById('md-import-browse');

            if (!dropzone || !input || !list || !browse) {
                return;
            }

            var strings = {
                noFiles: @json(trans('bookstack-markdown-importer::messages.dropzone_no_files')),
                sizeUnit: @json(trans('bookstack-markdown-importer::messages.size_unit_kb'))
            };

            var escapeHtml = function (value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            var renderFiles = function (files) {
                list.innerHTML = '';
                if (!files || files.length === 0) {
                    var empty = document.createElement('li');
                    empty.className = 'md-file-empty';
                    empty.textContent = strings.noFiles;
                    list.appendChild(empty);
                    return;
                }

                Array.prototype.slice.call(files).forEach(function (file) {
                    var item = document.createElement('li');
                    var name = document.createElement('span');
                    var size = document.createElement('span');
                    var sizeKb = Math.max(1, Math.round((file.size || 0) / 1024));

                    name.className = 'md-file-name';
                    name.textContent = file.name || 'file';
                    size.className = 'md-file-size';
                    size.textContent = sizeKb + ' ' + strings.sizeUnit;

                    item.appendChild(name);
                    item.appendChild(size);
                    list.appendChild(item);
                });
            };

            browse.addEventListener('click', function () {
                input.click();
            });

            input.addEventListener('change', function () {
                renderFiles(input.files);
            });

            dropzone.addEventListener('dragover', function (event) {
                event.preventDefault();
                dropzone.classList.add('is-dragover');
            });

            dropzone.addEventListener('dragleave', function (event) {
                event.preventDefault();
                dropzone.classList.remove('is-dragover');
            });

            dropzone.addEventListener('drop', function (event) {
                event.preventDefault();
                dropzone.classList.remove('is-dragover');

                if (event.dataTransfer && event.dataTransfer.files) {
                    try {
                        input.files = event.dataTransfer.files;
                    } catch (error) {
                        // Ignore if browser blocks assignment.
                    }
                    renderFiles(event.dataTransfer.files);
                }
            });
        })();
    </script>
@endpush
