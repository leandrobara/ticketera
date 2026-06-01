<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MakeSuperUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:superuser {username} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create super user username and password';

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
        $username = trim($this->argument('username'));
        $password = password_hash($this->argument('password'), PASSWORD_DEFAULT);
        $path = base_path('.env');
        if (file_exists($path)) {
            file_put_contents(
                $path,
                preg_replace('/APP_SUPER_USER_NAME=.*/', 'APP_SUPER_USER_NAME=' . $username, file_get_contents($path))
            );
            file_put_contents(
                $path,
                preg_replace(
                    '/APP_SUPER_USER_PASSWORD=.*/',
                    'APP_SUPER_USER_PASSWORD=' . preg_quote($password),
                    file_get_contents($path)
                )
            );
        }
    }
}
