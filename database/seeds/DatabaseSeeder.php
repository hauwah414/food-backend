<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {

        $this->call(TextReplacesTableSeeder::class);
        $this->call(ProvincesTableSeeder::class);
        $this->call(CitiesTableSeeder::class);
        $this->call(AutocrmsTableSeeder::class);
        $this->call(CourierTableSeeder::class);
        $this->call(FeaturesTableSeeder::class);
        $this->call(UsersTableSeeder::class);
        $this->call(SettingsTableSeeder::class);
        $this->call(ConfigsTableSeeder::class);
        $this->call(OutletsTableSeeder::class);
        $this->call(ProductCategoriesTableSeeder::class);
        $this->call(ProductsTableSeeder::class);
        $this->call(ProductPricesTableSeeder::class);
        // $this->call(UserAddressesTableSeeder::class);
        // $this->call(ManualPaymentsTableSeeder::class);
        // $this->call(ManualPaymentMethodsTableSeeder::class);

        // $this->call(AutocrmsTableAddClaimDeals::class);
        $this->call(SettingJobsCelebrateSeeder::class);
        $this->call(PromoCampaignsTableSeeder::class);
        $this->call(PromoCampaignReferralsTableSeeder::class);
        $this->call(SubdistrictsTableSeeder::class);
        $this->call(DistrictsTableSeeder::class);
        $this->call(BankNameTableSeeder::class);
        $this->call(SumberDanasTableSeeder::class);
        $this->call(DepartmentsTableSeeder::class);
    }
}
