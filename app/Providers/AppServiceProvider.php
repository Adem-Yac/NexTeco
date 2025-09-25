<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }


    public function boot(): void
    {
        Relation::morphMap([
            'employe' => \App\Models\Employe::class,
            'grh' => \App\Models\Grh::class,
            'admin' => \App\Models\Admin::class,
        ]);
    }
}
