<?php

namespace App\Filament\Resources\Institutions\Tables;

use App\Models\Institution;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InstitutionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('seats')->label('Asientos'),
                TextColumn::make('members_count')->label('Miembros')->counts('members'),
                IconColumn::make('is_subscribed')
                    ->label('Suscrita')
                    ->boolean()
                    ->getStateUsing(fn (Institution $record) => $record->subscribed('default')),
            ])
            ->recordActions([
                Action::make('generatePaymentLink')
                    ->label('Generar link de pago')
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->action(function (Institution $record) {
                        $priceId = config('services.stripe_institution_price_id');

                        if (! $priceId) {
                            Notification::make()
                                ->title('Falta STRIPE_INSTITUTION_PRICE_ID en el .env')
                                ->danger()
                                ->send();

                            return;
                        }

                        $checkout = $record->newSubscription('default', $priceId)
                            ->quantity($record->seats ?: 1)
                            ->checkout([
                                'success_url' => url('/admin'),
                                'cancel_url'  => url('/admin'),
                            ]);

                        Notification::make()
                            ->title('Link de pago generado')
                            ->body($checkout->url)
                            ->persistent()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
