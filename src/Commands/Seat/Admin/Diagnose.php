<?php
/*
This file is part of SeAT

Copyright (C) 2015, 2016  Leon Jacobs

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

namespace Seat\Console\Commands\Seat\Admin;

use Carbon\Carbon;
use DB;
use Exception;
use File;
use Illuminate\Console\Command;
use Predis\Client;
use Seat\Eveapi\Api\Server\ServerStatus;

/**
 * Class Diagnose
 * @package Seat\Console\Commands\Seat\Admin
 */
class Diagnose extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seat:admin:diagnose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose potential SeAT installation problems';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $this->line('SeAT Diagnostics');
        $this->line('If you are not already doing so, it is recommended that you ' .
            'run this as the user the workers are running as.');
        $this->line('Eg:');
        $this->info('    sudo -u apache php artisan seat:admin:diagnose');
        $this->line('This helps to check whether the permissions are correct.');
        $this->line('');

        $this->environment_info();
        $this->line('');

        $this->check_debug();
        $this->line('');

        $this->check_storage();
        $this->line('');

        $this->check_database();
        $this->line('');

        $this->check_redis();
        $this->line('');

        $this->check_pheal();
        $this->line('');

        $this->call('seat:version');

        $this->line('SeAT Diagnostics complete');

    }

    /**
     * Print some information about the current environment
     */
    public function environment_info()
    {

        $this->line(' * Getting environment information');
        $this->info('Current User: ' . get_current_user());
        $this->info('PHP Version: ' . phpversion());
        $this->info('Host OS: ' . php_uname());
        $this->info('SeAT Basepath: ' . base_path());
    }

    /**
     * Check if DEBUG mode is enabled
     */
    public function check_debug()
    {

        $this->line(' * Checking DEBUG mode');

        if (env('APP_DEBUG') == true)
            $this->warn('Debug mode is enabled. This is not recommended in production!');
        else
            $this->info('Debug mode disabled');
    }

    /**
     * Check access to some important storage paths
     */
    public function check_storage()
    {

        $this->line(' * Checking storage');
        if (!File::isWritable(storage_path()))
            $this->error(storage_path() . ' is not writable');
        else
            $this->info(storage_path() . ' is writable');

        if (!File::isWritable(config('eveapi.config.pheal.cache_path')))
            $this->error(config('eveapi.config.pheal.cache_path') . ' is not writable');
        else
            $this->info(config('eveapi.config.pheal.cache_path') . ' is writable');

        if (!File::isWritable(storage_path() . '/sde/'))
            $this->error(storage_path() . '/sde/' . ' is not writable');
        else
            $this->info(storage_path() . '/sde/' . ' is writable');

        if (!File::isWritable(storage_path('logs/laravel.log')))
            $this->error(storage_path('logs/laravel.log') . ' is not writable');
        else
            $this->info(storage_path('logs/laravel.log') . ' is writable');
    }

    /**
     * Check if database access is OK
     */
    public function check_database()
    {

        $this->line(' * Checking Database');
        $this->table(['Setting', 'Value'], [
            ['Connection', env('DB_CONNECTION')],
            ['Host', env('DB_HOST')],
            ['Database', env('DB_DATABASE')],
            ['Username', env('DB_USERNAME')],
            ['Password', str_repeat('*', strlen(env('DB_PASSWORD')))]
        ]);

        try {

            $this->info('Connection OK to database: ' .
                DB::connection()->getDatabaseName());

        } catch (Exception $e) {

            $this->error('Unable to connect to database server: ' . $e->getMessage());
        }
    }

    /**
     * Check of redis access is OK
     */
    public function check_redis()
    {

        $this->line(' * Checking Redis');
        $this->table(['Setting', 'Value'], [
            ['Host', config('database.redis.default.host')],
            ['Port', config('database.redis.default.port')],
            ['Database', config('database.redis.default.database')]
        ]);

        $test_key = str_random(64);

        try {

            $redis = new Client([
                'host' => config('database.redis.default.host'),
                'port' => config('database.redis.default.port')
            ]);
            $this->info('Connected to Redis');

            $redis->set($test_key, Carbon::now());
            $this->info('Set random key of: ' . $test_key);

            $redis->expire($test_key, 10);
            $this->info('Set key to expire in 10 sec');

            $redis->get($test_key);
            $this->info('Read key OK');

        } catch (Exception $e) {

            $this->error('Redis test failed. ' . $e->getMessage());

        }
    }

    /**
     * Check if access to the EVE API OK
     */
    public function check_pheal()
    {

        $this->line(' * Checking Pheal EVE API Access');

        try {

            $status = (new ServerStatus)->setScope('server')
                ->getPheal()
                ->ServerStatus();

            $this->info('Server Online: ' . $status->serverOpen);
            $this->info('Online Players: ' . $status->onlinePlayers);

        } catch (Exception $e) {

            $this->error('Unable to call the EVE API: ' . $e->getMessage());

        }

    }
}
