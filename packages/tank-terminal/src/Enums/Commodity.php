<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Enums;

/**
 * Bulk liquid palm products handled at the terminal.
 */
enum Commodity: string
{
    case CPO = 'cpo';
    case CPOL = 'cpol';
    case PFAD = 'pfad';

    public function label(): string
    {
        return match ($this) {
            self::CPO => 'Crude Palm Oil (CPO)',
            self::CPOL => 'Crude Palm Olein (CPOL)',
            self::PFAD => 'Palm Fatty Acid Distillate (PFAD)',
        };
    }

    public function shortLabel(): string
    {
        return strtoupper($this->value);
    }

    public function handlingNote(): string
    {
        return match ($this) {
            self::CPO => 'Unrefined and highly viscous. Requires constant temperature control.',
            self::CPOL => 'Liquid fraction after fractionation. Higher fluidity; cooking-oil feedstock.',
            self::PFAD => 'Refining byproduct. Viscous; used in soap-making and animal feed.',
        };
    }

    /**
     * Products that must stay heated in tank to remain pumpable.
     */
    public function requiresHeating(): bool
    {
        return match ($this) {
            self::CPO, self::PFAD => true,
            self::CPOL => false,
        };
    }

    /**
     * Recommended storage temperature band in °C.
     *
     * @return array{0: int, 1: int}
     */
    public function storageTemperatureRange(): array
    {
        return match ($this) {
            self::CPO => [50, 55],
            self::CPOL => [25, 35],
            self::PFAD => [60, 70],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
