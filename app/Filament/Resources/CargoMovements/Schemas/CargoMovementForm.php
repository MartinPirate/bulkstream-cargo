<?php

namespace App\Filament\Resources\CargoMovements\Schemas;

use App\Enums\VesselStatus;
use App\Filament\Support\RichOptions;
use App\Models\Tank;
use App\Models\Vessel;
use App\Settings\TerminalSettings;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Domain\TransitLossPolicy;
use MartinKiragu\TankTerminal\Enums\MovementDirection;

/**
 * Reactive entry form: as the operator picks direction, tank and quantity, the
 * preview recomputes transit loss, net quantity, ullage and approval routing
 * before anything is saved. The authoritative checks still run in the engine.
 */
class CargoMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Movement')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('direction')
                            ->options([
                                MovementDirection::Inbound->value => 'Vessel discharge',
                                MovementDirection::Outbound->value => 'Truck loading',
                            ])
                            ->icons([
                                MovementDirection::Inbound->value => 'heroicon-o-arrow-down-tray',
                                MovementDirection::Outbound->value => 'heroicon-o-truck',
                            ])
                            ->default(MovementDirection::Inbound->value)
                            ->inline()
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                        Select::make('vessel_id')
                            ->label('Vessel')
                            ->options(fn () => Vessel::query()
                                ->whereIn('status', [VesselStatus::Berthed, VesselStatus::Discharging])
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Vessel $v) => [$v->id => "{$v->name} ({$v->voyage_number})"]))
                            ->helperText('Only berthed or discharging vessels are listed.')
                            ->searchable()
                            ->visible(fn (Get $get) => $get('direction') === MovementDirection::Inbound->value)
                            ->required(fn (Get $get) => $get('direction') === MovementDirection::Inbound->value),
                        TextInput::make('truck_registration')
                            ->label('Truck registration')
                            ->placeholder('KDA 123X')
                            ->maxLength(20)
                            ->visible(fn (Get $get) => $get('direction') === MovementDirection::Outbound->value)
                            ->required(fn (Get $get) => $get('direction') === MovementDirection::Outbound->value),
                        Select::make('tank_id')
                            ->label('Shore tank')
                            ->options(fn (): array => RichOptions::tanks(Tank::query()->active()->orderBy('code')->get()))
                            ->native(false)
                            ->allowHtml()
                            ->placeholder('Search by tank code or commodity')
                            ->searchable()
                            ->required()
                            ->live(),
                        TextInput::make('gross_kg')
                            ->label(fn (Get $get) => $get('direction') === MovementDirection::Inbound->value
                                ? 'Gross quantity off vessel'
                                : 'Quantity loaded')
                            ->numeric()
                            ->minValue(0.001)
                            ->maxValue(200_000)
                            ->step(0.001)
                            ->suffix('MT')
                            ->required()
                            ->live(onBlur: true)
                            ->formatStateUsing(fn ($state) => $state !== null ? $state / 1000 : null)
                            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 1000)),
                        DateTimePicker::make('occurred_at')
                            ->label('Event time (source log)')
                            ->seconds()
                            ->default(now())
                            ->maxDate(now()->addMinutes(5))
                            ->required()
                            ->helperText('When it happened, not when it was keyed in. Late entries are re-sequenced.'),
                        TextInput::make('reference')
                            ->label('Source reference')
                            ->placeholder('Meter ticket / weighbridge no. (auto if blank)')
                            ->maxLength(64)
                            ->unique(ignoreRecord: true),
                    ]),
                Section::make('Preview')
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
        $grossTonnes = (float) $get('gross_kg');

        if ($tank === null || $grossTonnes <= 0) {
            return new HtmlString('<span class="text-gray-500">Pick a tank and quantity to see the effect.</span>');
        }

        $settings = app(TerminalSettings::class);
        $gross = Quantity::tonnes($grossTonnes);
        $inbound = $get('direction') === MovementDirection::Inbound->value;

        // Inline styles: Filament's compiled CSS does not ship arbitrary utility classes.
        $row = fn (string $k, string $v) => "<div style=\"display:flex;justify-content:space-between;gap:1rem;padding:2px 0\"><dt style=\"opacity:.65\">{$k}</dt><dd style=\"font-weight:600;text-align:right\">{$v}</dd></div>";
        $lines = ['<dl style="font-size:.875rem">'];
        $lines[] = $row('Tank', e($tank->code).' · '.e($tank->commodity->shortLabel()));
        $lines[] = $row('Current stock', $tank->balance()->format());

        if ($inbound) {
            $policy = new TransitLossPolicy($settings->transitLossBasisPoints());
            $net = $policy->netOf($gross);
            $fits = ! $net->isGreaterThan($tank->freeCapacity());
            $lines[] = $row('Gross', $gross->format());
            $lines[] = $row('Transit loss ('.$policy->percentage().'%)', '− '.$policy->lossFor($gross)->format());
            $lines[] = $row('Net into tank', $net->format());
            $lines[] = $row('Free after', $fits ? $tank->freeCapacity()->minus($net)->format() : '—');
            $lines[] = $fits
                ? '<p class="mt-2" style="color:#16a34a">Fits within capacity.</p>'
                : '<p class="mt-2 font-semibold" style="color:#dc2626">Would overflow: only '.$tank->freeCapacity()->format().' free. The engine will reject it.</p>';
        } else {
            $enough = ! $gross->isGreaterThan($tank->balance());
            $lines[] = $row('Release', $gross->format());
            $lines[] = $row('Stock after', $enough ? $tank->balance()->minus($gross)->format() : '—');
            $lines[] = $enough
                ? '<p class="mt-2" style="color:#16a34a">Sufficient stock.</p>'
                : '<p class="mt-2 font-semibold" style="color:#dc2626">Insufficient stock. The engine will reject it.</p>';

            if ($gross->kilograms >= $settings->releaseApprovalThresholdKg()) {
                $lines[] = '<p class="mt-1" style="color:#2563eb">At or above '.Quantity::kg($settings->releaseApprovalThresholdKg())->format().': needs supervisor approval.</p>';
            }
        }

        $lines[] = '</dl>';

        return new HtmlString(implode('', $lines));
    }
}
