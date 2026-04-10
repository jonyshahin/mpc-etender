<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            'Civil Works' => ['أعمال مدنية', ['Earthworks' => 'أعمال ترابية', 'Concrete' => 'خرسانة', 'Masonry' => 'بناء']],
            'MEP' => ['ميكانيك وكهرباء وسباكة', ['Mechanical' => 'ميكانيكية', 'Electrical' => 'كهربائية', 'Plumbing' => 'سباكة']],
            'Finishing' => ['تشطيبات', ['Painting' => 'دهان', 'Tiling' => 'بلاط', 'Carpentry' => 'نجارة']],
            'Steelwork' => ['أعمال حديدية', ['Structural Steel' => 'حديد إنشائي', 'Metal Cladding' => 'تكسية معدنية']],
            'HVAC' => ['تكييف وتبريد', ['Ductwork' => 'مجاري هواء', 'Chillers' => 'مبردات']],
            'Roads & Infrastructure' => ['طرق وبنية تحتية', ['Asphalt' => 'أسفلت', 'Drainage' => 'تصريف', 'Utilities' => 'مرافق']],
            'Landscaping' => ['تنسيق حدائق', ['Irrigation' => 'ري', 'Hardscape' => 'أعمال صلبة', 'Softscape' => 'أعمال خضراء']],
        ];

        $sortOrder = 0;
        foreach ($tree as $parentEn => [$parentAr, $children]) {
            $parent = Category::updateOrCreate(
                ['name_en' => $parentEn, 'parent_id' => null],
                ['name_ar' => $parentAr, 'is_active' => true, 'sort_order' => $sortOrder++]
            );

            $childSort = 0;
            foreach ($children as $childEn => $childAr) {
                Category::updateOrCreate(
                    ['name_en' => $childEn, 'parent_id' => $parent->id],
                    ['name_ar' => $childAr, 'is_active' => true, 'sort_order' => $childSort++]
                );
            }
        }
    }
}
