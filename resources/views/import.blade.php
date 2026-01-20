@extends('layouts.simple')

@section('body')
    <div class="container small">
        <div class="my-s">
            @include('entities.breadcrumbs', ['crumbs' => [
                $book,
                $book->getUrl('/markdown-import') => [
                    'text' => 'Import Markdown',
                    'icon' => 'file'
                ]
            ]])
        </div>

        <main class="content-wrap card">
            <h1 class="list-heading">Import Markdown</h1>
            <p class="text-muted">Upload a Markdown file or a ZIP of Markdown files to create pages in this book.</p>

            @if ($errors->any())
                <div class="text-neg mb-m">
                    <p class="mb-xs">Please fix the errors below:</p>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ $book->getUrl('/markdown-import') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label for="markdown-file">Markdown or ZIP file</label>
                    <input id="markdown-file"
                           type="file"
                           name="file"
                           required
                           accept=".md,.markdown,.zip">
                    <p class="text-muted small">Max upload size: {{ (int) config('bookstack-markdown-importer.max_upload_mb', 20) }} MB.</p>
                </div>

                <div class="form-group">
                    @include('form.custom-checkbox', [
                        'name' => 'create_chapters',
                        'value' => 'true',
                        'checked' => old('create_chapters', $createChaptersDefault),
                        'label' => 'Create chapters from folders'
                    ])
                    <p class="text-muted small">Top-level ZIP folders will become chapters when enabled.</p>
                </div>

                <div class="form-group">
                    <button type="submit" class="button">Import</button>
                    <a href="{{ $book->getUrl() }}" class="button outline">Cancel</a>
                </div>
            </form>
        </main>
    </div>
@stop
