<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\Traits;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedAuthor;
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
use Illuminate\Database\Eloquent\Collection;

class CachableTest extends IntegrationTestCase
{
    use RefreshDatabase;

    public function testSpecifyingAlternateCacheDriver()
    {
        $configCacheStores = config('cache.stores');
        $configCacheStores['customCache'] = ['driver' => 'array'];
        // TODO: make sure the alternate cache is actually loaded
        config(['cache.stores' => $configCacheStores]);
        config(['laravel-model-caching.store' => 'customCache']);
        $key = sha1('genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor');
        $tags = ['genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor'];

        $authors = (new Author)
            ->all();
        $defaultcacheResults = cache()
            ->tags($tags)
            ->get($key)['value'];
        $customCacheResults = cache()
            ->store('customCache')
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->all();

        $this->assertEquals($customCacheResults, $authors);
        $this->assertNull($defaultcacheResults);
        $this->assertEmpty($liveResults->diffAssoc($customCacheResults));
    }

    public function testSetCachePrefixAttribute()
    {
        (new PrefixedAuthor)->get();

        $results = $this->
            cache()
            ->tags([
                'genealabs:laravel-model-caching:testing:test-prefix:genealabslaravelmodelcachingtestsfixturesprefixedauthor',
            ])
            ->get(sha1('genealabs:laravel-model-caching:testing:test-prefix:genealabslaravelmodelcachingtestsfixturesprefixedauthor'))['value'];

        $this->assertNotNull($results);
    }

    public function testAllReturnsCollection()
    {
        (new Author)->truncate();
        factory(Author::class, 1)->create();
        $authors = (new Author)->all();

        $cachedResults = $this
            ->cache()
            ->tags([
                'genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor',
            ])
            ->get(sha1('genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor'))['value'];
        $liveResults = (new UncachedAuthor)->all();

        $this->assertInstanceOf(Collection::class, $authors);
        $this->assertInstanceOf(Collection::class, $cachedResults);
        $this->assertInstanceOf(Collection::class, $liveResults);
    }

    public function testsCacheFlagDisablesCaching()
    {
        config(['laravel-model-caching.disabled' => true]);

        $authors = (new Author)->get();
        $cachedAuthors = $this
            ->cache()
            ->tags([
                'genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor',
            ])
            ->get(sha1('genealabs:laravel-model-caching:testing:genealabslaravelmodelcachingtestsfixturesauthor'));

        config(['laravel-model-caching.disabled' => false]);

        $this->assertNull($cachedAuthors);
        $this->assertNotEmpty($authors);
        $this->assertCount(10, $authors);
    }
}
