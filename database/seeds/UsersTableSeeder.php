<?php

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    public function run()
    {
        

        \DB::table('users')->delete();
        
        \DB::table('users')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Admin',
                'phone' => '0811223344',
                'id_membership' => NULL,
                'email' => 'hauwah415@gmail.com',
                'password' => '$2y$10$JgoY2K77XGHLyvuKjQZHKei5.loCHfzCR573c5n4Iy5OlcvxEAmj.',
                'id_city' => 501,
                'gender' => 'Male',
                'provider' => 'Tri',
                'birthday' => '1998-12-10',
                'phone_verified' => '1',
                'email_verified' => '1',
                'level' => 'Super Admin',
                'points' => 0,
                'android_device' => NULL,
                'ios_device' => NULL,
                'is_suspended' => '0',
                'remember_token' => NULL,
                'created_at' => '2018-05-09 16:18:32',
                'updated_at' => '2018-05-09 16:18:32',
            ),
        ));
        
        
    }
}