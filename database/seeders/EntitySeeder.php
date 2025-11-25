<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Entity; // تأكد من أن اسم الموديل هو Entity

class EntitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // قائمة بالهيئات الحكومية السورية المحدثة
        $entities = [
            // --- الوزارات ---
            [
                'name' => 'وزارة الاتصالات وتقانة المعلومات',
                'description' => 'الجهة المسؤولة عن قطاع الاتصالات، والبريد، والإنترنت والخدمات الحكومية الإلكترونية.',
                'contact_email' => 'info@moct.gov.sy',
                'contact_phone' => '+963 11 227 0010',
            ],
            [
                'name' => 'وزارة الدفاع',
                'description' => 'الجهة المسؤولة عن القضايا المتعلقة بالدفاع والقوات المسلحة.',
                'contact_email' => 'Sy_Defense@mod.gov.sy',
                'contact_phone' => null, // تم ترك الرقم فارغًا لعدم توفره
            ],
            [
                'name' => 'وزارة الخارجية والمغتربين',
                'description' => 'الجهة المسؤولة عن العلاقات الدبلوماسية والشؤون المتعلقة بالمغتربين.',
                'contact_email' => 'info@mofaex.gov.sy',
                'contact_phone' => null,
            ],
            [
                'name' => 'وزارة الشؤون الاجتماعية والعمل',
                'description' => 'الجهة المسؤولة عن الشؤون الاجتماعية والعمل وقضايا الضمان الاجتماعي.',
                'contact_email' => 'maktabshafy2018@gmail.com',
                'contact_phone' => '+963 11 232 5221',
            ],
            [
                'name' => 'وزارة العدل',
                'description' => 'الجهة المسؤولة عن الجهاز القضائي والشؤون القانونية.',
                'contact_email' => 'info@moj.gov.sy',
                'contact_phone' => '+963 11 666 1260',
            ],
            [
                'name' => 'وزارة الأوقاف',
                'description' => 'الجهة المسؤولة عن الشؤون الدينية والأوقاف.',
                'contact_email' => 'mow.gov@gmail.com',
                'contact_phone' => null,
            ],
            [
                'name' => 'وزارة الصحة',
                'description' => 'الجهة المسؤولة عن الرعاية الصحية والمستشفيات والخدمات الطبية.',
                'contact_email' => 'info@moh.gov.sy',
                'contact_phone' => '+963 11 333 9600',
            ],
            [
                'name' => 'وزارة الإدارة المحلية والبيئة',
                'description' => 'الجهة المسؤولة عن الإدارة المحلية وشؤون البيئة والنظافة.',
                'contact_email' => 'info@mola.gov.sy',
                'contact_phone' => '+963 11 214 5700',
            ],
            [
                'name' => 'وزارة الزراعة والإصلاح الزراعي',
                'description' => 'الجهة المسؤولة عن الشؤون الزراعية والموارد المائية.',
                'contact_email' => 'agrisyria@gmail.com',
                'contact_phone' => null,
            ],
            [
                'name' => 'وزارة التربية والتعليم',
                'description' => 'الجهة المسؤولة عن التعليم الأساسي والثانوي والمدارس.',
                'contact_email' => 'Info@moed.gov.sy',
                'contact_phone' => null,
            ],
            [
                'name' => 'وزارة الأشغال العامة والإسكان',
                'description' => 'الجهة المسؤولة عن مشاريع البنية التحتية والمباني والإسكان.',
                'contact_email' => 'press@mopwh.gov.sy',
                'contact_phone' => null,
            ],
            [
                'name' => 'وزارة الإعلام',
                'description' => 'الجهة المسؤولة عن الإعلام والجهات الإعلامية الرسمية.',
                'contact_email' => null,
                'contact_phone' => '011 662 4218',
            ],
            [
                'name' => 'وزارة النقل',
                'description' => 'الجهة المسؤولة عن شبكات النقل البري والجوي والبحري.',
                'contact_email' => 'Press@mot.gov.sy',
                'contact_phone' => '011 333 0326',
            ],

            // --- المحافظات ---
            [
                'name' => 'محافظة ديرالزور',
                'description' => 'المسؤولة عن الشؤون الإدارية والخدمات المحلية في محافظة دير الزور.',
                'contact_email' => 'deiralzor@moi.gov.sy',
                'contact_phone' => null,
            ],
            [
                'name' => 'محافظة درعا',
                'description' => 'المسؤولة عن الشؤون الإدارية والخدمات المحلية في محافظة درعا.',
                'contact_email' => 'draa@moi.gov.sy',
                'contact_phone' => null,
            ],
            [
                'name' => 'محافظة ريف دمشق',
                'description' => 'المسؤولة عن الشؤون الإدارية والخدمات المحلية في محافظة ريف دمشق.',
                'contact_email' => 'rdamascus@moi.gov.sy',
                'contact_phone' => null,
            ],
            [
                'name' => 'محافظة اللاذقية',
                'description' => 'المسؤولة عن الشؤون الإدارية والخدمات المحلية في محافظة اللاذقية.',
                'contact_email' => 'Info@latakia.gov.sy',
                'contact_phone' => null,
            ],
            [
                'name' => 'محافظة طرطوس',
                'description' => 'المسؤولة عن الشؤون الإدارية والخدمات المحلية في محافظة طرطوس.',
                'contact_email' => 'Info@tartous-city.gov.sy',
                'contact_phone' => null,
            ],
            [
                'name' => 'محافظة إدلب',
                'description' => 'المسؤولة عن الشؤون الإدارية والخدمات المحلية في محافظة إدلب.',
                'contact_email' => 'idlib@moi.gov.sy',
                'contact_phone' => null,
            ],
            [
                'name' => 'محافظة حلب',
                'description' => 'المسؤولة عن الشؤون الإدارية والخدمات المحلية في محافظة حلب.',
                'contact_email' => 'media.office@aleppo.gov.sy',
                'contact_phone' => null,
            ],
            [
                'name' => 'محافظة حمص',
                'description' => 'المسؤولة عن الشؤون الإدارية والخدمات المحلية في محافظة حمص.',
                'contact_email' => 'homs@moi.gov.sy',
                'contact_phone' => null,
            ],
            [
                'name' => 'محافظة دمشق',
                'description' => 'المسؤولة عن الشؤون الإدارية والخدمات المحلية في محافظة دمشق.',
                'contact_email' => 'damascus@moi.gov.sy',
                'contact_phone' => null,
            ],
        ];

        foreach ($entities as $entity) {
            // استخدام firstOrCreate لضمان عدم تكرار إدخال الجهات
            Entity::firstOrCreate(
                ['name' => $entity['name']],
                [
                    'description' => $entity['description'],
                    'contact_email' => $entity['contact_email'],
                    'contact_phone' => $entity['contact_phone'] ?? null, // استخدام القيمة المزودة أو null إذا كانت غير موجودة
                    'is_active' => true,
                ]
            );
        }
    }
}
