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
use oat\tao\model\search\SearchTokenGenerator;

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
    /**
     * @var SearchTokenGenerator
     */
    private $tokenGenerator = null;
    
    private $map = array();
    
    public function __construct(Client $client, \Traversable $resourceTraversable)
    {
        $this->client = $client;
        $this->resources = $resourceTraversable;
        $this->tokenGenerator = new SearchTokenGenerator();
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
        foreach ($this->tokenGenerator->generateTokens($resource) as $data) {
            list($index, $strings) = $data;
            $document->add($this->getSolrId($index), $strings);
        }
        return $document->getDocument();
    }
    
    public function getSolrId(Index $index) {
        if (!isset($this->map[$index->getIdentifier()])) {
            $suffix = $index->isFuzzyMatching() ? '_t' : '_s';
            if ($index->isDefaultSearchable()) {
                $suffix .= '_d';
            }
            $this->map[$index->getIdentifier()] = $index->getIdentifier().$suffix;
        }
        return $this->map[$index->getIdentifier()];
    }
    
    public function getIndexMap() {
        return $this->map;
    }
}