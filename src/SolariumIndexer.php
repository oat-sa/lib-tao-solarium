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
use oat\tao\model\search\index\IndexProperty;
use Solarium\Client;
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

    /** @var null|Client  */
    private $client = null;

    /** @var array  */
    private $map = [];

    /** @var null|\Traversable  */
    private $document = null;

    /**
     * @var SearchTokenGenerator
     */
    private $tokenGenerator = null;

    /**
     * SolariumIndexer constructor.
     * @param Client $client
     * @param \Traversable|null $documentTraversable
     */
    public function __construct(Client $client, \Traversable $documentTraversable = null)
    {
        $this->client = $client;
        $this->document = $documentTraversable;
        $this->tokenGenerator = new SearchTokenGenerator();
    }

    /**
     * @return int
     */
    public function run()
    {
        // flush existing index
        $update = $this->client->createUpdate();
        $update->addDeleteQuery('*:*');
        $result = $this->client->update($update);
        $count = $this->addList();
        return $count;
    }

    /**
     * @return int
     */
    public function addList()
    {
        $count = 0;

        while ($this->document->valid()) {
            $update = $this->client->createUpdate();
            $blockSize = 0;
            while ($this->document->valid() && $blockSize < self::INDEXING_BLOCK_SIZE) {
                $document = $this->createDocument($update, $this->document->current());
                $update->addDocuments(array($document));
                $blockSize++;
                $this->document->next();
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

    /**
     * @param IndexDocument $document
     */
    public function add(IndexDocument $document)
    {
        $update = $this->client->createUpdate();
        $solrDocument = $this->createDocument($update, $document);
        $update->addDocuments(array($solrDocument));
        $result = $this->client->update($update);

        $update = $this->client->createUpdate();
        $update->addCommit();
        $update->addOptimize();
        $result = $this->client->update($update);
    }

    /**
     * @return array
     */
    public function getIndexMap()
    {
        return $this->map;
    }

    /**
     * @param $update
     * @param IndexDocument $document
     * @return \Solarium\QueryType\Update\Query\Document\DocumentInterface
     */
    protected function createDocument($update, IndexDocument $document)
    {
        $document = new SolariumDocument($update, $document);
        $body = $document->getDocument()->getBody();
        $indexProperties = $document->getDocument()->getIndexProperties();
        foreach ($body as $index => $value) {
            $prefix = '';
            if (isset($indexProperties[$index])) {
                /** @var IndexProperty $indexProperty */
                $indexProperty = $indexProperties[$index];
                $prefix = $indexProperty->isFuzzy() ? '_t' : '_s';
                $prefix .= $indexProperty->isDefault() ? '_d' : '';
                $document->add($index.$prefix, $value);
                $this->map[$index] = $index.$prefix;
            }

            if ($index == 'type') {
                $index = $index.'_r';
                $document->add($index, $value);
                $this->map[$index] = $index.$prefix;
            }
        }
        return $document->getSolrDocument();
    }

}
