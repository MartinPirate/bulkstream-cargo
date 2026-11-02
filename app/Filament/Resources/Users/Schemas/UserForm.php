<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('roles')
                            ->label('Role')
                            ->relationship('roles', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => UserRole::tryFrom($record->name)?->getLabel() ?? $record->name)
                            ->preload()
                            ->required()
                            ->helperText('Operator: records movements. Supervisor: approves. Admin: manages tanks, users and settings.'),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->rule('min:12')
                            // Only required on create; blank on edit keeps the current password.
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep the current password.' : 'At least 12 characters.'),
                    ]),
            ]);
    }
}
