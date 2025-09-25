<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'date',
        'duration',
        'is_recurring',
        'description'
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
        'duration' => 'integer'
    ];

    /**
     * Get holidays in a specific date range
     * Always returns an Illuminate\Database\Eloquent\Collection
     */
    public static function getHolidaysInRange($startDate, $endDate): \Illuminate\Database\Eloquent\Collection
    {
        try {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            Log::info("[Fiche de paie] getHolidaysInRange appelé avec start={$start->format('Y-m-d')}, end={$end->format('Y-m-d')}");

            $holidays = self::where(function ($query) use ($start, $end) {
                $query->where('is_recurring', false)
                    ->where(function ($q) use ($start, $end) {
                        $q->where('date', '<=', $end->format('Y-m-d'))
                            ->whereRaw('DATE_ADD(date, INTERVAL (duration - 1) DAY) >= ?', [$start->format('Y-m-d')]);
                    });
            })
            ->orWhere(function ($query) use ($start, $end) {
                $query->where('is_recurring', true);
            })
            ->get();

            Log::info("[Fiche de paie] getHolidaysInRange retourne " . count($holidays) . " résultats, type=" . get_class($holidays));
            
            return $holidays ?: new \Illuminate\Database\Eloquent\Collection();
        } catch (\Exception $e) {
            Log::error("[Fiche de paie] Erreur dans getHolidaysInRange : " . $e->getMessage());
            return new \Illuminate\Database\Eloquent\Collection();
        }
    }

    /**
     * Get holiday dates for non-recurring holidays
     */
    public function getHolidayDates(): array
    {
        if ($this->is_recurring) {
            return [];
        }

        $dates = [];
        $startDate = Carbon::parse($this->date);
        
        for ($i = 0; $i < $this->duration; $i++) {
            $dates[] = $startDate->copy()->addDays($i)->format('Y-m-d');
        }

        return $dates;
    }

    /**
     * Get recurring holiday dates for a specific year
     */
    public function getRecurringHolidayDatesForYear(int $year): array
    {
        if (!$this->is_recurring) {
            return [];
        }

        $dates = [];
        $baseDate = Carbon::parse($this->date);
        $holidayDate = Carbon::create($year, $baseDate->month, $baseDate->day);
        
        for ($i = 0; $i < $this->duration; $i++) {
            $dates[] = $holidayDate->copy()->addDays($i)->format('Y-m-d');
        }

        return $dates;
    }
}
