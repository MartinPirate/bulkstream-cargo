<?php

namespace App\Filament\Pages;

use App\Settings\TerminalSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Operator-tunable terminal parameters. Admin only; every change is audited.
 */
class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Terminal settings';

    protected static ?string $navigationLabel = 'Settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(TerminalSettings $settings): void
    {
        $values = $settings->all();
        $this->form->fill([
            'transit_loss_percent' => $values['transit_loss_basis_points'] / 100,
            'release_approval_threshold_mt' => $values['release_approval_threshold_kg'] / 1000,
            'high_level_alarm_percent' => $values['high_level_alarm_percent'],
            'low_level_alarm_percent' => $values['low_level_alarm_percent'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Pipeline rules')
                    ->columns(2)
                    ->schema([
                        TextInput::make('transit_loss_percent')
                            ->label('Standard transit loss on vessel discharge')
                            ->numeric()->step(0.01)->minValue(0)->maxValue(5)->suffix('%')->required()
                            ->helperText('Applied to every inbound vessel flow. Contract standard is 0.50%.'),
                        TextInput::make('release_approval_threshold_mt')
                            ->label('Supervisor approval for releases from')
                            ->numeric()->step(0.001)->minValue(1)->maxValue(100000)->suffix('MT')->required()
                            ->helperText('Truck or rail releases at or above this need a second person.'),
                    ]),
                Section::make('Dashboard alarms')
                    ->columns(2)
                    ->schema([
                        TextInput::make('high_level_alarm_percent')
                            ->label('High-level alarm')
                            ->numeric()->integer()->minValue(50)->maxValue(100)->suffix('%')->required(),
                        TextInput::make('low_level_alarm_percent')
                            ->label('Low-level alarm')
                            ->numeric()->integer()->minValue(0)->maxValue(50)->suffix('%')->required(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save settings')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(TerminalSettings $settings): void
    {
        $state = $this->form->getState();

        $settings->update([
            'transit_loss_basis_points' => (int) round(((float) $state['transit_loss_percent']) * 100),
            'release_approval_threshold_kg' => (int) round(((float) $state['release_approval_threshold_mt']) * 1000),
            'high_level_alarm_percent' => (int) $state['high_level_alarm_percent'],
            'low_level_alarm_percent' => (int) $state['low_level_alarm_percent'],
        ], auth()->id());

        Notification::make()->title('Settings saved')->body('They apply to the next pipeline run.')->success()->send();
    }
}
