<?php

namespace App\Actions;

use App\Models\dispositif_biometrique;
use App\Models\Employe;
use App\Models\Grh;
use Carbon\Carbon;
use App\Models\Presence;
use App\Models\PointageBiometrique;
use MehediJaman\LaravelZkteco\LaravelZkteco;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncPointagesFromZkteco
{
public function __invoke(): void
{
    set_time_limit(300);
    Log::info("Début de la synchronisation ZKTeco. Fuseau horaire du serveur : " . config('app.timezone') . ", Date : " . now()->toDateString());

    $devices = dispositif_biometrique::all();
    Log::info("Nombre de dispositifs biométriques trouvés : {$devices->count()}");
    $hasRecords = false;

    if ($devices->isEmpty()) {
        Log::error('Aucun dispositif ZKTeco configuré. Synchronisation annulée.');
        throw new \RuntimeException('Aucun dispositif ZKTeco configuré.');
    }

    foreach ($devices as $device) {
        Log::info("Traitement du dispositif : {$device->ip}:{$device->port}");
        if (! $this->isDeviceReachable($device->ip, $device->port)) {
            Log::warning("Le dispositif {$device->ip}:{$device->port} n'est pas joignable");
            continue;
        }

        try {
            $zk = new LaravelZkteco($device->ip, $device->port);
            if (! $zk->connect()) {
                Log::error("Échec de connexion au dispositif {$device->ip}:{$device->port}");
                continue;
            }

            $records = $zk->getAttendance();
            Log::info("{$device->ip}:{$device->port} : " . count($records) . " pointages récupérés");

            if (!empty($records)) {
                $hasRecords = true;
            }

            foreach ($records as $record) {
                $userid = $record['id'];
                $timestamp = Carbon::parse($record['timestamp']);
                Log::info("Traitement du pointage : USERID={$userid}, Horodatage={$timestamp}");

                $user = $this->resolveUser($userid);
                if (! $user) {
                    Log::warning("Aucun utilisateur trouvé pour l'USERID : {$userid}");
                    continue;
                }

                $exists = PointageBiometrique::where('user_type', $user['type'])
                    ->where('user_id', $user['id'])
                    ->where('timestamp', $timestamp)
                    ->exists();

                if ($exists) {
                    Log::info("Pointage déjà existant pour {$user['type']}:{$user['id']} à {$timestamp}");
                    continue;
                }

                try {
                    PointageBiometrique::create([
                        'user_type' => $user['type'],
                        'user_id' => $user['id'],
                        'timestamp' => $timestamp,
                    ]);
                    Log::info("Pointage créé pour {$user['type']}:{$user['id']} à {$timestamp}");
                } catch (\Exception $e) {
                    Log::error("Échec de création du pointage pour USERID: {$userid}, Horodatage: {$timestamp}. Erreur: {$e->getMessage()}");
                }
            }

            $zk->disconnect();
        } catch (\Exception $e) {
            Log::error("Erreur lors du traitement du dispositif {$device->ip}:{$device->port} : {$e->getMessage()}");
            continue;
        }
    }

    if (! $hasRecords) {
        Log::error('Aucun pointage récupéré depuis les dispositifs. Synchronisation annulée.');
        throw new \RuntimeException('Aucun pointage récupéré depuis les dispositifs.');
    }

    $this->organiserPresences();
    Log::info("Synchronisation ZKTeco terminée");
}

protected function resolveUser(string $userid): ?array
{
    if ($employe = DB::table('employes')->where('biometric_id', $userid)->first()) {
        return [
            'type' => 'employe',
            'id' => $employe->id,
            'shift_id' => $employe->shift_id,
            'name' => $employe->name,
            'prenom' => $employe->prenom,
        ];
    }

    if ($grh = DB::table('grhs')->where('biometric_id', $userid)->first()) {
        return [
            'type' => 'grh',
            'id' => $grh->id,
            'shift_id' => $grh->shift_id,
            'name' => $grh->name,
            'prenom' => $grh->prenom,
        ];
    }

    return null;
}


    protected function resolveUserByTypeAndId(string $type, int $id): array
    {
        $table = $type === 'employe' ? 'employes' : 'grhs';
        $user = DB::table($table)->where('id', $id)->first();
        return [
            'name' => $user->name ?? null,
            'prenom' => $user->prenom ?? null,
        ];
    }

    protected function getShift(string $userType, int $userId, ?string $date = null): ?object
    {
        $date = Carbon::parse($date ?? now())->toDateString();
        Log::info("Récupération du shift pour {$userType}:{$userId} le {$date}");

        if ($userType === 'employe') {
            $employe = Employe::find($userId);
            if ($employe) {
                $shift = $employe->shiftLe($date);
                if ($shift) {
                    Log::info("shift ID {$shift->id} trouvé pour employe:{$userId} le {$date} via shiftLe");
                    return (object) $shift->toArray();
                }
                Log::info("Aucun shift trouvé via shiftLe for employe:{$userId} le {$date}");
                return null;
            }
        } elseif ($userType === 'grh') {
            $grh = Grh::find($userId);
            if ($grh) {
                $shift = $grh->shiftLe($date);
                if ($shift) {
                    Log::info("shift ID {$shift->id} trouvé pour grh:{$userId} le {$date} via shiftLe");
                    return (object) $shift->toArray();
                }
                Log::info("Aucun shift trouvé via shiftLe pour grh:{$userId} le {$date}");
                return null;
            }
        }
        return null;
    }

    protected function organiserPresences(): void
    {
        $pointages = PointageBiometrique::whereDate('timestamp', '>=', now()->subDays(30)->toDateString())
            ->whereDate('timestamp', '<=', now()->toDateString())
            ->get();
        Log::info("Nombre de pointages trouvés pour la période : " . now()->subDays(2)->toDateString() . " à " . now()->addDay()->toDateString() . " : {$pointages->count()}");

        $groupes = $pointages->groupBy(function ($item) {
            $timestamp = Carbon::parse($item->timestamp);
            $shift = $this->getShift($item->user_type, $item->user_id, $timestamp->toDateString());
            
            if ($shift) {
                $shiftStart = Carbon::parse($shift->heure_debut);
                $shiftEnd = Carbon::parse($shift->heure_fin);
                
                // Check if this is a night shift (end time <= start time)
                if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
                    // For night shifts, determine the correct shift date
                    $shiftDate = $this->determineNightShiftDate($timestamp, $shift);
                    Log::info("Night shift detected for {$item->user_type}:{$item->user_id}. Timestamp: {$timestamp->toDateTimeString()}, Assigned shift date: {$shiftDate}");
                    return $item->user_type . '|' . $item->user_id . '|' . $shiftDate;
                }
            }
            
            // For regular shifts, use the timestamp date
            return $item->user_type . '|' . $item->user_id . '|' . $timestamp->toDateString();
        });

        foreach ($groupes as $key => $group) {
            [$type, $id, $date] = explode('|', $key);
            Log::info("Traitement de la présence pour {$type}:{$id} le {$date}");

            $times = $group->sortBy('timestamp')->pluck('timestamp')->map(fn($t) => Carbon::parse($t));
            $checkIn = $times->first();
            $checkOut = $times->count() > 1 ? $times->last() : null;

            $shift = $this->getShift($type, $id, $date);
            $isWorkingDay = $shift && $this->isJourTravail($date, $shift);

            // Normalize checkIn to shift start time if punched too early
            if ($shift && $checkIn) {
                $heureDebutShift = Carbon::parse($shift->heure_debut)->setDateFrom(Carbon::parse($date));
                if ($checkIn->lt($heureDebutShift)) {
                    Log::info("Le checkIn ({$checkIn->toDateTimeString()}) est avant l'heure de début du shift ({$heureDebutShift->toDateTimeString()}), normalisation.");
                    $checkIn = $heureDebutShift;
                }
            }

            $heures = $this->calculerHeuresTravaillees($checkIn, $checkOut, $shift, $date);
            $etat = $this->determinerEtat($checkIn, $checkOut, $shift);
            $anomalie = $this->detecterAnomalie($checkIn, $checkOut, $shift);

            // Set anomaly for non-working days
            if ($shift && !$isWorkingDay) {
                $anomalie = 'hors_shift';
                Log::info("Définition de l'anomalie 'hors_shift' pour {$type}:{$id} le {$date} : Jour non travaillé.");
            }

            // Calculate theoretical shift hours and check for 'incomplet' on working days
            if ($isWorkingDay && $checkIn && $checkOut && $shift && $heures) {
                $heureDebut = Carbon::parse($shift->heure_debut)->setDateFrom(Carbon::parse($date));
                $heureFin = Carbon::parse($shift->heure_fin)->setDateFrom(Carbon::parse($date));
                if ($heureFin->lessThanOrEqualTo($heureDebut)) {
                    $heureFin->addDay();
                }
                $theoreticalMinutes = $heureDebut->diffInMinutes($heureFin);
                if ($shift->pause && $shift->heure_debut_pause && $shift->heure_fin_pause) {
                    $pauseDebut = Carbon::parse($shift->heure_debut_pause)->setDateFrom(Carbon::parse($date));
                    $pauseFin = Carbon::parse($shift->heure_fin_pause)->setDateFrom(Carbon::parse($date));
                    if ($pauseFin->lessThanOrEqualTo($pauseDebut)) {
                        $pauseFin->addDay();
                    }
                    $pauseMinutes = $pauseDebut->diffInMinutes($pauseFin);
                    $theoreticalMinutes -= $pauseMinutes;
                } elseif ($shift->pause && $shift->duree_pause) {
                    $theoreticalMinutes -= $shift->duree_pause;
                }

                $adjustedMinPresence = $theoreticalMinutes - ($shift->tolerance_retard ?? 0) - ($shift->depart_anticipe ?? 0);
                $workedMinutes = $this->parseHeuresToMinutes($heures);

                if ($workedMinutes < $adjustedMinPresence) {
                    $anomalie = 'incomplet';
                    Log::info("Définition de l'anomalie 'incomplet' pour {$type}:{$id} le {$date} : {$workedMinutes} minutes travaillées < minimum ajusté {$adjustedMinPresence} minutes");
                }
            }

            // Set anomalie_resolue to false for hors_shift, true for no anomalies
            $anomalieResolue = $anomalie === 'hors_shift' ? false : ($anomalie === null);

            // Fetch user details to populate name and prenom
            $userDetails = $this->resolveUserByTypeAndId($type, $id);

            try {
                Presence::updateOrCreate(
                    [
                        'user_type' => $type,
                        'user_id' => $id,
                        'date' => $date,
                    ],
                    [
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'etat' => $etat,
                        'heures_travaillees' => $heures,
                        'anomalie_type' => $anomalie,
                        'anomalie_resolue' => $anomalieResolue,
                        'name' => $userDetails['name'] ?? null,
                        'prenom' => $userDetails['prenom'] ?? null,
                    ]
                );
                Log::info("Présence créée/mise à jour pour {$type}:{$id} le {$date} avec nom : {$userDetails['name']}, prénom : {$userDetails['prenom']}, état : {$etat}, anomalie résolue : " . ($anomalieResolue ? 'true' : 'false'));
            } catch (\Exception $e) {
                Log::error("Échec de création/mise à jour de la présence pour {$type}:{$id} le {$date} : {$e->getMessage()}");
            }
        }
    }

    protected function determineNightShiftDate(Carbon $timestamp, object $shift): string
    {
        $shiftStart = Carbon::parse($shift->heure_debut);
        $shiftEnd = Carbon::parse($shift->heure_fin);
        
        // If punch time is closer to shift start time, it's likely the shift start date
        // If punch time is closer to shift end time, it's likely the day before shift start        
        // If timestamp is in the evening/night (after 18:00), it's likely shift start
        if ($timestamp->hour >= 18) {
            return $timestamp->toDateString();
        }
        
        // If timestamp is in early morning (before 12:00), it's likely shift end from previous day
        if ($timestamp->hour < 12) {
            return $timestamp->subDay()->toDateString();
        }
        
        // For times between 12:00-18:00, determine based on proximity to shift times
        $distanceToStart = abs($timestamp->hour * 60 + $timestamp->minute - ($shiftStart->hour * 60 + $shiftStart->minute));
        $distanceToEnd = abs($timestamp->hour * 60 + $timestamp->minute - ($shiftEnd->hour * 60 + $shiftEnd->minute));
        
        if ($distanceToStart <= $distanceToEnd) {
            return $timestamp->toDateString();
        } else {
            return $timestamp->subDay()->toDateString();
        }
    }

    protected function parseHeuresToMinutes(?string $heures): int
    {
        if (!$heures) {
            return 0;
        }
        [$hours, $minutes] = explode(':', $heures);
        return (int)$hours * 60 + (int)$minutes;
    }

    protected function isJourTravail(string $date, object $shift): bool
    {
        $joursTravail = is_string($shift->jours_travail)
            ? json_decode($shift->jours_travail, true)
            : $shift->jours_travail;

        $jour = strtolower(Carbon::parse($date)->locale('fr')->dayName);
        return in_array($jour, $joursTravail);
    }

    protected function determinerEtat(?Carbon $checkIn, ?Carbon $checkOut, ?object $shift): string
    {
        if (! $checkIn && ! $checkOut) {
            return 'absent';
        }

        if (! $checkIn || ! $checkOut) {
            return 'absent';
        }

        if (! $shift) {
            return 'present';
        }

        $heureDebut = Carbon::today()->setTimeFromTimeString($shift->heure_debut);
        $heureFin = Carbon::today()->setTimeFromTimeString($shift->heure_fin);

        $toleranceRetard = $shift->tolerance_retard ?? 0;
        $departAnticipe = $shift->depart_anticipe ?? 0;

        if ($checkIn->lte($heureDebut->copy()->subMinutes($toleranceRetard))) {
            return 'present';
        }

        if ($checkIn->gt($heureDebut->copy()->addMinutes($toleranceRetard))) {
            return 'retard';
        }

        return 'present';
    }

    protected function calculerHeuresTravaillees(?Carbon $checkIn, ?Carbon $checkOut, ?object $shift, string $shiftDate): ?string
    {
        if (! $checkIn || ! $checkOut || ! $shift) {
            Log::warning("Données manquantes pour le calcul des heures : checkIn=" . ($checkIn ? $checkIn->toDateTimeString() : 'null') .
                ", checkOut=" . ($checkOut ? $checkOut->toDateTimeString() : 'null') .
                ", shift=" . ($shift ? json_encode($shift) : 'null'));
            return null;
        }

        Log::info("Calcul des heures pour le shift ID : {$shift->id}, heure_debut : {$shift->heure_debut}, heure_fin : {$shift->heure_fin}");

        // Set shift start and end times based on the shift date
        $heureDebut = Carbon::parse($shift->heure_debut)->setDateFrom(Carbon::parse($shiftDate));
        $heureFin = Carbon::parse($shift->heure_fin)->setDateFrom(Carbon::parse($shiftDate));
        
        // For night shifts, adjust end time to next day
        if ($heureFin->lessThanOrEqualTo($heureDebut)) {
            $heureFin->addDay();
            Log::info("Ajustement de l'heure de fin pour shift de nuit : {$shift->heure_fin} -> {$heureFin->toDateTimeString()}");
        }

        // Adjust start time to shift start (exclude hours before heure_debut)
        $effectiveStart = $checkIn->max($heureDebut);
        Log::info("Heure de début effective : {$effectiveStart->toDateTimeString()} (checkIn : {$checkIn->toDateTimeString()}, heure_debut : {$heureDebut->toDateTimeString()})");

        // Adjust end time to shift end or check_out, whichever is earlier
        $adjustedCheckOut = $checkOut->copy();
        
        // For night shifts, if checkOut is on the next day but appears to be from previous day, adjust it
        if ($heureFin->greaterThan($heureDebut->copy()->addHours(12)) && $checkOut->lessThan($heureDebut)) {
            $adjustedCheckOut->addDay();
            Log::info("Ajustement du checkOut pour shift de nuit : {$checkOut->toDateTimeString()} -> {$adjustedCheckOut->toDateTimeString()}");
        }
        
        $effectiveEnd = $adjustedCheckOut->min($heureFin);
        Log::info("Heure de fin effective : {$effectiveEnd->toDateTimeString()} (checkOut : {$adjustedCheckOut->toDateTimeString()}, heure_fin : {$heureFin->toDateTimeString()})");

        // Calculate total minutes worked within shift
        if ($effectiveEnd->lessThanOrEqualTo($effectiveStart)) {
            Log::warning("L'heure de fin effective est avant ou égale à l'heure de début. Heures mises à 0.");
            return '00:00:00';
        }
        $totalMinutes = $effectiveStart->diffInMinutes($effectiveEnd);
        Log::info("Minutes totales initiales : {$totalMinutes} (de {$effectiveStart->toDateTimeString()} à {$effectiveEnd->toDateTimeString()})");

        // Handle pause if defined
        if ($shift->pause && $shift->heure_debut_pause && $shift->heure_fin_pause) {
            $pauseDebut = Carbon::parse($shift->heure_debut_pause)->setDateFrom(Carbon::parse($shiftDate));
            $pauseFin = Carbon::parse($shift->heure_fin_pause)->setDateFrom(Carbon::parse($shiftDate));
            if ($pauseFin->lessThanOrEqualTo($pauseDebut)) {
                $pauseFin->addDay();
                Log::info("Ajustement de la fin de pause pour shift de nuit : {$shift->heure_fin_pause} -> {$pauseFin->toDateTimeString()}");
            }
            $overlapStart = $effectiveStart->max($pauseDebut);
            $overlapEnd = $effectiveEnd->min($pauseFin);
            if ($overlapEnd->greaterThan($overlapStart)) {
                $overlapMinutes = $overlapStart->diffInMinutes($overlapEnd);
                $totalMinutes -= $overlapMinutes;
                Log::info("Pause soustraite : {$overlapMinutes} minutes (de {$overlapStart->toDateTimeString()} à {$overlapEnd->toDateTimeString()})");
            }
        } elseif ($shift->pause && $shift->duree_pause) {
            $totalMinutes -= $shift->duree_pause;
            Log::info("Durée de pause fixe soustraite : {$shift->duree_pause} minutes");
        }

        // Ensure non-negative minutes
        if ($totalMinutes < 0) {
            Log::warning("Minutes totales négatives calculées : {$totalMinutes}. Mise à 0.");
            $totalMinutes = 0;
        }

        // Convert to HH:MM:00 format
        $heures = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        $result = sprintf('%02d:%02d:00', $heures, $minutes);
        Log::info("Heures calculées finales : {$result}");

        return $result;
    }

    protected function detecterAnomalie(?Carbon $in, ?Carbon $out, ?object $shift): ?string
    {
        if (! $in && ! $out) {
            return 'absent';
        }
        if ($in && ! $out) {
            return 'unique_pointage';
        }
        if (! $in && $out) {
            return 'incomplet';
        }
        if ($in && $out && $shift) {
            $heureDebut = Carbon::parse($shift->heure_debut)->setDateFrom($in);
            $heureFin = Carbon::parse($shift->heure_fin)->setDateFrom($in);
            if ($heureFin->lessThanOrEqualTo($heureDebut)) {
                $heureFin->addDay();
            }
            $tolerance = $shift->tolerance_retard;
            if ($in->lt($heureDebut->subMinutes($tolerance)) || $out->gt($heureFin->addMinutes($tolerance))) {
                return 'hors_shift';
            }
        }
        return null;
    }

    protected function isDeviceReachable(string $ip, int $port): bool
    {
        $connection = @fsockopen($ip, $port, $errno, $errstr, 2);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        Log::error("Le dispositif {$ip}:{$port} n'est pas joignable. Erreur : {$errstr} ({$errno})");
        return false;
    }
}
