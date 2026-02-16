<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            $table->foreign('person_id')->references('id')->on('people')->onDelete('set null');
        });

        Schema::table('people', function (Blueprint $table) {
            $table->foreign('cover_face_id')->references('id')->on('faces')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropForeign(['cover_face_id']);
        });
    }
};
