<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Actions\SyncPointagesFromZkteco;

class SyncPointage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-pointage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    
    
    public function handle()
    {
        $this->info('Syncing pointage...');
        $var = new SyncPointagesFromZkteco; // Décommenté
        $var(); // Décommenté
        $this->info('Pointage synced successfully!');
    }
}