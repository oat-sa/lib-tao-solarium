<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2014 (original work) Open Assessment Technologies SA;
 *
 *
 */
namespace oat\tao\solarium;

use oat\tao\model\search\Search;
use common_Logger;
use Solarium\Client;
use oat\oatbox\Configurable;

/**
 * Solarium Search implementation
 * 
 * Sample config
 * 
 *  $config = array(
 *      'endpoint' => array(
 *          'localhost' => array(
 *              'host' => '127.0.0.1',
 *             'port' => 8983,
 *             'path' => '/solr/',
 *          )
 *      )
 *  );
 * 
 * @author Joel Bout <joel@taotesting.com>
 */
class SolariumSearch extends Configurable implements Search
{
    const INDEXING_BLOCK_SIZE = 100;
    
    /**
     * 
     * @var \Solarium\Client
     */
    private $client;
    
    /**
     * 
     * @return \Solarium\Client
     */
    protected function getClient() {
        if (is_null($this->client)) {
            $this->client = new \Solarium\Client($this->getOptions());
        }
        return $this->client;
    }
    
    /**
     * (non-PHPdoc)
     * @see \oat\tao\model\search\Search::query()
     */
    public function query($queryString) {
        
        $query = $this->getClient()->createQuery(\Solarium\Client::QUERY_SELECT);
        
        // set a query (all prices starting from 12)
        $query->setQuery($queryString);
        
        
        // this executes the query and returns the result
        $resultset = $this->getClient()->execute($query);
        
        $uris = array();
        foreach ($resultset as $document) {
            $uris[] = $document->uri;
            //.' : '.implode(',',$document->label);
        }
        
        return $uris;
    }
    
    public function index(\Traversable $resourceTraversable) {
    
        $count = 0;
    
        // flush existing index
        $update = $this->getClient()->createUpdate();
        $update->addDeleteQuery('*:*');
        $result = $this->getClient()->update($update);
        
        while ($resourceTraversable->valid()) {
            $update = $this->getClient()->createUpdate();
            $blockSize = 0;
            while ($resourceTraversable->valid() && $blockSize < self::INDEXING_BLOCK_SIZE) {
                $indexer = new SolariumIndexer($resourceTraversable->current());
                $update->addDocuments(array($indexer->toDocument($update)));
                $blockSize++;
                $resourceTraversable->next();
            }
            $result = $this->getClient()->update($update);
            $count += $blockSize;
        }
        
        $update = $this->getClient()->createUpdate();
        $update->addCommit();
        $update->addOptimize();
        $result = $this->getClient()->update($update);
        
        
        return $count;
    }

}