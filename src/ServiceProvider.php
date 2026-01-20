<?php

namespace BookStackMarkdownImporter;

use BookStack\Entities\Controllers\BookController;
use BookStack\Entities\Queries\BookQueries;
use BookStack\Facades\Theme;
use BookStack\Permissions\Permission;
use BookStack\Theming\ThemeEvents;
use BookStackMarkdownImporter\Support\HtmlActionInjector;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bookstack-markdown-importer.php', 'bookstack-markdown-importer');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'bookstack-markdown-importer');

        $this->publishes([
            __DIR__ . '/../config/bookstack-markdown-importer.php' => config_path('bookstack-markdown-importer.php'),
        ], 'bookstack-markdown-importer-config');

        $this->registerRoutes();
        $this->registerActionButton();
    }

    protected function registerRoutes(): void
    {
        Theme::listen(ThemeEvents::ROUTES_REGISTER_WEB_AUTH, function (Router $router) {
            $router->group([], function () {
                require __DIR__ . '/../routes/web.php';
            });
        });
    }

    protected function registerActionButton(): void
    {
        Theme::listen(ThemeEvents::WEB_MIDDLEWARE_AFTER, function (Request $request, $response) {
            if (!$response instanceof Response) {
                return null;
            }

            $contentType = $response->headers->get('Content-Type', '');
            if (!str_contains($contentType, 'text/html')) {
                return null;
            }

            $route = $request->route();
            if (!$route || $route->getActionName() !== BookController::class . '@show') {
                return null;
            }

            $slug = $route->parameter('slug');
            if (!$slug) {
                return null;
            }

            try {
                $book = app(BookQueries::class)->findVisibleBySlugOrFail($slug);
            } catch (Throwable) {
                return null;
            }

            if (!userCan(Permission::BookUpdate, $book)) {
                return null;
            }

            $actionHtml = view('bookstack-markdown-importer::parts.book-action', [
                'book' => $book,
            ])->render();

            $injector = app(HtmlActionInjector::class);
            $updatedHtml = $injector->injectBookAction($response->getContent(), $actionHtml);
            if ($updatedHtml === null) {
                return null;
            }

            $response->setContent($updatedHtml);
            return $response;
        });
    }
}
