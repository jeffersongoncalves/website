<?php

namespace App\Filament\Admin\Resources\PushSubscriptions\Pages;

use App\Filament\Admin\Resources\PushSubscriptions\PushSubscriptionResource;
use App\Models\PushSubscription;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

use function __;

class ListPushSubscriptions extends ListRecords
{
    protected static string $resource = PushSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('broadcast')
                ->label(__('resources/push_subscription.broadcast.label'))
                ->icon(Heroicon::PaperAirplane)
                ->color('primary')
                ->visible(fn (): bool => PushSubscription::query()->exists()
                    && is_string(config('services.webpush.public_key'))
                    && is_string(config('services.webpush.private_key')))
                ->schema([
                    TextInput::make('title')
                        ->label(__('resources/push_subscription.broadcast.title'))
                        ->required()
                        ->maxLength(120),
                    TextInput::make('body')
                        ->label(__('resources/push_subscription.broadcast.body'))
                        ->maxLength(240),
                    TextInput::make('url')
                        ->label(__('resources/push_subscription.broadcast.url'))
                        ->default('/')
                        ->placeholder('/')
                        ->maxLength(2048),
                ])
                ->action(function (array $data): void {
                    // Reuse the artisan command so the CLI and the admin
                    // share the same delivery path + expired-pruning logic.
                    $exitCode = Artisan::call('push:send', [
                        '--title' => $data['title'],
                        '--body' => $data['body'] ?? '',
                        '--url' => $data['url'] ?? '/',
                    ]);

                    $output = trim((string) Artisan::output());

                    Notification::make()
                        ->title($exitCode === 0
                            ? __('resources/push_subscription.broadcast.success')
                            : __('resources/push_subscription.broadcast.failure'))
                        ->body($output)
                        ->{$exitCode === 0 ? 'success' : 'danger'}()
                        ->send();
                }),
        ];
    }
}
