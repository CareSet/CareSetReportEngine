<?php

namespace CareSet\Zermelo\Console;

use CareSet\Zermelo\Models\DatabaseCache;
use CareSet\Zermelo\Models\ZermeloDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ZermeloInstallCommand extends AbstractZermeloInstallCommand
{
    protected $config_file = __DIR__.'/../config/zermelo.php';

    protected $signature = 'install:zermelo
                    {--database= : Pass in the database name}
                    {--force : Overwrite existing views and database by default}';

    public function handle()
    {
        // Do view, config and asset installing first
        parent::handle();

        $config_changes = false;
        // If the user specifies a database name, user that, otherwise
        // use the default database name
        if ( $this->option( 'database' ) ) {
            $zermelo_db_name = $this->option( 'database' );
            $config_changes = true;
        } else {
            $zermelo_db_name = config( 'zermelo.ZERMELO_DB' );
        }

        $create_zermelo_db = true;
        if ( ZermeloDatabase::doesDatabaseExist( $zermelo_db_name ) &&
            ! $this->option('force') ) {

            if ( !$this->confirm("The Zermelo database '".$zermelo_db_name."' already exists. Do you want to DROP it and recreate it?")) {
                $create_zermelo_db = false;
            }
        }

        // Do we need to create the database, or do we migrate only?
        if ( $create_zermelo_db ) {
            $this->runZermeloInitialMigration( $zermelo_db_name );
        } else {
            $this->migrateDatabase( $zermelo_db_name );
        }

        if ( ! $this->option('force') ) {
            if ( $this->confirm("Would you like to use your previously installed Bootstrap CSS file?" )) {
                $bootstrap_css_location = $this->ask("Please paste the path of your bootstrap CSS file relative to public");
                // Write the bootstrap CSS location to the master config
                config( [ 'zermelo.BOOTSTRAP_CSS_LOCATION' => $bootstrap_css_location ] );
                $config_changes = true;
            }
        }

        // Write the runtime config changes
        if ( $config_changes ) {
            $array = Config::get( 'zermelo' );
            $data = var_export( $array, 1 );
            if ( File::put( config_path( 'zermelo.php' ), "<?php\n return $data ;" ) ) {
                $this->info( "Wrote new config file" );
            } else {
                $this->error("There were config changes, but there was an error writing config file.");
            }
        }

        return true;

    }

    public function runZermeloInitialMigration( $zermelo_db_name )
    {
        // Create the database
        if ( ZermeloDatabase::doesDatabaseExist( $zermelo_db_name ) ) {
            DB::connection()->statement( DB::connection()->raw( "DROP DATABASE IF EXISTS " . $zermelo_db_name . ";" ) );
        }

        DB::connection()->statement( DB::connection()->raw( "CREATE DATABASE `".$zermelo_db_name."`;" ) );

        // Write the database name to the master config
        config( ['zermelo.ZERMELO_DB' => $zermelo_db_name ] );

        // Configure the database for usage
        ZermeloDatabase::configure( $zermelo_db_name );

        $this->migrateDatabase( $zermelo_db_name );
    }

    public function migrateDatabase( $zermelo_db_name )
    {
        Artisan::call('migrate', [
            '--force' => true,
            '--database' => $zermelo_db_name,
            '--path' => 'vendor/careset/zermelo/database/migrations'
        ]);
    }
}
