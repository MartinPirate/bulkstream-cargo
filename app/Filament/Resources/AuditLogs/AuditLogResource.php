<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'audit entry';

    public static function canViewAny(): bool
    {
        return auth()->user()?->canApprove() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer'))
            ->defaultSort('created_at', 'desc')
            ->poll('15s')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y H:i:s')->sortable(),
                TextColumn::make('log_name')->label('Area')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'movements' => 'info',
                        'approvals', 'corrections' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('description')->label('Event'),
                TextColumn::make('subject')
                    ->label('Record')
                    ->state(fn (AuditEntry $record) => $record->subject_type
                        ? class_basename($record->subject_type).' #'.$record->subject_id
                        : '—'),
                TextColumn::make('causer.name')->label('By')->placeholder('System'),
                TextColumn::make('details')
                    ->label('Details')
                    ->state(fn (AuditEntry $record) => self::summarise($record))
                    ->wrap()
                    ->limit(120),
            ])
            ->filters([
                SelectFilter::make('log_name')->label('Area')->options([
                    'movements' => 'Movements',
                    'approvals' => 'Approvals',
                    'corrections' => 'Stock corrections',
                    'settings' => 'Settings',
                    'setup' => 'Tanks & vessels',
                ]),
            ]);
    }

    private static function summarise(AuditEntry $record): string
    {
        $props = $record->properties?->toArray() ?? [];
        $flat = $props['attributes'] ?? $props;

        return collect($flat)
            ->except(['updated_at', 'created_at'])
            ->map(fn ($v, $k) => $k.': '.(is_scalar($v) || $v === null ? var_export($v, true) : json_encode($v)))
            ->implode(' · ');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
