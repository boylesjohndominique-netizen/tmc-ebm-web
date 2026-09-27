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
                'password'=>'password123',
                'account_status'=>'Active',
                'is_super_admin'=>true,
            ]
        );

        $categories = [
            'Information Technology' => 'Computers and IT accessories',
            'Audio Visual' => 'AV and presentation equipment',
            'Office Equipment' => 'Common office machines',
            'Electrical' => 'Cables, power and storage devices',
            'Science Laboratory' => 'Laboratory instruments',
            'General Equipment' => 'Common college equipment',
        ];

        $categoryModels = [];
        foreach ($categories as $catName => $catDesc) {
            $categoryModels[$catName] = Category::firstOrCreate(
                ['name' => $catName],
                ['description' => $catDesc]
            );
        }

        $items = [
            ['name' => 'Laptop', 'category' => 'Information Technology', 'description' => 'College laptop for academic activities.', 'quantity' => 20, 'condition' => 'Good'],
            ['name' => 'Gaming Laptop', 'category' => 'Information Technology', 'description' => 'High-spec laptop for multimedia classes.', 'quantity' => 10, 'condition' => 'Good'],
            ['name' => 'Tablet', 'category' => 'Information Technology', 'description' => 'Android tablet for e-learning activities.', 'quantity' => 25, 'condition' => 'Good'],
            ['name' => 'Chromebook', 'category' => 'Information Technology', 'description' => 'Lightweight laptop for web-based tasks.', 'quantity' => 30, 'condition' => 'Good'],
            ['name' => 'Computer Mouse', 'category' => 'Information Technology', 'description' => 'USB mouse for computer laboratory use.', 'quantity' => 50, 'condition' => 'Good'],
            ['name' => 'Projector', 'category' => 'Audio Visual', 'description' => 'Multimedia projector for classrooms.', 'quantity' => 8, 'condition' => 'Good'],
            ['name' => 'Projector Screen', 'category' => 'Audio Visual', 'description' => 'Pull-down projector screen for lectures.', 'quantity' => 6, 'condition' => 'Good'],
            ['name' => 'LED TV', 'category' => 'Audio Visual', 'description' => '42-inch LED television for presentations.', 'quantity' => 8, 'condition' => 'Good'],
            ['name' => 'Digital Camera', 'category' => 'Audio Visual', 'description' => 'Camera for school documentation and activities.', 'quantity' => 5, 'condition' => 'Good'],
            ['name' => 'Video Camera', 'category' => 'Audio Visual', 'description' => 'Camcorder for school events coverage.', 'quantity' => 6, 'condition' => 'Good'],
            ['name' => 'Tripod', 'category' => 'Audio Visual', 'description' => 'Camera tripod for documentation.', 'quantity' => 15, 'condition' => 'Good'],
            ['name' => 'Microphone', 'category' => 'Audio Visual', 'description' => 'Wired microphone for seminars.', 'quantity' => 20, 'condition' => 'Good'],
            ['name' => 'Wireless Microphone', 'category' => 'Audio Visual', 'description' => 'Wireless mic set for school programs.', 'quantity' => 12, 'condition' => 'Good'],
            ['name' => 'Speaker', 'category' => 'Audio Visual', 'description' => 'Portable speaker for campus events.', 'quantity' => 10, 'condition' => 'Good'],
            ['name' => 'Amplifier', 'category' => 'Audio Visual', 'description' => 'Audio amplifier for assemblies.', 'quantity' => 4, 'condition' => 'Good'],
            ['name' => 'Printer', 'category' => 'Office Equipment', 'description' => 'Multifunction inkjet printer.', 'quantity' => 6, 'condition' => 'Good'],
            ['name' => 'Scanner', 'category' => 'Office Equipment', 'description' => 'Flatbed document scanner.', 'quantity' => 5, 'condition' => 'Good'],
            ['name' => 'HDMI Cable', 'category' => 'Electrical', 'description' => 'HDMI cable for presentation equipment.', 'quantity' => 25, 'condition' => 'Good'],
            ['name' => 'Extension Cord', 'category' => 'Electrical', 'description' => 'Electrical extension cord.', 'quantity' => 15, 'condition' => 'Good'],
            ['name' => 'Extension Reel', 'category' => 'Electrical', 'description' => '10-meter extension cord reel.', 'quantity' => 10, 'condition' => 'Good'],
            ['name' => 'Power Strip', 'category' => 'Electrical', 'description' => '6-outlet surge-protected power strip.', 'quantity' => 20, 'condition' => 'Good'],
            ['name' => 'Flash Drive', 'category' => 'Electrical', 'description' => '32GB USB flash drive.', 'quantity' => 40, 'condition' => 'Good'],
            ['name' => 'Microscope', 'category' => 'Science Laboratory', 'description' => 'Compound light microscope for biology.', 'quantity' => 12, 'condition' => 'Good'],
            ['name' => 'Scientific Calculator', 'category' => 'Science Laboratory', 'description' => 'Graphing calculator for math classes.', 'quantity' => 45, 'condition' => 'Good'],
            ['name' => 'Lab Thermometer', 'category' => 'Science Laboratory', 'description' => 'Digital laboratory thermometer.', 'quantity' => 30, 'condition' => 'Good'],
        ];

        foreach ($items as $item) {
            $catId = $categoryModels[$item['category']]->id;
            Equipment::firstOrCreate(
                ['name' => $item['name']],
                [
                    'category_id' => $catId,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'available_quantity' => $item['quantity'],
                    'condition' => $item['condition'],
                    'status' => 'Available',
                ]
            );
        }

        // Demo users/borrowings are intentionally NOT seeded anymore —
        // accounts now come from real mobile registrations. Equipment and
        // the super admin are still seeded above.
    }
}
