<?php

namespace CareSet\Zermelo;

use CareSet\Zermelo\Console\ZermeloInstallCommand;
use CareSet\Zermelo\Console\ZermeloMakeDemoCommand;
use CareSet\Zermelo\Console\ZermeloMakeReportCommand;
use CareSet\Zermelo\Models\ZermeloDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Class ZermeloServiceProvider extends \Illuminate\Support\ServiceProvider
{
    protected function presentations()
    {
        return [];
    }

    /*
     * Registration happens before boot, so this is where we gather static configuration
     * and register things to be used later.
     */
	public function register()
	{
        require_once __DIR__ . '/helpers.php';

        /*
         * Register our zermelo view make command which:
         *  - Copies views
         *  - Exports configuration
         *  - Exports Assets
         */
        $this->commands([
            ZermeloInstallCommand::class,
            ZermeloMakeReportCommand::class
        ]);

        /*
         * Merge with main config so parameters are accessable.
         * Try to load config from the app's config directory first,
         * then load from the package.
         */
        if ( file_exists(  config_path( 'zermelo.php' ) ) ) {
            $this->mergeConfigFrom(
                config_path( 'zermelo.php' ), 'zermelo'
            );
        } else {
            $this->mergeConfigFrom(
                __DIR__.'/config/zermelo.php', 'zermelo'
            );
        }

        // Register the cache database connection if we have a zermelo db
        $zermelo_db = config( 'zermelo.ZERMELO_DB' );
        if ( ZermeloDatabase::doesDatabaseExist( $zermelo_db ) ) {
            ZermeloDatabase::configure( $zermelo_db );
        }
	}

	public function boot( Router $router )
	{
        // routes
        $this->registerApiRoutes();

        // Boot our reports
        $this->registerReports();
	}

    /**
     * Register the application's Zermelo reports.
     *
     * @return void
     */
    protected function registerReports()
    {
        $reportDir = app_path( 'Reports' );
        if ( File::isDirectory($reportDir) ) {
            Zermelo::reportsIn( $reportDir );
        }
    }

    /**
     * Load the given routes file if routes are not already cached.
     *
     * @param  string  $path
     * @return void
     */
    protected function loadRoutesFrom($path)
    {
        if (! $this->app->routesAreCached()) {
            require $path;
        }
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    protected function registerApiRoutes()
    {
        Route::group( $this->routeConfiguration(), function () {

            // Load the core zermelo api routes including sockets
            $this->loadRoutesFrom(__DIR__.'/routes/api.zermelo.php');

            $tabular_api_prefix = config('zermelo.TABULAR_API_PREFIX');
            Route::group( ['prefix' => $tabular_api_prefix ], function() {
                $this->loadRoutesFrom(__DIR__.'/routes/api.tabular.php');
            });

            $graph_api_prefix = config('zermelo.GRAPH_API_PREFIX');
            Route::group( ['prefix' => $graph_api_prefix ], function() {
                $this->loadRoutesFrom(__DIR__.'/routes/api.graph.php');
            });

        });
    }

    /**
     * Get the Nova route group configuration array.
     *
     * @return array
     */
    protected function routeConfiguration()
    {
        return [
            'namespace' => 'CareSet\Zermelo\Http\Controllers',
            'domain' => config('zermelo.domain', null),
            'as' => 'zermelo.api.',
            'prefix' => config( 'zermelo.API_PREFIX' ),
            'middleware' => 'api',
        ];
    }
}
