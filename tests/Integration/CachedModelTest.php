<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Profile;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedProfile;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPublisher;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedStore;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CachedModelTest extends IntegrationTestCase
{
    use RefreshDatabase;

    public function testAllModelResultsCreatesCache()
    {
        $authors = (new Author)->all();
        $key = sha1('genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor');
        $tags = [
            'genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor',
        ];

        $cachedResults = $this
            ->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->all();

        $this->assertEquals($authors, $cachedResults);
        $this->assertEmpty($liveResults->diffAssoc($cachedResults));
    }

    public function testScopeDisablesCaching()
    {
        $key = sha1('genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor');
        $tags = ['genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor'];
        $authors = (new Author)
            ->where("name", "Bruno")
            ->disableCache()
            ->get();

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];

        $this->assertNull($cachedResults);
        $this->assertNotEquals($authors, $cachedResults);
    }

    public function testScopeDisablesCachingWhenCalledOnModel()
    {
        $key = sha1('genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor');
        $tags = ['genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor'];
        $authors = (new PrefixedAuthor)
            ->disableCache()
            ->where("name", "Bruno")
            ->get();

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];

        $this->assertNull($cachedResults);
        $this->assertNotEquals($authors, $cachedResults);
    }

    public function testScopeDisableCacheDoesntCrashWhenCachingIsDisabledInConfig()
    {
        config(['laravel-model-caching.disabled' => true]);
        $key = sha1('genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor');
        $tags = ['genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor'];
        $authors = (new PrefixedAuthor)
            ->where("name", "Bruno")
            ->disableCache()
            ->get();

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];

        $this->assertNull($cachedResults);
        $this->assertNotEquals($authors, $cachedResults);
    }

    public function testAllMethodCachingCanBeDisabledViaConfig()
    {
        config(['laravel-model-caching.disabled' => true]);
        $authors = (new Author)
            ->all();
        $key = sha1('genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor');
        $tags = [
            'genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor',
        ];
        config(['laravel-model-caching.disabled' => false]);

        $cachedResults = $this
            ->cache()
            ->tags($tags)
            ->get($key)['value'];

        $this->assertEmpty($cachedResults);
        $this->assertNotEmpty($authors);
        $this->assertCount(10, $authors);
    }

    public function testWhereHasIsBeingCached()
    {
        $books = (new Book)
            ->with('author')
            ->whereHas('author', function ($query) {
                $query->whereId('1');
            })
            ->get();

        $key = sha1('genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesbook_exists_and_books.author_id_=_authors.id-id_=_1-author');
        $tags = [
            'genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesbook',
            'genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor',
        ];

        $cachedResults = $this
            ->cache()
            ->tags($tags)
            ->get($key)['value'];

        $this->assertEquals(1, $books->first()->author->id);
        $this->assertEquals(1, $cachedResults->first()->author->id);
    }

    public function testWhereHasWithClosureIsBeingCached()
    {
        $books1 = (new Book)
            ->with('author')
            ->whereHas('author', function ($query) {
                $query->whereId(1);
            })
            ->get()
            ->keyBy('id');
        $books2 = (new Book)
            ->with('author')
            ->whereHas('author', function ($query) {
                $query->whereId(2);
            })
            ->get()
            ->keyBy('id');

        $this->assertNotEmpty($books1->diffKeys($books2));
    }

    public function testModelCacheDoesntInvalidateDuringCooldownPeriod()
    {
        $authors = (new Author)
            ->withCacheCooldownSeconds(1)
            ->get();

        factory(Author::class, 1)->create();
        $authorsDuringCooldown = (new Author)
            ->get();
        $uncachedAuthors = (new UncachedAuthor)
            ->get();
        sleep(2);
        $authorsAfterCooldown = (new Author)
            ->get();

        $this->assertCount(10, $authors);
        $this->assertCount(10, $authorsDuringCooldown);
        $this->assertCount(11, $uncachedAuthors);
        $this->assertCount(11, $authorsAfterCooldown);
    }

    public function testModelCacheDoesInvalidateWhenNoCooldownPeriod()
    {
        $authors = (new Author)
            ->get();

        factory(Author::class, 1)->create();
        $authorsAfterCreate = (new Author)
            ->get();
        $uncachedAuthors = (new UncachedAuthor)
            ->get();

        $this->assertCount(10, $authors);
        $this->assertCount(11, $authorsAfterCreate);
        $this->assertCount(11, $uncachedAuthors);
    }
}
