<?php

namespace App\Traits;

trait TranslatesComplaintStatus
{
    private function translateStatus($status): string
    {
        return match ($status) {
            'new'           => 'جديدة',
            'processing'    => 'قيد المعالجة',
            'under_review'  => 'قيد المراجعة - بانتظار ردك',
            'done'          => 'منجزة',
            'rejected'      => 'مرفوضة',
            default         => 'غير معروف',
        };
    }
}
