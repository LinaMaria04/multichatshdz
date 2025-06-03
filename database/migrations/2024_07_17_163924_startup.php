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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->string('image')->nullable();
            $table->enum('gender', ['hombre', 'mujer'])->default('mujer');
            $table->timestamps();
        });

        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->string('code')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('assistant_id')->nullable();
            $table->string('vectorstore_id')->nullable();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained();
            $table->string('file_id')->nullable();
            $table->string('filename');
            $table->text('description')->nullable();
            $table->timestamps();
        });

    }

};
