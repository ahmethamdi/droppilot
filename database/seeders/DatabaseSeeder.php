<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super_admin', 'reseller_owner', 'reseller_staff', 'supplier_owner'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@droppilot.test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles(['super_admin']);

        $supplier = Supplier::updateOrCreate(
            ['slug' => 'vapor-handels-gmbh'],
            [
                'name' => 'Vapor Handels GmbH (myhookah.de)',
                'kind' => 'plenty',
                'plenty_base_url' => 'https://p57085.my.plentysystems.com',
                'plenty_login_user' => 'apitest',
                'plenty_login_password' => 'EaaRRx5k8gSBAGr',
                'status' => 'active',
                'default_plenty_id' => 62087, // myhookah Mandant
                'default_referrer_id' => '1.00', // Webshop (start; özel Herkunft tanımlanınca güncellenir)
            ],
        );

        $tenant = Tenant::updateOrCreate(
            ['slug' => 'mcvapes'],
            [
                'name' => 'McVapes',
                'status' => 'active',
                'plan' => 'starter',
            ],
        );

        $tenant->suppliers()->syncWithoutDetaching([
            $supplier->id => [
                'plenty_contact_id' => 13426, // Koray Özkan / McVapes
                'markup_pct' => 0,
                'status' => 'active',
            ],
        ]);
    }
}
