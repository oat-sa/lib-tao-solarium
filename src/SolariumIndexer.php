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

use common_Logger;
use oat\tao\model\search\Search;
use oat\tao\model\search\Index;
use Solarium\Client;
use Solarium\QueryType\Update\Query\Document\DocumentInterface;

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
class SolariumIndexer
{
    const INDEXING_BLOCK_SIZE = 100;
    
    private $client = null;
    
    private $resources = null;
    
    private $indexMap = array();
    
    private $propertyCache = array();
    
    
    public function __construct(Client $client, \Traversable $resourceTraversable)
    {
        $this->client = $client;
        $this->resources = $resourceTraversable;
    }

    public function run() {
    
        $count = 0;
    
        // flush existing index
        $update = $this->client->createUpdate();
        $update->addDeleteQuery('*:*');
        $result = $this->client->update($update);
    
        while ($this->resources->valid()) {
            $update = $this->client->createUpdate();
            $blockSize = 0;
            while ($this->resources->valid() && $blockSize < self::INDEXING_BLOCK_SIZE) {
                $document = $this->createDocument($update, $this->resources->current());
                $update->addDocuments(array($document));
                $blockSize++;
                $this->resources->next();
            }
            $result = $this->client->update($update);
            $count += $blockSize;
        }
    
        $update = $this->client->createUpdate();
        $update->addCommit();
        $update->addOptimize();
        $result = $this->client->update($update);
    
        return $count;
    }
    
    protected function createDocument($update, \core_kernel_classes_Resource $resource) {
        $document = new SolariumDocument($update, $resource);
        foreach ($this->getProperties($resource) as $property) {
            $indexes = $this->getIndexes($property);
            if (!empty($indexes)) {
                $values = $resource->getPropertyValues($property);
                foreach ($indexes as $index) {
                    $strings = $index->tokenize($values);
                    $document->add($index, $strings);
                }
            }
        }
        return $document->getDocument();
    }
    
    protected function getProperties(\core_kernel_classes_Resource $resource) {
        $classProperties = array(new \core_kernel_classes_Property(RDFS_LABEL));
        foreach ($resource->getTypes() as $type) {
            $classProperties = array_merge($classProperties, $this->getPropertiesByClass($type));
        }
        
        return $classProperties;
    }
    
    protected function getPropertiesByClass(\core_kernel_classes_Class $type) {
        if (!isset($this->propertyCache[$type->getUri()])) {
            $this->propertyCache[$type->getUri()] = \tao_helpers_form_GenerisFormFactory::getClassProperties($type);
        }
        return $this->propertyCache[$type->getUri()];
    }
    
    protected function getIndexes(\core_kernel_classes_Property $property) {
        if (!isset($this->indexMap[$property->getUri()])) {
            $this->indexMap[$property->getUri()] = array();
            $indexes = $property->getPropertyValues(new \core_kernel_classes_Property('http://www.tao.lu/Ontologies/TAO.rdf#PropertyIndex'));
            foreach ($indexes as $indexUri) {
                $this->indexMap[$property->getUri()][] = new SolrIndex($indexUri);
            }
        }
        return $this->indexMap[$property->getUri()];
    }
    
    public function getUsedIndexes() {
        $usedIndexes = array();
        foreach ($this->indexMap as $indexes) {
            foreach ($indexes as $index) {
                $usedIndexes[] = $index;
            }
        }
        return $usedIndexes;
    }
}