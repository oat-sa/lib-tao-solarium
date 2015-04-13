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
use oat\tao\model\search\SyntaxException;
use Solarium\Exception\HttpException;

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
    const SUBSTITUTION_CONFIG_KEY = 'solr_search_map';
    
    /**
     * 
     * @var \Solarium\Client
     */
    private $client;
    
    private $substitutes = null;
    
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
    public function query($queryString, $rootClass = null) {
        $parts = explode(' ', $queryString);
        foreach ($parts as $key => $part) {
            
            $matches = array();
            if (preg_match('/^([^a-z]*)([a-z\-_]+):(.*)/', $part, $matches) === 1) {
                list($fullstring, $prefix, $fieldname, $value) = $matches;
                $sub = $this->getIndexSubstitutions();
                if (isset($sub[$fieldname])) {
                    $parts[$key] = $prefix.$sub[$fieldname].':'.$value;
                }
            }
        }
        $queryString = implode(' ', $parts);
        if (!is_null($rootClass)) {
            $queryString = $queryString.' AND type_r:'.str_replace(':', '\\:', $rootClass->getUri());
        }
        
        try {
            $query = $this->getClient()->createQuery(\Solarium\Client::QUERY_SELECT);
            $query->setQuery($queryString)->setRows(100);
            
            // this executes the query and returns the result
            $resultset = $this->getClient()->execute($query);
        } catch (HttpException $e) {
            switch ($e->getCode()) {
            	case 400 :
            	    $json = json_decode($e->getBody(), true);
            	    throw new SyntaxException($queryString, __('There is an error in your search query, system returned: %s', $json['error']['msg']));
            	default :
            	    throw new SyntaxException($queryString, __('An unknown error occured during search'));
            }
            
        }
        
        $uris = array();
        foreach ($resultset as $document) {
            $uris[] = $document->uri;
            //.' : '.implode(',',$document->label);
        }
        
        return $uris;
    }
    
    public function index(\Traversable $resourceTraversable) {
    
        $indexer = new SolariumIndexer($this->getClient(), $resourceTraversable);
        $count = $indexer->run();
        
        // generate index substitution map
        
        $map = array();
        foreach ($indexer->getUsedIndexes() as $index) {
            $map[$index->getIdentifier()] = $index->getSolrId();
        }
        $this->setIndexSubstitutions($map);
            
        return $count;
    }

    public function setIndexSubstitutions($map) {
        $ext = \common_ext_ExtensionsManager::singleton()->getExtensionById('tao');
        $ext->setConfig(self::SUBSTITUTION_CONFIG_KEY, $map);
        $this->substitutes = $map;
    }

    public function getIndexSubstitutions() {
        if (is_null($this->substitutes)) {
            $this->substitutes = \common_ext_ExtensionsManager::singleton()->getExtensionById('tao')->getConfig(self::SUBSTITUTION_CONFIG_KEY);
        }
        return $this->substitutes;
    }
    
}