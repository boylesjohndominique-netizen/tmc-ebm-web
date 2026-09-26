<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Student','Faculty','Staff','Administrator'] as $name) {
            Role::firstOrCreate(['name'=>$name]);
        }

        $adminRole=Role::where('name','Administrator')->first();
        User::firstOrCreate(
            ['username'=>'admin'],
            [
                'role_id'=>$adminRole->id,
                'first_name'=>'TMC',
                'last_name'=>'Administrator',
                'email'=>'admin@tmc.edu.ph',
                'password'=>'password123'
            ]
        );

        $items=[
            ['name'=>'Laptop','description'=>'College laptop for academic activities.','quantity'=>20,'condition'=>'Good'],
            ['name'=>'Projector','description'=>'Multimedia projector for classrooms.','quantity'=>8,'condition'=>'Good'],
            ['name'=>'HDMI Cable','description'=>'HDMI cable for presentation equipment.','quantity'=>25,'condition'=>'Good'],
            ['name'=>'Extension Cord','description'=>'Electrical extension cord.','quantity'=>15,'condition'=>'Good'],
            ['name'=>'Digital Camera','description'=>'Camera for school documentation and activities.','quantity'=>5,'condition'=>'Good'],
        ];

        foreach ($items as $item) {
            $category=Category::firstOrCreate(['name'=>'General Equipment'],['description'=>'Common college equipment']);
            Equipment::firstOrCreate(['name'=>$item['name']],[
                'category_id'=>$category->id,
                'description'=>$item['description'],
                'quantity'=>$item['quantity'],
                'available_quantity'=>$item['quantity'],
                'condition'=>$item['condition'],
                'status'=>'Available'
            ]);
        }

        // Small realistic dataset (borrowers + borrowing/return requests)
        // so the admin pages aren't empty. Remove if unwanted.
        $this->call(DemoDataSeeder::class);
    }
}
