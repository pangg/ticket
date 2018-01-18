<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // $this->call(UsersTableSeeder::class);
        $basePath = app()->basePath();
        $path = $basePath.'/ticket.sql';
        if (file_exists($path)) {

            $content = file_get_contents($path);
            DB::getPdo()->exec($content);
        }
    }
}
