<?php

namespace App\Filament\Admin\Resources\PushSubscriptions;

use App\Filament\Admin\Resources\PushSubscriptions\Pages\ListPushSubscriptions;
use App\Models\PushSubscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

use function __;

class PushSubscriptionResource extends Resource
{
    protected static ?string $model = PushSubscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BellAlert;

    public static function getModelLabel(): string
    {
        return __('resources/push_subscription.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/push_subscription.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources/push_subscription.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.settings');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Cache::rememberForever(
            'push_subscriptions_count',
            fn () => PushSubscription::query()->count(),
        );
    }

    public static function canCreate(): bool
    {
        // Push subscriptions are created by the browser handshake, never by
        // an admin. Hide the create button so the only verb in this resource
        // is "delete" (via row action) + "broadcast" (via header action).
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('endpoint_host')
                    ->label(__('resources/push_subscription.fields.endpoint_host'))
                    ->getStateUsing(fn (PushSubscription $row) => parse_url($row->endpoint, PHP_URL_HOST) ?: '—')
                    ->badge(),

                TextColumn::make('locale')
                    ->label(__('resources/push_subscription.fields.locale'))
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('user.name')
                    ->label(__('resources/push_subscription.fields.user'))
                    ->placeholder(__('resources/push_subscription.fields.anonymous'))
                    ->searchable(),

                TextColumn::make('user_agent')
                    ->label(__('resources/push_subscription.fields.user_agent'))
                    ->limit(40)
                    ->tooltip(fn (PushSubscription $row) => $row->user_agent)
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label(__('resources/push_subscription.fields.created_at'))
                    ->dateTime()
                    ->since(),

                TextColumn::make('last_used_at')
                    ->label(__('resources/push_subscription.fields.last_used_at'))
                    ->dateTime()
                    ->since()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPushSubscriptions::route('/'),
        ];
    }
}
