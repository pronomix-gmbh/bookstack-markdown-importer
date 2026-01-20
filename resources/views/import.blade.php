@extends('layouts.simple')

@push('head')
    <style>
        .md-import-card {
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.08);
        }

        .md-import-hero {
            padding: 18px 20px;
            margin: 0 0 16px 0;
            border-radius: 6px;
            background: radial-gradient(circle at top left, rgba(30, 86, 49, 0.12), rgba(30, 86, 49, 0.02));
            border: 1px solid rgba(30, 86, 49, 0.15);
        }

        .md-import-hero h1 {
            margin: 0 0 6px 0;
        }

        .md-import-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }

        @media (min-width: 960px) {
            .md-import-grid {
                grid-template-columns: 1.1fr 0.9fr;
                align-items: start;
            }
        }

        .md-import-panel {
            padding: 16px;
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .md-import-panel h4 {
            margin-top: 0;
        }

        .md-import-dropzone {
            position: relative;
            border-radius: 10px;
            padding: 20px;
            border: 1px dashed rgba(0, 0, 0, 0.35);
            background: linear-gradient(140deg, rgba(255, 255, 255, 0.85), rgba(245, 247, 246, 0.95));
            transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
            overflow: hidden;
        }

        .md-import-dropzone::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 20% 0%, rgba(30, 86, 49, 0.12), transparent 55%);
            opacity: 0;
            transition: opacity 200ms ease;
            pointer-events: none;
        }

        .md-import-dropzone.is-dragover {
            border-color: rgba(30, 86, 49, 0.6);
            box-shadow: 0 0 0 4px rgba(30, 86, 49, 0.12);
            transform: translateY(-2px);
        }

        .md-import-dropzone.is-dragover::after {
            opacity: 1;
        }

        .md-import-dropzone.is-ready {
            border-color: rgba(0, 140, 90, 0.6);
            box-shadow: inset 0 0 0 1px rgba(0, 140, 90, 0.25);
        }

        .md-import-dropzone.is-drop {
            animation: md-import-drop 420ms ease;
        }

        @keyframes md-import-drop {
            0% { transform: scale(1); }
            35% { transform: scale(1.01); }
            100% { transform: scale(1); }
        }

        .md-import-dropzone-inner {
            display: grid;
            gap: 12px;
        }

        .md-import-dropzone-header {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .md-import-dropzone-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(30, 86, 49, 0.12);
            color: #1e5631;
        }

        .md-import-dropzone-title {
            margin: 0;
            font-size: 1rem;
        }

        .md-import-dropzone-subtitle {
            margin: 0;
            color: rgba(0, 0, 0, 0.6);
            font-size: 0.875rem;
        }

        .md-import-dropzone-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .md-import-file-input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .md-import-file-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.85rem;
            background: rgba(30, 86, 49, 0.08);
            color: rgba(0, 0, 0, 0.7);
        }

        .md-import-file-pill strong {
            color: #1e5631;
        }

        .md-import-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .md-import-meta {
            display: grid;
            gap: 8px;
            font-size: 0.875rem;
            color: rgba(0, 0, 0, 0.65);
        }

        .md-import-error {
            border: 1px solid rgba(180, 20, 30, 0.35);
            background: rgba(180, 20, 30, 0.06);
            border-radius: 6px;
            padding: 12px 14px;
        }

        .md-import-hint {
            margin: 0;
            font-size: 0.85rem;
            color: rgba(0, 0, 0, 0.55);
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

            <div class="md-import-grid">
                <form action="{{ $book->getUrl('/markdown-import') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="form-group">
                        <div class="md-import-dropzone" data-dropzone>
                            <input id="markdown-file"
                                   class="md-import-file-input"
                                   type="file"
                                   name="file[]"
                                   required
                                   multiple
                                   accept=".md,.markdown,.txt,.html,.htm,.zip">

                            <div class="md-import-dropzone-inner">
                                <div class="md-import-dropzone-header">
                                    <div class="md-import-dropzone-icon">@icon('file')</div>
                                    <div>
                                        <p class="md-import-dropzone-title">{{ trans('bookstack-markdown-importer::messages.dropzone_title') }}</p>
                                        <p class="md-import-dropzone-subtitle">{{ trans('bookstack-markdown-importer::messages.dropzone_subtitle') }}</p>
                                    </div>
                                </div>

                                <div class="md-import-dropzone-actions">
                                    <button type="button" class="button outline" data-dropzone-browse>{{ trans('bookstack-markdown-importer::messages.dropzone_browse') }}</button>
                                    <span class="md-import-file-pill" data-dropzone-filename>
                                        <strong>{{ trans('bookstack-markdown-importer::messages.dropzone_no_files') }}</strong>
                                    </span>
                                </div>

                                <p class="md-import-hint">{{ trans('bookstack-markdown-importer::messages.hint_max_size', ['size' => (int) config('bookstack-markdown-importer.max_upload_mb', 20)]) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        @include('form.custom-checkbox', [
                            'name' => 'create_chapters',
                            'value' => 'true',
                            'checked' => old('create_chapters', $createChaptersDefault),
                            'label' => trans('bookstack-markdown-importer::messages.checkbox_label')
                        ])
                        <p class="text-muted small">{{ trans('bookstack-markdown-importer::messages.checkbox_help') }}</p>
                    </div>

                    <div class="form-group md-import-actions">
                        <button type="submit" class="button">{{ trans('bookstack-markdown-importer::messages.action_import') }}</button>
                        <a href="{{ $book->getUrl() }}" class="button outline">{{ trans('bookstack-markdown-importer::messages.action_cancel') }}</a>
                    </div>
                </form>

                <aside class="md-import-panel">
                    <h4>{{ trans('bookstack-markdown-importer::messages.sidebar_title') }}</h4>
                    <div class="md-import-meta">
                        <div><strong>{{ trans('bookstack-markdown-importer::messages.sidebar_item_title') }}</strong></div>
                        <div><strong>{{ trans('bookstack-markdown-importer::messages.sidebar_item_order') }}</strong></div>
                        <div><strong>{{ trans('bookstack-markdown-importer::messages.sidebar_item_chapters') }}</strong></div>
                        <div><strong>{{ trans('bookstack-markdown-importer::messages.sidebar_item_collisions') }}</strong></div>
                    </div>
                </aside>
            </div>
        </main>
    </div>
@stop

@push('body-end')
    <script>
        (function () {
            var dropzone = document.querySelector('[data-dropzone]');
            if (!dropzone) {
                return;
            }

            var messages = {
                noFiles: @json(trans('bookstack-markdown-importer::messages.dropzone_no_files')),
                moreFiles: @json(trans('bookstack-markdown-importer::messages.dropzone_more_files')),
                sizeUnit: @json(trans('bookstack-markdown-importer::messages.size_unit_kb'))
            };

            var fileInput = dropzone.querySelector('input[type="file"]');
            var browseButton = dropzone.querySelector('[data-dropzone-browse]');
            var filenameLabel = dropzone.querySelector('[data-dropzone-filename] strong');

            var setFilename = function (files) {
                if (!filenameLabel) {
                    return;
                }
                if (files && files.length) {
                    var list = Array.prototype.slice.call(files);
                    var totalSize = list.reduce(function (sum, item) {
                        return sum + (item.size || 0);
                    }, 0);
                    var sizeKb = Math.max(1, Math.round(totalSize / 1024));
                    if (list.length === 1) {
                        filenameLabel.textContent = list[0].name + ' (' + sizeKb + ' ' + messages.sizeUnit + ')';
                    } else {
                        filenameLabel.textContent = messages.moreFiles
                            .replace(':name', list[0].name)
                            .replace(':count', String(list.length - 1))
                            .replace(':size', String(sizeKb))
                            .replace(':unit', messages.sizeUnit);
                    }
                    dropzone.classList.add('is-ready');
                } else {
                    filenameLabel.textContent = messages.noFiles;
                    dropzone.classList.remove('is-ready');
                }
            };

            var setFiles = function (files) {
                if (!files || files.length === 0) {
                    return;
                }

                try {
                    var dt = new DataTransfer();
                    Array.prototype.slice.call(files).forEach(function (item) {
                        dt.items.add(item);
                    });
                    fileInput.files = dt.files;
                } catch (error) {
                    // Some browsers block programmatic file assignment.
                }
                setFilename(files);
                dropzone.classList.add('is-drop');
                setTimeout(function () {
                    dropzone.classList.remove('is-drop');
                }, 460);
            };

            browseButton.addEventListener('click', function () {
                fileInput.click();
            });

            fileInput.addEventListener('change', function (event) {
                var files = event.target.files;
                setFiles(files);
            });

            ['dragenter', 'dragover'].forEach(function (eventName) {
                dropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    dropzone.classList.add('is-dragover');
                });
            });

            ['dragleave', 'dragend', 'drop'].forEach(function (eventName) {
                dropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    dropzone.classList.remove('is-dragover');
                });
            });

            dropzone.addEventListener('drop', function (event) {
                var files = event.dataTransfer ? event.dataTransfer.files : null;
                setFiles(files);
            });
        })();
    </script>
@endpush
