<?php

use Illuminate\Database\Seeder;

class BankNameTableSeeder extends Seeder
{
    public function run()
    {
        

        \DB::table('bank_name')->delete();
        
        \DB::table('bank_name')->insert(
            array (
                0 => 
            array (
                'id_bank_name' => 1,
                'bank_code' => 'bca',
                'bank_name' => 'PT. BANK CENTRAL ASIA TBK.',
                'bank_image' => NULL,
                'withdrawal_fee_formula' => NULL,
                'created_at' => NULL,
                'updated_at' => '2023-02-14 14:15:02',
            ),
            1 => 
            array (
                'id_bank_name' => 2,
                'bank_code' => 'bni',
            'bank_name' => 'PT. BANK NEGARA INDONESIA (PERSERO)',
                'bank_image' => NULL,
                'withdrawal_fee_formula' => NULL,
                'created_at' => NULL,
                'updated_at' => '2023-02-14 14:15:02',
            ),
            2 => 
            array (
                'id_bank_name' => 3,
                'bank_code' => 'bri',
            'bank_name' => 'PT. BANK RAKYAT INDONESIA (PERSERO)',
                'bank_image' => NULL,
                'withdrawal_fee_formula' => NULL,
                'created_at' => NULL,
                'updated_at' => '2023-02-14 14:15:02',
            ),
            3 => 
            array (
                'id_bank_name' => 4,
                'bank_code' => 'mandiri',
            'bank_name' => 'PT. BANK MANDIRI (PERSERO) TBK.',
                'bank_image' => NULL,
                'withdrawal_fee_formula' => NULL,
                'created_at' => NULL,
                'updated_at' => '2023-02-14 14:15:02',
            ),)
                
        );
    }
}