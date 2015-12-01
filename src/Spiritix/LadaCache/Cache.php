<?php
/**
 * This file is part of the spiritix/lada-cache package.
 *
 * @copyright Copyright (c) Matthias Isler <mi@matthias-isler.ch>
 * @license   MIT
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiritix\LadaCache;

use Spiritix\LadaCache\Reflector\QueryBuilder as QueryBuilderReflector;
use Illuminate\Support\Facades\Redis;

/**
 * Cache is responsible for storing data in cache and providing cached data.
 *
 * @package Spiritix\LadaCache
 * @author  Matthias Isler <mi@matthias-isler.ch>
 */
class Cache
{
    /**
     * Reflector instance.
     *
     * @var QueryBuilderReflector
     */
    protected $reflector;

    /**
     * Initialize cache instance.
     *
     * @param QueryBuilderReflector $reflector
     */
    public function __construct(QueryBuilderReflector $reflector)
    {
        $this->reflector = $reflector;
    }

    /**
     * Check if cached version of the query result is available.
     *
     * Will always return false if cache has been disabled in config.
     *
     * @return bool
     */
    public function has()
    {
        $active = (bool) config('lada-cache.active');

        if ($active === false) {
            return false;
        }

        $hash = $this->reflector->getHash();

        return Redis::exists($hash);
    }

    /**
     * Store result of a query in cache.
     *
     * This method does not check if the target query has already been cached.
     * Use has() to prevent this issue.
     *
     * @param array $data Query result
     */
    public function set(array $data)
    {
        $hash = $this->reflector->getHash();
        $tags = $this->reflector->getTags();

        // Store data in cache
        Redis::set($hash, $this->encodeData($data));

        // Add cache key to all tag sets
        // Thanks to this we can easily invalidate the data by tag afterwards
        foreach ($tags as $tag) {
            Redis::sadd($tag, [$hash]);
        }
    }

    /**
     * Returns result of a cached query.
     *
     * This method does not check if the query has been cached before, may return unexpected values if not.
     * Use has() to prevent this issue.
     *
     * @return array
     */
    public function get()
    {
        $hash = $this->reflector->getHash();
        $encoded = Redis::get($hash);

        return $this->decodeData($encoded);
    }

    /**
     * Encodes data in order to be stored as Redis string.
     *
     * @param array $data Decoded data
     *
     * @return string
     */
    protected function encodeData(array $data)
    {
        return json_encode($data);
    }

    /**
     * Decodes data from Redis to array.
     *
     * @param string $data Decoded data
     *
     * @return array
     */
    protected function decodeData($data)
    {
        return json_decode($data);
    }
}