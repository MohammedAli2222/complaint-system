<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // نحذف فقط جدول complaint_history
        Schema::dropIfExists('complaint_histories');
        // أو إذا كان اسمه complaint_history بدون s
        Schema::dropIfExists('complaint_history');
    }

    public function down()
    {
        // لا حاجة للـ rollback لأننا ننتقل لنظام أفضل
    }
};
