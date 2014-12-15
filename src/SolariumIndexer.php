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
    private $resource;
    
    public function __construct(\core_kernel_classes_Resource $resource)
    {
        $this->resource = $resource;
    }
    
    public function toDocument(\Solarium\QueryType\Update\Query\Query $update)
    {
        $document = $update->createDocument();
//        common_Logger::i('indexing '.$this->resource->getLabel());
        
        $document->uri = $this->resource->getUri();
        
//        $this->indexTypes($document);
        foreach ($this->getIndexedProperties() as $property) {
            $this->indexProperty($document, $property);
        }
        
        return $document;
    }
    
    protected function indexProperty(DocumentInterface $document, \core_kernel_classes_Property $property)
    {
        $indexes = $property->getPropertyValues(new \core_kernel_classes_Property('http://www.tao.lu/Ontologies/TAO.rdf#PropertyIndex'));
        foreach ($indexes as $indexUri) {
            $index = new Index($indexUri);
            $id = $index->getIdentifier();
            $strings = $index->tokenize($this->resource->getPropertyValues($property));
    
            if (!empty($strings)) {
                if ($index->isFuzzyMatching()) {
                    $this->indexText($document, $index, $strings);
                } else {
                    $this->indexKeyword($document, $index, $strings);
                }
            } else {
//                common_Logger::d('no tokens for '.$index->getLabel());
            }
        }
    }
    
    protected function indexText(DocumentInterface $document, Index $index, $values)
    {
//        common_Logger::d('indexed '.$index->getLabel().' as text ('.count($values).')');
        $indexName = $index->getIdentifier().'_txt';
        $document->$indexName = implode(' ', $values);
    }
    
    protected function indexKeyword(DocumentInterface $document, Index $index, $values)
    {
//        common_Logger::d('indexed '.$index->getLabel().' as keyword ('.count($values).')');
        $indexName = $index->getIdentifier().'_ss';
        $document->$indexName = $values;
    }
    
    protected function getIndexedProperties()
    {
        $classProperties = array(new \core_kernel_classes_Property(RDFS_LABEL));
        foreach ($this->resource->getTypes() as $type) {
            $classProperties = array_merge($classProperties, \tao_helpers_form_GenerisFormFactory::getClassProperties($type));
        }
    
        return $classProperties;
    }
}