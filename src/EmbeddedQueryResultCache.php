<?php

namespace SMW;

use Onoi\BlobStore\BlobStore;
use SMWQuery as Query;
use SMWQueryResult as QueryResult;

/**
 * @license GNU GPL v2+
 * @since 2.4
 *
 * @author mwjames
 */
class EmbeddedQueryResultCache {

	/**
	 * Update this version number when the serialization format
	 * changes.
	 */
	const VERSION = '0.5';

	/**
	 * @var BlobStore
	 */
	private $blobStore;

	/**
	 * @var boolean
	 */
	private $enabledState = true;

	/**
	 * @since 2.4
	 *
	 * @param BlobStore $blobStore
	 */
	public function __construct(  BlobStore $blobStore ) {
		$this->blobStore = $blobStore;
	}

	/**
	 * @since 2.4
	 *
	 * @param boolean $enabledState
	 */
	public function setEnabledState( $enabledState ) {
		$this->enabledState = (bool)$enabledState;
	}

	/**
	 * @since 2.4
	 *
	 * @return boolean
	 */
	public function isEnabled() {
		return $this->enabledState && $this->blobStore->canUse();
	}

	/**
	 * @note Called from 'SMW::Store::BeforeQueryResultLookupComplete'
	 *
	 * @since 2.4
	 *
	 * @param Store $store
	 * @param Query $query
	 * @param QueryEngine $queryEngine
	 *
	 * @return QueryResult|string
	 */
	public function fetchQueryResult( Store $store, Query $query, QueryEngine $queryEngine ) {

		if ( !$this->isEnabled() || $query->getLimit() < 1 || $query->getSubject() === null ) {
			return $queryEngine->getQueryResult( $query );
		}

		// The queryID is used without a subject to access query content with the same
		// query signature
		$queryID = md5( $query->getQueryId() . self::VERSION );
		$container = $this->blobStore->read( $queryID );

		if ( $container->has( 'results' ) ) {
			wfDebugLog( 'smw', 'Using EmbeddedQueryResultCache for ' . $queryID );

			$queryResult = new QueryResult(
				$container->get( 'printrequests' ),
				$query,
				$container->get( 'results' ),
				$store,
				$container->get( 'furtherresults' )
			);

			$queryResult->setCountValue( $container->get( 'countvalue' ) );

			return $queryResult;
		}

		$queryResult = $queryEngine->getQueryResult( $query );

		if ( !$queryResult instanceof QueryResult ) {
			return $queryResult;
		}

		$container->set( 'printrequests', $queryResult->getPrintRequests() );
		$container->set( 'results', $queryResult->getResults() );
		$container->set( 'furtherresults', $queryResult->hasFurtherResults() );
		$container->set( 'countvalue', $queryResult->getCountValue() );

		$queryResult->reset();

		$this->blobStore->save(
			$container
		);

		// We can not ensure that EmbeddedQueryDependencyLinksStore is
		// enabled and yet we still allow to use the cache and store subjects and
		// queryID's separately to make them easily discoverable and removable
		// per subject
		$container = $this->blobStore->read(
			md5( $query->getSubject()->getHash() . self::VERSION )
		);

		$container->append(
			'list',
			array( $queryID => true )
		);

		$this->blobStore->save(
			$container
		);

		return $queryResult;
	}

	/**
	 * @since 2.4
	 *
	 * @param array $queryList
	 */
	public function purgeCacheByQueryList( array $queryList ) {
		foreach ( $queryList as $queryID ) {
			$this->blobStore->delete( md5( $queryID . self::VERSION ) );
		}
	}

	/**
	 * @since 2.4
	 *
	 * @param DIWikiPage $subject
	 */
	public function purgeCacheBySubject( DIWikiPage $subject ) {

		$id = md5( $subject->getHash() . self::VERSION );
		$container = $this->blobStore->read( $id );

		if ( !$container->has( 'list' ) ) {
			return;
		}

		$list = array_keys( $container->get( 'list' ) );

		foreach ( $list as $queryID ) {
			$this->blobStore->delete( $queryID );
		}

		$this->blobStore->delete( $id );
	}

}
