<?php

namespace App\Filament\Resources\StockCorrections\Schemas;

use App\Filament\Support\RichOptions;
use App\Models\Tank;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use MartinKiragu\TankTerminal\Domain\Quantity;

/**
 * Reactive maker form for a stock correction. The preview mirrors the guards
 * StockCorrectionService applies on approval; it never writes anything.
 */
class StockCorrectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Request a stock correction')
                    ->description('Use when a tank dip or ullage survey disagrees with the book balance. A supervisor must approve before the balance changes.')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        Select::make('tank_id')
                            ->label('Tank')
                            ->options(fn (): array => RichOptions::tanks(Tank::query()->orderBy('code')->get()))
                            ->native(false)
                            ->allowHtml()
                            ->searchable()
                            ->placeholder('Search by tank code or commodity')
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                        ToggleButtons::make('direction')
                            ->label('Adjustment')
                            ->options(['increase' => 'Increase stock', 'decrease' => 'Decrease stock'])
                            ->icons(['increase' => 'heroicon-m-arrow-trending-up', 'decrease' => 'heroicon-m-arrow-trending-down'])
                            ->colors(['increase' => 'success', 'decrease' => 'danger'])
                            ->grouped()
                            ->required()
                            ->live()
                            ->extraAttributes(['class' => 'bs-segmented']),
                        TextInput::make('quantity_kg')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(0.001)
                            ->maxValue(200_000)
                            ->step(0.001)
                            ->suffix('MT')
                            ->placeholder('0.000')
                            ->required()
                            ->live(onBlur: true)
                            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 1000)),
                        Textarea::make('reason')
                            ->label('Reason and survey reference')
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->rows(3)
                            ->placeholder('e.g. Dip survey 02 Nov 07:00 (surveyor ref. DS-4411) reads 12.4 MT below book.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Effect on book stock')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('preview')
                            ->hiddenLabel()
                            ->state(fn (Get $get) => self::preview($get))
                            ->html(),
                    ]),
            ]);
    }

    private static function preview(Get $get): HtmlString
    {
        $tank = filled($get('tank_id')) ? Tank::query()->find($get('tank_id')) : null;
        $tonnes = (float) $get('quantity_kg');
        $direction = $get('direction');

        if ($tank === null || $tonnes <= 0 || ! in_array($direction, ['increase', 'decrease'], true)) {
            return new HtmlString('<p class="bs-preview__empty">Pick a tank, an adjustment and a quantity to see the new book balance.</p>');
        }

        $qty = Quantity::tonnes($tonnes);
        $increase = $direction === 'increase';
        $blocked = $increase ? $qty->isGreaterThan($tank->freeCapacity()) : $qty->isGreaterThan($tank->balance());
        $after = $blocked ? null : ($increase ? $tank->balance()->plus($qty) : $tank->balance()->minus($qty));
        $afterPct = $after === null || $tank->capacity_kg === 0 ? null : min(100, round($after->kilograms / $tank->capacity_kg * 100, 1));

        $html = '<div class="bs-preview">'
            .'<div class="bs-preview__flow">'
            .'<div><span class="bs-preview__k">Book now</span><span class="bs-preview__v">'.e($tank->balance()->format()).'</span></div>'
            .'<span class="bs-preview__arrow bs-preview__arrow--'.($increase ? 'up' : 'down').'">'.($increase ? '+' : '−').' '.e($qty->format()).'</span>'
            .'<div><span class="bs-preview__k">After approval</span><span class="bs-preview__v">'.($after ? e($after->format()) : '—').'</span></div>'
            .'</div>';

        if ($afterPct !== null) {
            $html .= '<span class="bs-meter bs-meter--'.($afterPct >= 90 ? 'high' : ($afterPct <= 10 ? 'low' : 'ok')).'"><span style="width:'.e((string) $afterPct).'%"></span></span>'
                .'<p class="bs-preview__note">'.e(number_format($afterPct, 1)).'% of '.e($tank->capacity()->format()).' capacity</p>';
        }

        $html .= $blocked
            ? '<p class="bs-preview__status bs-preview__status--bad">'.($increase
                ? 'Exceeds free capacity ('.e($tank->freeCapacity()->format()).'). It will fail on approval.'
                : 'More than the tank holds. It will fail on approval.').'</p>'
            : '<p class="bs-preview__status bs-preview__status--ok">Within limits. Goes to a supervisor for approval.</p>';

        return new HtmlString($html.'</div>');
    }
}
