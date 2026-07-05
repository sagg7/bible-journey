<?php

namespace App\Filament\Resources\Institutions\Tables;

use App\Models\Institution;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
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
                    ->schema([
                        Radio::make('billing_period')
                            ->label('Periodicidad')
                            ->options([
                                'monthly' => 'Mensual',
                                'annual'  => 'Anual (2 meses gratis)',
                            ])
                            ->default('monthly')
                            ->required(),
                    ])
                    ->action(function (Institution $record, array $data) {
                        $minSeats = (int) config('services.stripe_institution_min_seats');

                        if ($record->seats < $minSeats) {
                            Notification::make()
                                ->title("Mínimo {$minSeats} asientos")
                                ->body("Esta institución tiene {$record->seats} asiento(s) configurado(s); el mínimo es {$minSeats}.")
                                ->danger()
                                ->send();

                            return;
                        }

                        $priceId = $data['billing_period'] === 'annual'
                            ? config('services.stripe_institution_price_id_annual')
                            : config('services.stripe_institution_price_id_monthly');

                        if (! $priceId) {
                            Notification::make()
                                ->title('Falta configurar el Price ID de Stripe en el .env')
                                ->danger()
                                ->send();

                            return;
                        }

                        $checkout = $record->newSubscription('default', $priceId)
                            ->quantity($record->seats)
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
