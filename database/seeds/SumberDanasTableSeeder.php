<?php

use Illuminate\Database\Seeder;

class SumberDanasTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('sumber_danas')->delete();
        
        \DB::table('sumber_danas')->insert(array (
            0 => 
            array (
                'id_sumber_dana' => 1,
                'sumber_dana' => 'Pribadi',
                'created_at' => '2023-07-03 15:11:33',
                'updated_at' => '2023-07-03 15:11:33',
            ),
            1 => 
            array (
                'id_sumber_dana' => 2,
                'sumber_dana' => 'Other',
                'created_at' => '2023-07-03 15:11:33',
                'updated_at' => '2023-07-03 15:11:33',
            ),
            2 => 
            array (
                'id_sumber_dana' => 3,
                'sumber_dana' => 'Uang Kas',
                'created_at' => '2023-07-03 15:11:33',
                'updated_at' => '2023-07-03 15:11:33',
            ),
        ));
        
        
    }
}