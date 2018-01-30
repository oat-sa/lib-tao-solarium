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
use oat\generis\model\OntologyRdfs;
use oat\tao\model\TaoOntology;
/**
 * Solarium Document Index
 * 
 * @author Joel Bout <joel@taotesting.com>
 */
class SolariumDocument
{
    private $resource;
    
    private $document;
    
    public function __construct(\Solarium\QueryType\Update\Query\Query $update, \core_kernel_classes_Resource $resource)
    {
        $this->resource = $resource;
        $this->document = $update->createDocument();
        
        $this->indexUri();
        $this->indexTypes();
    }

    public function add($indexName, $values) {
        $this->document->$indexName = $values;
    }
    
    public function getDocument() {
        return $this->document;
    }
    
    public function indexUri() {
        $this->document->uri = $this->resource->getUri();
    }
     
    public function indexTypes() {
        
        $toDo = array();
        foreach ($this->resource->getTypes() as $class) {
            $toDo[] = $class->getUri();
//            $document->addField(Document\Field::Text('class', $class->getLabel()));
        }
        
        $done = array(OntologyRdfs::RDFS_RESOURCE, TaoOntology::OBJECT_CLASS_URI);
        $toDo = array_diff($toDo, $done);
        
        $classes = array();
        while (!empty($toDo)) {
            $class = new \core_kernel_classes_Class(array_pop($toDo));
            $classes[] = $class->getUri();
            foreach ($class->getParentClasses() as $parent) {
                if (!in_array($parent->getUri(), $done)) {
                    $toDo[] = $parent->getUri();
                }
            }
            $done[] = $class->getUri();
        }
        
        $this->document->type_r = $classes; 
    }
    

}