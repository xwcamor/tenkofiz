<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;

class HolidayTemplate extends Model
{
    use BelongsToCompany, HasHashid;

    protected $fillable = ['company_id', 'country', 'month', 'day', 'easter_offset', 'name'];

    protected $casts = [
        'month' => 'integer',
        'day' => 'integer',
        'easter_offset' => 'integer',
    ];

    /** Supported countries (extend as the product grows) */
    public const COUNTRIES = [
        'PE' => 'Perú',
        'CL' => 'Chile',
    ];

    /** The concrete date this rule falls on in the given year (Y-m-d), or null if invalid */
    public function dateForYear(int $year): ?string
    {
        if ($this->easter_offset !== null) {
            return static::easterSunday($year)->copy()->addDays($this->easter_offset)->toDateString();
        }

        if ($this->month && $this->day) {
            try {
                return \Carbon\Carbon::create($year, $this->month, $this->day)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /** Human label for a rule ("06-07" or "Easter -2") */
    public function ruleLabel(): string
    {
        if ($this->easter_offset !== null) {
            return __('Easter').' '.($this->easter_offset > 0 ? '+' : '').$this->easter_offset;
        }

        return sprintf('%02d/%02d', $this->day, $this->month);
    }

    /** Easter Sunday of a year as a Carbon date */
    public static function easterSunday(int $year): \Carbon\Carbon
    {
        return function_exists('easter_date')
            ? \Carbon\Carbon::createFromTimestamp(easter_date($year))->startOfDay()
            : \Carbon\Carbon::create($year, 3, 21)->addDays(easter_days($year));
    }

    /** Built-in presets used to seed a country (and to reset it) */
    public static function presets(string $country): array
    {
        return match ($country) {
            'PE' => [
                [1, 1, null, 'Año Nuevo'],
                [null, null, -3, 'Jueves Santo'],
                [null, null, -2, 'Viernes Santo'],
                [5, 1, null, 'Día del Trabajo'],
                [6, 7, null, 'Batalla de Arica y Día de la Bandera'],
                [6, 29, null, 'San Pedro y San Pablo'],
                [7, 23, null, 'Día de la Fuerza Aérea del Perú'],
                [7, 28, null, 'Fiestas Patrias'],
                [7, 29, null, 'Fiestas Patrias'],
                [8, 6, null, 'Batalla de Junín'],
                [8, 30, null, 'Santa Rosa de Lima'],
                [10, 8, null, 'Combate de Angamos'],
                [11, 1, null, 'Todos los Santos'],
                [12, 8, null, 'Inmaculada Concepción'],
                [12, 9, null, 'Batalla de Ayacucho'],
                [12, 25, null, 'Navidad'],
            ],
            'CL' => [
                [1, 1, null, 'Año Nuevo'],
                [null, null, -2, 'Viernes Santo'],
                [null, null, -1, 'Sábado Santo'],
                [5, 1, null, 'Día del Trabajo'],
                [5, 21, null, 'Día de las Glorias Navales'],
                [6, 29, null, 'San Pedro y San Pablo'],
                [7, 16, null, 'Virgen del Carmen'],
                [8, 15, null, 'Asunción de la Virgen'],
                [9, 18, null, 'Independencia Nacional'],
                [9, 19, null, 'Día de las Glorias del Ejército'],
                [10, 12, null, 'Encuentro de Dos Mundos'],
                [10, 31, null, 'Día de las Iglesias Evangélicas'],
                [11, 1, null, 'Día de Todos los Santos'],
                [12, 8, null, 'Inmaculada Concepción'],
                [12, 25, null, 'Navidad'],
            ],
            default => [],
        };
    }
}
