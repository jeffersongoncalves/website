<?php

namespace App\Providers;

use App\Support\LocaleSupport;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications;
use Filament\Pages;
use Filament\Schemas;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\View\PanelsRenderHook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use RalphJSmit\Laravel\SEO\Facades\SEOManager;
use RalphJSmit\Laravel\SEO\Support\SEOData;

use function view;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (config('filakit.favicon.enabled')) {
            FilamentView::registerRenderHook(PanelsRenderHook::HEAD_START, fn (): View => view('components.favicon'));
        }
        FilamentView::registerRenderHook(PanelsRenderHook::HEAD_START, fn (): View => view('components.js-md5'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! app()->isLocal()) {
            URL::forceHttps();
            Vite::useAggressivePrefetching();
        }

        // Strip Livewire's data-navigate-track="reload" from Vite-injected
        // assets — no panel uses SPA navigate, and the attribute otherwise
        // forces a hard CSS/JS reload on every full-page navigation.
        //
        // `data-cfasync="false"` opts the bundle out of Cloudflare's
        // Rocket Loader. Rocket Loader defers + reorders script execution,
        // which breaks `navigator.serviceWorker.register()` timing and
        // makes `pushManager.subscribe()` fail with the generic
        // "AbortError: Registration failed — push service error".
        Vite::useStyleTagAttributes(['data-navigate-track' => false]);
        Vite::useScriptTagAttributes([
            'data-navigate-track' => false,
            'data-cfasync' => 'false',
        ]);

        Model::automaticallyEagerLoadRelationships();

        Paginator::defaultView('pagination.site');
        Paginator::defaultSimpleView('pagination.site-simple');

        $this->configureSeo();
        $this->configureRateLimiting();

        $this->configureActions();
        $this->configureSchema();
        $this->configureForms();
        $this->configureInfolists();
        $this->configurePages();
        $this->configureTables();
    }

    private function configureSeo(): void
    {
        SEOManager::SEODataTransformer(function (SEOData $data): SEOData {
            if (empty($data->image)) {
                $data->image = Vite::asset('resources/images/github-og-'.LocaleSupport::short().'.png');
            }

            return $data;
        });
    }

    /**
     * Cap GitHub-API job throughput so parallel Horizon workers can't trip
     * GitHub's *secondary* rate limit (~900 pts/min, separate from the 5000/h
     * primary). 60 jobs/min × ~3 calls ≈ 180 calls/min — wide margin. The
     * `RateLimited('github-api')` middleware on SyncProjectMetricsJob releases
     * over-limit jobs back to the queue instead of failing them.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('github-api', fn () => Limit::perMinute(60));
    }

    private function configureActions(): void
    {
        Actions\ActionGroup::configureUsing(function (Actions\ActionGroup $action) {
            return $action->icon(Heroicon::EllipsisVertical);
        });

        Actions\Action::configureUsing(function (Actions\Action $action) {
            return $action
                ->translateLabel()
                ->modalWidth(Width::Medium)
                ->closeModalByClickingAway(false);
        });

        Actions\CreateAction::configureUsing(function (Actions\CreateAction $action) {
            return $action
                ->icon(Heroicon::Plus)
                ->hiddenLabel()
                ->createAnother(false);
        });

        Actions\EditAction::configureUsing(function (Actions\EditAction $action) {
            return $action
                ->icon(Heroicon::PencilSquare)
                ->hiddenLabel()
                ->button();
        });

        Actions\DeleteAction::configureUsing(function (Actions\DeleteAction $action) {
            return $action
                ->icon(Heroicon::Trash)
                ->hiddenLabel()
                ->button();
        });

        Actions\ViewAction::configureUsing(function (Actions\ViewAction $action) {
            return $action
                ->icon(Heroicon::Eye)
                ->hiddenLabel()
                ->button();
        });
    }

    private function configureSchema(): void
    {
        Schemas\Schema::configureUsing(function (Schemas\Schema $schema) {
            return $schema
                ->defaultCurrency(config('filakit.defaultCurrency'))
                ->defaultDateDisplayFormat(config('filakit.defaultDateDisplayFormat'))
                ->defaultIsoDateDisplayFormat(config('filakit.defaultIsoDateDisplayFormat'))
                ->defaultDateTimeDisplayFormat(config('filakit.defaultDateTimeDisplayFormat'))
                ->defaultIsoDateTimeDisplayFormat(config('filakit.defaultIsoDateTimeDisplayFormat'))
                ->defaultNumberLocale(config('filakit.defaultNumberLocale'))
                ->defaultTimeDisplayFormat(config('filakit.defaultTimeDisplayFormat'))
                ->defaultIsoTimeDisplayFormat(config('filakit.defaultIsoTimeDisplayFormat'));
        });
    }

    private function configureForms(): void
    {
        Forms\Components\Field::configureUsing(function (Forms\Components\Field $field) {
            return $field->translateLabel();
        });

        Forms\Components\ToggleButtons::configureUsing(function (Forms\Components\ToggleButtons $component) {
            return $component
                ->inline()
                ->grouped();
        });

        Forms\Components\TextInput::configureUsing(function (Forms\Components\TextInput $component) {
            return $component->minValue(0);
        });

        Forms\Components\Select::configureUsing(function (Forms\Components\Select $component) {
            return $component
                ->native(false)
                ->selectablePlaceholder(function (Forms\Components\Select $component) {
                    return ! $component->isRequired();
                })
                ->searchable(function (Forms\Components\Select $component) {
                    return $component->hasRelationship();
                })
                ->preload(function (Forms\Components\Select $component) {
                    return $component->isSearchable();
                });
        });

        Forms\Components\DateTimePicker::configureUsing(function (Forms\Components\DateTimePicker $component) {
            return $component
                ->seconds(false)
                ->maxDate('9999-12-31T23:59');
        });

        Forms\Components\Repeater::configureUsing(function (Forms\Components\Repeater $component) {
            return $component->deleteAction(function (Actions\Action $action) {
                return $action->requiresConfirmation();
            });
        });

        Forms\Components\Builder::configureUsing(function (Forms\Components\Builder $component) {
            return $component->deleteAction(function (Actions\Action $action) {
                return $action->requiresConfirmation();
            });
        });

        Forms\Components\FileUpload::configureUsing(function (Forms\Components\FileUpload $component) {
            return $component->moveFiles();
        });

        Forms\Components\Textarea::configureUsing(function (Forms\Components\Textarea $component) {
            return $component->rows(4);
        });
    }

    private function configureInfolists(): void
    {
        Infolists\Components\Entry::configureUsing(function (Infolists\Components\Entry $entry) {
            return $entry->translateLabel();
        });
    }

    private function configurePages(): void
    {
        Pages\Page::$reportValidationErrorUsing = function (ValidationException $exception) {
            Notifications\Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();
        };

        Pages\Page::$formActionsAreSticky = false;
    }

    private function configureTables(): void
    {
        Tables\Table::configureUsing(function (Tables\Table $table) {
            return $table
                ->defaultCurrency(config('filakit.defaultCurrency'))
                ->defaultDateDisplayFormat(config('filakit.defaultDateDisplayFormat'))
                ->defaultIsoDateDisplayFormat(config('filakit.defaultIsoDateDisplayFormat'))
                ->defaultDateTimeDisplayFormat(config('filakit.defaultDateTimeDisplayFormat'))
                ->defaultIsoDateTimeDisplayFormat(config('filakit.defaultIsoDateTimeDisplayFormat'))
                ->defaultNumberLocale(config('filakit.defaultNumberLocale'))
                ->defaultTimeDisplayFormat(config('filakit.defaultTimeDisplayFormat'))
                ->defaultIsoTimeDisplayFormat(config('filakit.defaultIsoTimeDisplayFormat'));
        });
        Tables\Columns\Column::configureUsing(function (Tables\Columns\Column $column) {
            return $column->translateLabel();
        });

        Tables\Table::configureUsing(function (Tables\Table $table) {
            return $table
                ->filtersFormWidth('md')
                ->paginationPageOptions([5, 10, 25, 50]);
        });

        Tables\Columns\ImageColumn::configureUsing(function (Tables\Columns\ImageColumn $column) {
            return $column->extraImgAttributes(['loading' => 'lazy']);
        });

        Tables\Filters\SelectFilter::configureUsing(function (Tables\Filters\SelectFilter $filter) {
            return $filter->native(false);
        });
    }
}
