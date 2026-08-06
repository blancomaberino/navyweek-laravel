<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DuskTestCase;
use Tests\TestCase;

// NOTE: these `use` imports must stay ABOVE the pest() calls below. A `use` alias
// only affects references that appear textually after it (lexical scoping — true on
// every PHP version), so with the import below, `DuskTestCase::class` resolves to the
// global `\DuskTestCase` and Pest aborts every run with "class `DuskTestCase` was not found."
pest()->extend(DuskTestCase::class)
//  ->use(Illuminate\Foundation\Testing\DatabaseMigrations::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
| NOTE: the `use` imports above MUST stay above the first `pest()->extend()` call. PHP
| resolves a `Class::class` reference against the aliases known at that point in the file,
| so a `use` placed *after* the call leaves `DuskTestCase::class` pointing at the global
| `\DuskTestCase` (which doesn't exist) and Pest fails to collect ANY suite with
| "class DuskTestCase not found".
|
*/

pest()->extend(DuskTestCase::class)
//  ->use(Illuminate\Foundation\Testing\DatabaseMigrations::class)
    ->in('Browser');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * The CMS body of every content page, captured verbatim from the live database into
 * tests/Fixtures/body-blocks/. Shared by the block round-trip, coverage and inline-span
 * suites so the corpus has one loader and one non-empty guard: an empty glob would make
 * all three pass vacuously.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function bodyBlockCorpus(): array
{
    $files = glob(__DIR__.'/Fixtures/body-blocks/*.json');

    expect($files)->not->toBeEmpty('The body-block fixture corpus is missing.');

    $corpus = [];

    foreach ($files as $file) {
        $corpus[basename($file, '.json')] = json_decode(
            (string) file_get_contents($file),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    return $corpus;
}
