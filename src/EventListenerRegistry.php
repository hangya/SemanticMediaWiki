<?php

namespace SMW;

use Onoi\EventDispatcher\EventListenerCollection;
use Onoi\EventDispatcher\EventDispatcherFactory;
use SMWExporter as Exporter;
use SMW\Query\QueryComparator;
use SMW\DIWikiPage;

/**
 * @license GNU GPL v2+
 * @since 2.2
 *
 * @author mwjames
 */
class EventListenerRegistry implements EventListenerCollection {

	/**
	 * @var EventListenerCollection
	 */
	private $eventListenerCollection = null;

	/**
	 * @since 2.2
	 *
	 * @param EventListenerCollection $eventListenerCollection
	 */
	public function __construct( EventListenerCollection $eventListenerCollection ) {
		$this->eventListenerCollection = $eventListenerCollection;
	}

	/**
	 * @see  EventListenerCollection::getCollection
	 *
	 * @since 2.2
	 */
	public function getCollection() {
		return $this->addListenersToCollection()->getCollection();
	}

	private function addListenersToCollection() {

		$applicationFactory = ApplicationFactory::getInstance();

		$this->eventListenerCollection->registerCallback(
			'factbox.cache.delete', function( $dispatchContext ) use ( $applicationFactory ) {

				$title = $dispatchContext->get( 'title' );
				$cache = $applicationFactory->getCache();

				$cache->delete(
					$applicationFactory->newCacheFactory()->getFactboxCacheKey( $title->getArticleID() )
				);
			}
		);

		/**
		 * Listen to when an ArticlePurge event is emitted
		 */
		$this->eventListenerCollection->registerCallback(
			'cache.delete.on.article.purge', function( $dispatchContext ) use ( $applicationFactory ) {

				$title = $dispatchContext->get( 'title' );
				$cache = $applicationFactory->getCache();
				$cacheFactory = $applicationFactory->newCacheFactory();

				if ( $applicationFactory->getSettings()->get( 'smwgFactboxCacheRefreshOnPurge' ) ) {
					$cache->delete(
						$cacheFactory->getFactboxCacheKey( $title->getArticleID() )
					);
				}

				if ( $applicationFactory->getSettings()->get( 'smwgEmbeddedQueryResultCacheRefreshOnPurge' ) ) {

					$embeddedQueryResultCache = $applicationFactory->newCacheFactory()->newEmbeddedQueryResultCache(
						$applicationFactory->getSettings()->get( 'smwgEmbeddedQueryResultCacheType' ),
						$applicationFactory->getSettings()->get( 'smwgEmbeddedQueryResultCacheLifetime' )
					);

					$embeddedQueryResultCache->purgeCacheBySubject( DIWikiPage::newFromTitle( $title ) );
				}
			}
		);

		$this->eventListenerCollection->registerCallback(
			'exporter.reset', function() {
				Exporter::getInstance()->clear();
			}
		);

		$this->eventListenerCollection->registerCallback(
			'query.comparator.reset', function() {
				QueryComparator::getInstance()->clear();
			}
		);

		$this->eventListenerCollection->registerCallback(
			'property.spec.change', function( $dispatchContext ) {

				$subject = $dispatchContext->get( 'subject' );

				$updateDispatcherJob = ApplicationFactory::getInstance()->newJobFactory()->newUpdateDispatcherJob(
					$subject->getTitle()
				);

				$updateDispatcherJob->run();

				Exporter::getInstance()->resetCacheFor( $subject );

				$dispatchContext->set( 'propagationstop', true );
			}
		);

		return $this->eventListenerCollection;
	}

}
