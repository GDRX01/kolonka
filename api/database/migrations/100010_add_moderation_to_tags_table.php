<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            // Tags suggested by ordinary users wait for the administrator's approval:
            //   approved - visible to everybody (existing tags stay approved)
            //   pending  - visible only to the author of the post and to administrators
            $table->string('status', 20)->default('approved')->after('slug');
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
