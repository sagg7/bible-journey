<?php

namespace App\Filament\Resources\InstitutionMembers\Tables;

use App\Models\Institution;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class InstitutionMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('email')->label('Correo')->searchable(),
                TextColumn::make('institution.name')->label('Institución'),
                TextColumn::make('created_at')->label('Desde')->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label('Quitar de la institución')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->update(['institution_id' => null])),
            ])
            ->headerActions([
                Action::make('addMember')
                    ->label('Agregar miembro')
                    ->schema(fn () => array_filter([
                        auth()->user()?->is_admin
                            ? Select::make('institution_id')
                                ->label('Institución')
                                ->options(Institution::pluck('name', 'id'))
                                ->required()
                            : null,
                        TextInput::make('email')->label('Correo')->email()->required(),
                    ]))
                    ->action(function (array $data) {
                        $institutionId = auth()->user()->is_admin
                            ? $data['institution_id']
                            : auth()->user()->institution_id;

                        $institution = Institution::find($institutionId);
                        if (! $institution) {
                            Notification::make()->title('Institución no encontrada')->danger()->send();
                            return;
                        }

                        $currentCount = User::where('institution_id', $institutionId)->count();
                        if ($currentCount >= $institution->seats) {
                            Notification::make()
                                ->title('Límite de asientos alcanzado')
                                ->body("Esta institución tiene {$institution->seats} asiento(s) contratado(s).")
                                ->danger()
                                ->send();
                            return;
                        }

                        $existing = User::where('email', $data['email'])->first();
                        if ($existing) {
                            $existing->update(['institution_id' => $institutionId]);
                            Notification::make()->title('Miembro agregado')->success()->send();
                            return;
                        }

                        $password = Str::random(12);
                        User::create([
                            'name' => $data['email'],
                            'email' => $data['email'],
                            'password' => $password,
                            'institution_id' => $institutionId,
                        ]);

                        Notification::make()
                            ->title('Cuenta creada para nuevo miembro')
                            ->body("Correo: {$data['email']} — Contraseña temporal: {$password}")
                            ->persistent()
                            ->send();
                    }),
            ]);
    }
}
