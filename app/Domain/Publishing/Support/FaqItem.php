<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Support;

use App\Domain\Publishing\Seo\BuildsSeoSchema;

/**
 * A computed, non-persisted FAQ pair for pages whose FAQs are derived from live data
 * (rather than seeded on the polymorphic `faqs` table) — e.g. the Veterans Day
 * free-meals roundup, whose answers interpolate the live verified-offer stats.
 * Satisfies the `object{question: string, answer: string}` shape that
 * {@see BuildsSeoSchema::faqPageFrom()} consumes.
 */
final readonly class FaqItem
{
    public function __construct(
        public string $question,
        public string $answer,
    ) {}
}
