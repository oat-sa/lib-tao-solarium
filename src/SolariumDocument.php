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

use oat\tao\model\search\index\IndexDocument;

/**
 * Solarium Document Index
 * 
 * @author Joel Bout <joel@taotesting.com>
 */
class SolariumDocument
{
    /**
     * @var IndexDocument
     */
    private $document;

    /**
     * @var \Solarium\QueryType\Update\Query\Document\DocumentInterface
     */
    private $solrDocument;

    /**
     * SolariumDocument constructor.
     * @param \Solarium\QueryType\Update\Query\Query $update
     * @param IndexDocument $document
     */
    public function __construct(\Solarium\QueryType\Update\Query\Query $update, IndexDocument $document)
    {
        $this->document = $document;
        $this->solrDocument = $update->createDocument();
        
        $this->indexUri();
    }

    /**
     * @param $indexName
     * @param $values
     */
    public function add($indexName, $values) {
        $this->solrDocument->$indexName = $values;
    }

    /**
     * @return IndexDocument
     */
    public function getDocument() {
        return $this->document;
    }

    /**
     * @return \Solarium\QueryType\Update\Query\Document\DocumentInterface
     */
    public function getSolrDocument() {
        return $this->solrDocument;
    }

    public function indexUri() {
        $this->solrDocument->uri = $this->document->getId();
    }
}
