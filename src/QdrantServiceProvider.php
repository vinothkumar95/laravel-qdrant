<?php

namespace Vinothkumar\Qdrant;

use Illuminate\Support\ServiceProvider;
use Vinothkumar\Qdrant\Services\QdrantService;

class QdrantServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/qdrant.php', 'qdrant');

        $this->app->singleton('qdrant', function () {
            return new QdrantService();
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/qdrant.php' => config_path('qdrant.php'),
        ], 'config');
    }
}
