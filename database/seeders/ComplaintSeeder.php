<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Complaint;
use App\Models\User;
use App\Models\Entity;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ComplaintSeeder extends Seeder
{
    public function run()
    {
        // 1. جلب البيانات الأساسية من السيدييرز السابقة
        // نحتاج التأكد من وجود مواطنين وموظفين وجهات
        $citizens = User::role('citizen')->get();
        $employees = User::role('employee')->get();
        $entities = Entity::pluck('id');

        if ($citizens->isEmpty() || $entities->isEmpty()) {
            $this->command->warn('No citizens or entities found. Please run UserSeeder and EntitySeeder first.');
            return;
        }

        // أنواع الشكاوى المحتملة
        $types = [
            'تأخير إداري',
            'سوء معاملة',
            'فساد مالي',
            'نقص خدمات',
            'تلوث بيئي',
            'مشاكل مياه',
            'انقطاع كهرباء'
        ];

        // حالات الشكوى المحتملة
        $statuses = ['new', 'processing', 'under_review', 'done', 'rejected'];

        // 2. إنشاء شكاوى لكل مواطن
        foreach ($citizens as $citizen) {
            // كل مواطن سيقدم بين 1 إلى 3 شكاوى بشكل عشوائي
            $complaintsCount = rand(1, 3);

            for ($i = 0; $i < $complaintsCount; $i++) {

                $status = $statuses[array_rand($statuses)];
                $entityId = $entities->random();

                // تحديد بيانات الموظف (التعيين والقفل) بناءً على الحالة
                $assignedTo = null;
                $lockedBy = null;
                $lockedAt = null;

                // إذا لم تكن الشكوى جديدة، نعينها لأحد الموظفين الموجودين عشوائياً
                if ($status !== 'new' && $employees->isNotEmpty()) {
                    // في الوضع المثالي، يتم اختيار موظف تابع لنفس الجهة،
                    // لكن بما أن UserSeeder أنشأ موظفين اثنين فقط، سنختار أحدهم عشوائياً للمحاكاة
                    $randomEmployee = $employees->random();
                    $assignedTo = $randomEmployee->id;

                    // إذا كانت قيد المعالجة، يجب أن تكون مقفولة
                    if ($status === 'processing') {
                        $lockedBy = $randomEmployee->id;
                        $lockedAt = Carbon::now()->subMinutes(rand(1, 100));
                    }

                    // إذا كانت منجزة أو مرفوضة، عادة يتم فك القفل (Null) ولكن يبقى التعيين
                    // حسب منطق Repository، عند الإنجاز يتم تصفير locked_by
                }

                // إنشاء الشكوى
                Complaint::create([
                    'reference_number' => $this->generateReference(),
                    'user_id'          => $citizen->id,
                    'entity_id'        => $entityId,
                    'type'             => $types[array_rand($types)],
                    'location'         => 'منطقة سكنية - حي ' . rand(1, 50),
                    'description'      => 'أواجه مشكلة بخصوص ' . $types[array_rand($types)] . ' وأحتاج إلى حل عاجل. التفاصيل كالتالي: نعاني من هذا الأمر منذ فترة...',
                    'status'           => $status,
                    'assigned_to'      => $assignedTo,
                    'locked_by'        => $lockedBy,
                    'locked_at'        => $lockedAt,
                    'created_at'       => Carbon::now()->subDays(rand(1, 30)), // تواريخ قديمة لمحاكاة الواقع
                    'updated_at'       => Carbon::now(),
                ]);
            }
        }
    }

    // دالة مساعدة لتوليد رقم مرجعي شبيه بالموجود في الـ Repository
    private function generateReference()
    {
        return 'REF-' . Str::upper(Str::random(10));
    }
}
