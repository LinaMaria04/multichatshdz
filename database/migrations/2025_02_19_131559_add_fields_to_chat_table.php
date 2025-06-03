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
            $table->boolean('show_url_to_index')->default(false);
            $table->string('url_to_index')->nullable();
            $table->smallInteger('reindex_hours')->default(24)->nullable();
        });
    }

};
