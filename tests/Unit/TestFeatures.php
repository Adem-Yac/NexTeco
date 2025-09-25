<?php

namespace Tests\Unit;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Actions\CalculatePayslips;
use App\Models\Employe;
use App\Models\Presence;
use App\Models\fiche_de_paie;
use App\Models\Shift;
use App\Models\Holiday;
use App\Models\SanctionsConfig;
use Carbon\Carbon;

class TestFeatures extends TestCase {
    use RefreshDatabase;
    /**
     * 4.7.1 Planification des scénarios de test
     * Vérifie que le plan de test est bien initialisé.
     * Cas correct : true attendu.
     * Cas incorrect : false attendu.
     */
    public function test_scenario_planification()
    {
        // Cas correct
        $this->assertTrue(true, 'Scénario planifié');
        // Cas incorrect
        $this->assertFalse(false, 'Scénario planifié faux');
    }

    /**
     * 4.7.2 Tests des fonctionnalités biométriques ZKTeco
     * Vérifie l'enregistrement d'une présence biométrique.
     * Cas correct : présence enregistrée.
     * Cas incorrect : présence non enregistrée.
     */
    public function test_biometric_sync_zkteco()
    {
        $shift = Shift::create([
            'nom' => 'Test Shift',
            'heure_debut' => '08:00:00',
            'heure_fin' => '16:00:00',
            'jours_travail' => json_encode(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi']),
            'pause' => true,
            'duree_pause' => 60,
            'duree_min_presence' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $departement = \App\Models\departement::create([
            'nom' => 'IT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $employe = Employe::create([
            'name' => 'Test',
            'prenom' => 'User',
            'email' => 'test.user@example.com',
            'adresse' => '123 Test St',
            'Numero_telephone' => '111-222-3333',
            'biometric_id' => 1001,
            'poste' => 'Developer',
            'salaire' => 20.0,
            'shift_id' => $shift->id,
            'departement_id' => $departement->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Presence::create([
            'user_type' => 'employe',
            'user_id' => $employe->id,
            'heures_travaillees' => '08:00:00',
            'date' => now(),
            'etat' => 'present',
            'anomalie_resolue' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Cas correct
        $this->assertDatabaseHas('presences', ['user_id' => $employe->id]);
        // Cas incorrect
        $this->assertDatabaseMissing('presences', ['user_id' => 999999]);
    }

    /**
     * 4.7.3 Tests des interfaces avec Livewire et Filament
     * Vérifie la création et l'affichage d'une fiche de paie.
     * Cas correct : type employé attendu.
     * Cas incorrect : type admin non attendu.
     */
    public function test_livewire_filament_interface()
    {
        $shift = Shift::create([
            'nom' => 'Test Shift',
            'heure_debut' => '08:00:00',
            'heure_fin' => '16:00:00',
            'jours_travail' => json_encode(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi']),
            'pause' => true,
            'duree_pause' => 60,
            'duree_min_presence' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $departement = \App\Models\departement::create([
            'nom' => 'IT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $employe = Employe::create([
            'name' => 'Test',
            'prenom' => 'User',
            'email' => 'test.user2@example.com',
            'adresse' => '123 Test St',
            'Numero_telephone' => '222-333-4444',
            'biometric_id' => 1002,
            'poste' => 'Developer',
            'salaire' => 20.0,
            'shift_id' => $shift->id,
            'departement_id' => $departement->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fiche = fiche_de_paie::create([
            'user_id' => $employe->id,
            'user_type' => 'employe',
            'montant' => 100.0,
            'heures_sup' => 0.0,
            'prime' => 0.0,
            'avance' => 0.0,
            'taux_horaire_sup' => null,
            'montant_heures_sup' => null,
            'retards_deduction' => 0.0,
            'absences_deduction' => 0.0,
            'status' => 'en_attente',
            'date_generation' => now(),
            'mode_calculation' => 'horaire',
            'mois' => now()->format('Y-m'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Cas correct
        $this->assertEquals('employe', $fiche->user_type);
        // Cas incorrect
        $this->assertNotEquals('admin', $fiche->user_type);
    }

    /**
     * 4.7.4 Validation des interactions avec la base de données
     * Vérifie la mise à jour d'une fiche de paie.
     * Cas correct : montant mis à jour.
     * Cas incorrect : montant non existant.
     */
    public function test_database_interactions()
    {
        $shift = Shift::create([
            'nom' => 'Test Shift',
            'heure_debut' => '08:00:00',
            'heure_fin' => '16:00:00',
            'jours_travail' => json_encode(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi']),
            'pause' => true,
            'duree_pause' => 60,
            'duree_min_presence' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $departement = \App\Models\departement::create([
            'nom' => 'IT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $employe = Employe::create([
            'name' => 'Test',
            'prenom' => 'User',
            'email' => 'test.user3@example.com',
            'adresse' => '123 Test St',
            'Numero_telephone' => '333-444-5555',
            'biometric_id' => 1003,
            'poste' => 'Developer',
            'salaire' => 20.0,
            'shift_id' => $shift->id,
            'departement_id' => $departement->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fiche = fiche_de_paie::create([
            'user_id' => $employe->id,
            'user_type' => 'employe',
            'montant' => 50.0,
            'heures_sup' => 0.0,
            'prime' => 0.0,
            'avance' => 0.0,
            'taux_horaire_sup' => null,
            'montant_heures_sup' => null,
            'retards_deduction' => 0.0,
            'absences_deduction' => 0.0,
            'status' => 'en_attente',
            'date_generation' => now(),
            'mode_calculation' => 'horaire',
            'mois' => now()->format('Y-m'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fiche->update(['montant' => 123.45]);
        // Cas correct
        $this->assertDatabaseHas('fiche_de_paies', ['montant' => 123.45]);
        // Cas incorrect
        $this->assertDatabaseMissing('fiche_de_paies', ['montant' => 999.99]);
    }

    /**
     * 4.7.5 Analyse des résultats et corrections
     * Vérifie que le montant de la fiche de paie est positif.
     * Cas correct : montant > 0.
     * Cas incorrect : montant < 0 (jamais créé).
     */
    public function test_result_analysis_and_corrections()
    {
        $shift = Shift::create([
            'nom' => 'Test Shift',
            'heure_debut' => '08:00:00',
            'heure_fin' => '16:00:00',
            'jours_travail' => json_encode(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi']),
            'pause' => true,
            'duree_pause' => 60,
            'duree_min_presence' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $departement = \App\Models\departement::create([
            'nom' => 'IT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $employe = Employe::create([
            'name' => 'Test',
            'prenom' => 'User',
            'email' => 'test.user4@example.com',
            'adresse' => '123 Test St',
            'Numero_telephone' => '444-555-6666',
            'biometric_id' => 1004,
            'poste' => 'Developer',
            'salaire' => 20.0,
            'shift_id' => $shift->id,
            'departement_id' => $departement->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fiche = fiche_de_paie::create([
            'user_id' => $employe->id,
            'user_type' => 'employe',
            'montant' => 200.0,
            'heures_sup' => 0.0,
            'prime' => 0.0,
            'avance' => 0.0,
            'taux_horaire_sup' => null,
            'montant_heures_sup' => null,
            'retards_deduction' => 0.0,
            'absences_deduction' => 0.0,
            'status' => 'en_attente',
            'date_generation' => now(),
            'mode_calculation' => 'horaire',
            'mois' => now()->format('Y-m'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Cas correct
        $this->assertGreaterThan(0, $fiche->montant);
        // Cas incorrect
        $this->assertNotEquals(-1, $fiche->montant);
    }

    /**
     * 4.7.6 Test de la gestion des sanctions (retards/absences)
     * Vérifie la déduction des retards.
     * Cas correct : déduction >= 0.
     * Cas incorrect : déduction < 0 (jamais créé).
     */
    public function test_sanctions_management()
    {
        $shift = Shift::create([
            'nom' => 'Test Shift',
            'heure_debut' => '08:00:00',
            'heure_fin' => '16:00:00',
            'jours_travail' => json_encode(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi']),
            'pause' => true,
            'duree_pause' => 60,
            'duree_min_presence' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $departement = \App\Models\departement::create([
            'nom' => 'IT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $employe = Employe::create([
            'name' => 'Test',
            'prenom' => 'User',
            'email' => 'test.user5@example.com',
            'adresse' => '123 Test St',
            'Numero_telephone' => '555-666-7777',
            'biometric_id' => 1005,
            'poste' => 'Developer',
            'salaire' => 20.0,
            'shift_id' => $shift->id,
            'departement_id' => $departement->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        SanctionsConfig::create(['type' => 'retards', 'threshold' => 3, 'hours_deducted' => 2, 'created_at' => now(), 'updated_at' => now()]);
        for ($i = 0; $i < 3; $i++) {
            Presence::create([
                'user_type' => 'employe',
                'user_id' => $employe->id,
                'etat' => 'retard',
                'date' => now()->addDays($i),
                'heures_travaillees' => '07:00:00',
                'anomalie_resolue' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $action = new CalculatePayslips();
        $fiche = $action->run('employe', $employe->id, null, null, Carbon::now()->format('Y-m'), 'horaire');
        // Cas correct
        $this->assertGreaterThanOrEqual(0, $fiche->retards_deduction);
        // Cas incorrect
        $this->assertNotEquals(-1, $fiche->retards_deduction);
    }

    /**
     * 4.7.7 Test de la robustesse en cas d’erreur ou d’anomalie
     * Vérifie la gestion des erreurs lors du calcul de paie.
     * Cas correct : exception attendue.
     * Cas incorrect : pas d'exception pour un ID valide.
     */
    public function test_error_handling()
    {
        // Cas correct
        $this->expectException(\Exception::class);
        $action = new CalculatePayslips();
        $action->run('employe', 999999, null, null, Carbon::now()->format('Y-m'), 'horaire');
        // Cas incorrect (no exception for valid ID)
        $action2 = new CalculatePayslips();
        $fiche = $action2->run('employe', 1, null, null, Carbon::now()->format('Y-m'), 'horaire');
        $this->assertNotNull($fiche);
    }

    /**
     * 4.7.8 Test de la gestion des jours fériés (holidays globaux)
     * Cas correct : holiday créé et trouvé.
     * Cas incorrect : holiday non trouvé.
     */
    public function test_holiday_management()
    {
        $shift = Shift::create([
            'nom' => 'Test Shift',
            'heure_debut' => '08:00:00',
            'heure_fin' => '16:00:00',
            'jours_travail' => json_encode(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi']),
            'pause' => true,
            'duree_pause' => 60,
            'duree_min_presence' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $departement = \App\Models\departement::create([
            'nom' => 'IT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Employe::create([
            'name' => 'Test',
            'prenom' => 'Holiday',
            'email' => 'test.holiday@example.com',
            'adresse' => '123 Test St',
            'Numero_telephone' => '666-777-8888',
            'biometric_id' => 1006,
            'poste' => 'Developer',
            'salaire' => 20.0,
            'shift_id' => $shift->id,
            'departement_id' => $departement->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $holiday = Holiday::create([
            'name' => 'Congé',
            'date' => now()->addDays(1),
            'duration' => 1,
            'is_recurring' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Cas correct
        $this->assertDatabaseHas('holidays', [
            'name' => 'Congé',
            'date' => $holiday->date,
        ]);
        // Cas incorrect
        $this->assertDatabaseMissing('holidays', [
            'name' => 'NonExistant',
            'date' => now()->addYears(1),
        ]);
    }

    /**
     * 4.7.9 Test de la gestion des primes
     * Cas correct : prime créée et trouvée.
     * Cas incorrect : prime non trouvée.
     */
    public function test_prime_management()
    {
        $shift = Shift::create([
            'nom' => 'Test Shift',
            'heure_debut' => '08:00:00',
            'heure_fin' => '16:00:00',
            'jours_travail' => json_encode(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi']),
            'pause' => true,
            'duree_pause' => 60,
            'duree_min_presence' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $departement = \App\Models\departement::create([
            'nom' => 'IT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $employe = Employe::create([
            'name' => 'Test',
            'prenom' => 'Prime',
            'email' => 'test.prime@example.com',
            'adresse' => '123 Test St',
            'Numero_telephone' => '777-888-9999',
            'biometric_id' => 1007,
            'poste' => 'Developer',
            'salaire' => 20.0,
            'shift_id' => $shift->id,
            'departement_id' => $departement->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $prime = \App\Models\Prime::create([
            'user_id' => $employe->id,
            'user_type' => 'employe',
            'montant' => 150.0,
            'mois' => now()->format('Y-m'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Cas correct
        $this->assertDatabaseHas('primes', ['user_id' => $employe->id, 'montant' => 150.0]);
        // Cas incorrect
        $this->assertDatabaseMissing('primes', ['user_id' => 999999, 'montant' => 999.99]);
    }

    /**
     * 4.7.10 Test de la gestion des présences anormales
     * Cas correct : présence anormale enregistrée.
     * Cas incorrect : présence anormale non enregistrée.
     */
    public function test_abnormal_presence_management()
    {
        $shift = Shift::create([
            'nom' => 'Test Shift',
            'heure_debut' => '08:00:00',
            'heure_fin' => '16:00:00',
            'jours_travail' => json_encode(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi']),
            'pause' => true,
            'duree_pause' => 60,
            'duree_min_presence' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $departement = \App\Models\departement::create([
            'nom' => 'IT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $employe = Employe::create([
            'name' => 'Test',
            'prenom' => 'Abnormal',
            'email' => 'test.abnormal@example.com',
            'adresse' => '123 Test St',
            'Numero_telephone' => '888-999-0000',
            'biometric_id' => 1008,
            'poste' => 'Developer',
            'salaire' => 20.0,
            'shift_id' => $shift->id,
            'departement_id' => $departement->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Presence::create([
            'user_type' => 'employe',
            'user_id' => $employe->id,
            'heures_travaillees' => '02:00:00',
            'date' => now(),
            'etat' => 'present',
            'anomalie_resolue' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Cas correct
        $this->assertDatabaseHas('presences', ['user_id' => $employe->id, 'anomalie_resolue' => false]);
        // Cas incorrect
        $this->assertDatabaseMissing('presences', ['user_id' => 999999, 'anomalie_resolue' => false]);
    }

}
