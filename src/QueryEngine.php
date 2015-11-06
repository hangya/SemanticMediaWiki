<?php

namespace SMW;

use SMWQuery as Query;
use SMWQueryResult as QueryResult;

/**
 * @license GNU GPL v2+
 * @since 2.4
 *
 * @author mwjames
 */
interface QueryEngine {

	/**
	 * Execute query for the provided query object and returns a list of results
	 * as nn QueryResult. If the request was made for a debug (querymode MODE_DEBUG)
	 * query then s simple wiki and HTML-compatible string is returned.
	 *
	 * @since 2.4
	 *
	 * @param Query $query
	 *
	 * @return QueryResult|string
	 */
	public function getQueryResult( Query $query );

}
