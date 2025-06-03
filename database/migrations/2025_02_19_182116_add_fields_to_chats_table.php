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
        Schema::table('chats', function (Blueprint $table) {
            $table->string('fetch_url')->nullable()->after('header');
            $table->string('fetch_periodicity')->nullable()->after('fetch_url');
            $table->timestamp('last_fetch_execution')->nullable()->after('fetch_periodicity');

            $table->dropColumn('url_to_index');
            $table->dropColumn('reindex_hours');
        });
    }
};
