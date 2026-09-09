<?php

namespace Database\Seeders;

use App\Models\LoyaltyProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class GrowthModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedModules();
        $this->seedDefaultProgram();
    }

    private function seedPermissions(): void
    {
        $permissions = [
            'crm.view','crm.view_all','crm.manage','crm.interactions.manage',
            'loyalty.view','loyalty.manage','loyalty.adjust','loyalty.redeem',
            'delivery.view','delivery.view_all_locations','delivery.zones.manage','delivery.create','delivery.assign','delivery.update_status',
        ];
        foreach($permissions as $name) Permission::findOrCreate($name,'web');

        $rolePermissions = [
            'Admin'=>$permissions,
            'General Manager'=>$permissions,
            'Branch Manager'=>['crm.view','crm.manage','crm.interactions.manage','loyalty.view','loyalty.redeem','delivery.view','delivery.zones.manage','delivery.create','delivery.assign','delivery.update_status'],
            'Cashier'=>['crm.view','loyalty.view','loyalty.redeem','delivery.view','delivery.create'],
            'Delivery Driver'=>['delivery.view','delivery.update_status'],
        ];
        foreach ($rolePermissions as $roleName => $names) {
            // Sprint 08 introduces only Delivery Driver. Existing business roles
            // must not be invented if an installation uses different role names.
            $role = $roleName === 'Delivery Driver'
                ? Role::findOrCreate($roleName, 'web')
                : Role::query()
                    ->where('name', $roleName)
                    ->where('guard_name', 'web')
                    ->first();

            if (! $role) {
                continue;
            }

            // Additive only: never sync/revoke existing permissions.
            $role->givePermissionTo($names);
        }
    }

    private function seedModules(): void
    {
        if (! Schema::hasTable('modules')) return;
        $columns=Schema::getColumnListing('modules');
        $definitions=[
            'crm'=>['name'=>'إدارة علاقات العملاء CRM','description'=>'ملف العميل 360 والوسوم والعناوين والمتابعات.','type'=>'optional','icon'=>'crm','sort_order'=>800],
            'loyalty'=>['name'=>'برنامج الولاء','description'=>'دفتر نقاط العملاء والاكتساب والاستبدال والتسويات.','type'=>'optional','icon'=>'loyalty','sort_order'=>810],
            'delivery'=>['name'=>'التوصيل','description'=>'مناطق التوصيل والمهام والسائقون وسجل الحالات.','type'=>'optional','icon'=>'delivery','sort_order'=>820],
        ];
        foreach($definitions as $code=>$definition){
            $exists=DB::table('modules')->where('code',$code)->first();
            $payload=[];
            foreach($definition as $key=>$value) if(in_array($key,$columns,true)) $payload[$key]=$value;
            if(in_array('is_core',$columns,true)) $payload['is_core']=false;
            if(in_array('is_system',$columns,true)) $payload['is_system']=false;
            if(in_array('is_implemented',$columns,true)) $payload['is_implemented']=true;
            if(in_array('implemented',$columns,true)) $payload['implemented']=true;
            if(in_array('route_prefix',$columns,true)) $payload['route_prefix']=$code;
            if(in_array('updated_at',$columns,true)) $payload['updated_at']=now();
            if(!$exists){
                if(in_array('is_active',$columns,true)) $payload['is_active']=false;
                if(in_array('created_at',$columns,true)) $payload['created_at']=now();
                $payload['code']=$code;
                DB::table('modules')->insert($payload);
            } else {
                // Preserve current activation state.
                DB::table('modules')->where('code',$code)->update($payload);
            }
        }

        if (Schema::hasTable('module_dependencies')) {
            $dependencyMap=[
                'crm'=>['customers','locations'],
                'loyalty'=>['crm','sales'],
                'delivery'=>['sales','customers','locations'],
            ];
            foreach($dependencyMap as $moduleCode=>$requires){
                $moduleId=DB::table('modules')->where('code',$moduleCode)->value('id');
                if(!$moduleId) continue;
                foreach($requires as $requiredCode){
                    $requiredId=DB::table('modules')->where('code',$requiredCode)->value('id');
                    if($requiredId && !DB::table('module_dependencies')->where('module_id',$moduleId)->where('required_module_id',$requiredId)->exists()){ $row=['module_id'=>$moduleId,'required_module_id'=>$requiredId]; $depCols=Schema::getColumnListing('module_dependencies'); if(in_array('created_at',$depCols,true))$row['created_at']=now(); if(in_array('updated_at',$depCols,true))$row['updated_at']=now(); DB::table('module_dependencies')->insert($row); }
                }
            }
        }

        $this->attachCustomerGrowthBundle();
    }

    private function attachCustomerGrowthBundle(): void
    {
        if (!Schema::hasTable('module_bundles') || !Schema::hasTable('module_bundle_modules')) return;
        $bundleColumns=Schema::getColumnListing('module_bundles');
        $pivotColumns=Schema::getColumnListing('module_bundle_modules');
        if(!in_array('id',$bundleColumns,true) || !in_array('code',$bundleColumns,true) || !in_array('module_bundle_id',$pivotColumns,true) || !in_array('module_id',$pivotColumns,true)) return;
        $bundleId=DB::table('module_bundles')->where('code','customer_growth')->value('id');
        if(!$bundleId) return;
        $moduleIds=DB::table('modules')->whereIn('code',['crm','loyalty','delivery'])->pluck('id');
        foreach($moduleIds as $moduleId){ if(!DB::table('module_bundle_modules')->where('module_bundle_id',$bundleId)->where('module_id',$moduleId)->exists()){ $row=['module_bundle_id'=>$bundleId,'module_id'=>$moduleId]; if(in_array('created_at',$pivotColumns,true))$row['created_at']=now(); if(in_array('updated_at',$pivotColumns,true))$row['updated_at']=now(); DB::table('module_bundle_modules')->insert($row); } }
    }

    private function seedDefaultProgram(): void
    {
        if (!Schema::hasTable('loyalty_programs')) return;
        LoyaltyProgram::query()->firstOrCreate(['name'=>'برنامج الولاء الافتراضي'],[
            'points_per_currency_unit'=>1,'minimum_order_amount'=>0,'redemption_value_per_point'=>0,'minimum_redeem_points'=>0,'is_active'=>false,
        ]);
    }
}
