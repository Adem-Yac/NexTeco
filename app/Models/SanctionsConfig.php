<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SanctionsConfig extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'type', // 'retards' or 'absences'
        'threshold', // Number of occurrences to trigger sanction
        'hours_deducted', // Hours to deduct
    ];

    protected $casts = [
        'threshold' => 'integer',
        'hours_deducted' => 'float',
    ];

    /**
     * Get sanctions configuration organized by type
     */
    public static function getConfig(): array
    {
        $configs = self::orderBy('type')->orderBy('threshold')->get();
        
        $result = [];
        foreach ($configs as $config) {
            $result[$config->type][] = [
                'threshold' => $config->threshold,
                'hours_deducted' => $config->hours_deducted,
            ];
        }
        
        return $result;
    }

    /**
     * Get the applicable sanction for a given type and count
     */
    public static function getApplicableSanction(string $type, int $count): ?array
    {
        $sanctions = self::where('type', $type)
            ->where('threshold', '<=', $count)
            ->orderBy('threshold', 'desc')
            ->first();

        if (!$sanctions) {
            return null;
        }

        return [
            'threshold' => $sanctions->threshold,
            'hours_deducted' => $sanctions->hours_deducted,
        ];
    }
}
