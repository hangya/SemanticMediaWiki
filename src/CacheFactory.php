<?php

namespace SMW;

use Onoi\Cache\CacheFactory as OnoiCacheFactory;
use Onoi\BlobStore\BlobStore;
use SMW\ApplicationFactory;
use ObjectCache;
use RuntimeException;

/**
 * @license GNU GPL v2+
 * @since 2.2
 *
 * @author mwjames
 */
class CacheFactory {

	/**
	 * @var string|integer
	 */
	private $mainCacheType;

	/**
	 * @since 2.2
	 *
	 * @param string|integer $mainCacheType
	 */
	public function __construct( $mainCacheType ) {
		$this->mainCacheType = $mainCacheType;
	}

	/**
	 * @since 2.2
	 *
	 * @return string|integer
	 */
	public function getMainCacheType() {
		return $this->mainCacheType;
	}

	/**
	 * @since 2.2
	 *
	 * @return string
	 */
	public function getCachePrefix() {
		return $GLOBALS['wgCachePrefix'] === false ? wfWikiID() : $GLOBALS['wgCachePrefix'];
	}

	/**
	 * @since 2.2
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	public function getFactboxCacheKey( $key ) {
		return $this->getCachePrefix() . ':smw:fc:' . md5( $key );
	}

	/**
	 * @since 2.2
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	public function getPurgeCacheKey( $key ) {
		return $this->getCachePrefix() . ':smw:arc:' . md5( $key );
	}

	/**
	 * @since 2.2
	 *
	 * @param array $cacheOptions
	 *
	 * @return stdClass
	 * @throws RuntimeException
	 */
	public function newCacheOptions( array $cacheOptions ) {

		if ( !isset( $cacheOptions['useCache'] ) || !isset( $cacheOptions['ttl'] ) ) {
			throw new RuntimeException( "Cache options is missing a useCache/ttl parameter" );
		}

		return (object)$cacheOptions;
	}

	/**
	 * @since 2.2
	 *
	 * @param integer $cacheSize
	 *
	 * @return Cache
	 */
	public function newFixedInMemoryCache( $cacheSize = 500 ) {
		return OnoiCacheFactory::getInstance()->newFixedInMemoryLruCache( $cacheSize );
	}

	/**
	 * @since 2.2
	 *
	 * @return Cache
	 */
	public function newNullCache() {
		return OnoiCacheFactory::getInstance()->newNullCache();
	}

	/**
	 * @since 2.2
	 *
	 * @param integer|string $mediaWikiCacheType
	 *
	 * @return Cache
	 */
	public function newMediaWikiCompositeCache( $mediaWikiCacheType = null ) {

		$mediaWikiCache = ObjectCache::getInstance(
			( $mediaWikiCacheType === null ? $this->getMainCacheType() : $mediaWikiCacheType )
		);

		$compositeCache = OnoiCacheFactory::getInstance()->newCompositeCache( array(
			$this->newFixedInMemoryCache( 500 ),
			OnoiCacheFactory::getInstance()->newMediaWikiCache( $mediaWikiCache )
		) );

		return $compositeCache;
	}

	/**
	 * @since 2.3
	 *
	 * @param integer|string $mbeddedQueryResultCacheType
	 * @param integer $embeddedQueryResultCacheLifetime
	 *
	 * @return BlobStore
	 */
	public function newEmbeddedQueryResultBlobstore( $embeddedQueryResultCacheType = null, $embeddedQueryResultCacheLifetime = 3600 ) {

		$blobStore = new BlobStore(
			'smw:qrc:store',
			$this->newMediaWikiCompositeCache( $embeddedQueryResultCacheType )
		);

		// If CACHE_NONE is selected, disable the usage
		$blobStore->setUsageState(
			$embeddedQueryResultCacheType !== CACHE_NONE
		);

		$blobStore->setExpiryInSeconds(
			$embeddedQueryResultCacheLifetime
		);

		$blobStore->setNamespacePrefix(
			$this->getCachePrefix()
		);

		return $blobStore;
	}

	/**
	 * @since 2.4
	 *
	 * @return EmbeddedQueryResultCache
	 */
	public function newEmbeddedQueryResultCache( $embeddedQueryResultCacheType = null, $embeddedQueryResultCacheLifetime = 3600 ) {

		$embeddedQueryResultBlobstore = $this->newEmbeddedQueryResultBlobstore(
			$embeddedQueryResultCacheType,
			$embeddedQueryResultCacheLifetime
		);

		$embeddedQueryResultCache = new EmbeddedQueryResultCache(
			$embeddedQueryResultBlobstore
		);

		return $embeddedQueryResultCache;
	}

	/**
	 * @since 2.3
	 *
	 * @param integer|string $mediaWikiCacheType
	 * @param integer $valueLookupCacheLifetime
	 *
	 * @return BlobStore
	 */
	public function newValueLookupBlobstore( $valueLookupCacheType = null, $valueLookupCacheLifetime = 3600 ) {

		$blobStore = new BlobStore(
			'smw:vl:store',
			$this->newMediaWikiCompositeCache( $valueLookupCacheType )
		);

		// If CACHE_NONE is selected, disable the usage
		$blobStore->setUsageState(
			$valueLookupCacheType !== CACHE_NONE
		);

		$blobStore->setExpiryInSeconds(
			$valueLookupCacheLifetime
		);

		$blobStore->setNamespacePrefix(
			$this->getCachePrefix()
		);

		return $blobStore;
	}

}
