<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared, polymorphic FAQ store. A `faq` (question + answer) is attached via
 * `faqable` to a Page or Offer (later bases/ranks/…). The discount-page FAQPage
 * JSON-LD is built from these rows, and the hard schema↔content parity gate is
 * enforced against them — one source for both the rendered FAQ and its schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            // faqable_type + faqable_id (+ composite index) — Page/Offer/pillars.
            $table->morphs('faqable');
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
