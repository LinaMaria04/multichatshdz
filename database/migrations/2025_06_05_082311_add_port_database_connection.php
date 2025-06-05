<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('_database_connection', function (Blueprint $table){
            $table->string('port')->after('ip_host');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('_database_connection', function (Blueprint $table){
            $table->dropColumn('port');
        });
    }
};
