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
        //common_Logger::i('indexing '.$this->resource->getLabel());
        
        $document->uri = $this->resource->getUri();
        
//        $this->indexTypes($document);
        foreach ($this->getIndexedProperties() as $property) {
            $this->indexProperty($document, $property);
        }
        
        return $document;
    }
    
    protected function indexProperty(DocumentInterface $document, \core_kernel_classes_Property $property)
    {
        //\common_Logger::d('property '.$property->getLabel());
    
        switch ($property->getUri()) {
        	case RDFS_LABEL:
        	    // if label: tokenize, store
        	    $document->label = $this->resource->getLabel();
        	    break;
    
        	case 'http://www.tao.lu/Ontologies/TAOItem.rdf#ItemModel':
        	case 'http://myfantasy.domain/my_tao30.rdf#i1415962196740059':
        	    $this->indexKeyword($document, $property);
        	    break;
        	case 'http://www.tao.lu/Ontologies/TAOItem.rdf#ItemContent' :
        	    /*
        	    $content = \taoItems_models_classes_ItemsService::singleton()->getItemContent($this->resource);
        	    if (!empty($content)) {
    
        	        //if itemcontent: tokenize, nostore, complex data retrieval
        	        // @todo
        	    }
        	    */
        	    break;
        	
        	// blacklist
        	case 'http://www.tao.lu/Ontologies/TAO.rdf#Lock' :
        	case 'http://www.tao.lu/Ontologies/TAOTest.rdf#TestContent' :
        	    break;
        	default :
    	       $this->indexUnknown($document, $property);
        }
    }
    
    protected function indexUnknown(DocumentInterface $document, \core_kernel_classes_Property $property)
    {
        $range = $property->getRange();
        if ($range->getUri() == RDFS_LITERAL) {
            \common_Logger::d('index '.$property->getLabel().' as literal');
        } else {
            \common_Logger::d('index '.$property->getUri().' as keyword');
        }
    }
    
    protected function indexText(DocumentInterface $document, \core_kernel_classes_Property $property)
    {
        $val = array();
        foreach ($this->resource->getPropertyValues($property) as $value) {
            if (!empty($value)) {
                $valres = new \core_kernel_classes_Resource($value);
                $val[] = $valres->getLabel();
            }
        }
        $document->type_txt = $val;
    }
    
    protected function indexKeyword(DocumentInterface $document, \core_kernel_classes_Property $property)
    {
        $val = array();
        foreach ($this->resource->getPropertyValues($property) as $value) {
            if (!empty($value)) {
                $valres = new \core_kernel_classes_Resource($value);
                $val[] = $valres->getLabel();
            }
        }
        $document->type_ss = $val;
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