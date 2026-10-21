<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('path')->comment('optimised main file on the public disk');
            $table->string('original_path')->nullable()->comment('untouched upload on the private disk');
            $table->string('filename')->index();
            $table->string('original_name');
            $table->string('title')->nullable();
            $table->string('alt')->nullable();
            $table->string('caption')->nullable();
            $table->string('mime', 100)->index();
            $table->string('extension', 10);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedBigInteger('original_size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('original_width')->nullable();
            $table->unsignedInteger('original_height')->nullable();
            $table->json('edits')->nullable()->comment('crop/rotate applied to the original');
            $table->json('optimization')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('format', 10);
            $table->string('path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
            $table->unique(['media_id', 'name', 'format']);
        });

        Schema::create('media_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->string('usable_type', 120);
            $table->unsignedBigInteger('usable_id');
            $table->string('field', 191);
            $table->timestamps();
            $table->index(['usable_type', 'usable_id']);
            $table->unique(['media_id', 'usable_type', 'usable_id', 'field'], 'media_usage_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('media');
        Schema::dropIfExists('media_folders');
    }
};
