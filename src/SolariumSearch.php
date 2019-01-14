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
use oat\tao\model\search\index\IndexIterator;
use oat\tao\model\search\index\IndexService;
use oat\tao\model\search\Search;
use common_Logger;
use Solarium\Client;
use oat\tao\model\search\SyntaxException;
use Solarium\Exception\HttpException;
use Solarium\QueryType\Select\Query\Query;
use oat\tao\model\search\ResultSet;
use Solarium\Core\Query\Result\Result;
use oat\oatbox\service\ConfigurableService;
use oat\tao\solarium\view\SolaruimHelpView;
use oat\tao\model\mvc\view\ViewHelperAwareTrait;
use oat\tao\model\mvc\view\ViewHelperAwareInterface;
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
class SolariumSearch extends ConfigurableService implements Search , ViewHelperAwareInterface
{
    const SUBSTITUTION_CONFIG_KEY = 'solr_search_map';
    
    use ViewHelperAwareTrait;
    /**
     *
     * @var \Solarium\Client
     */
    private $client;

    private $substitutes = null;

    protected $helpView = SolaruimHelpView::class;

    use ViewHelperAwareTrait;

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
    public function query($queryString, $type, $start = 0, $count = 10, $order = 'id', $dir = 'DESC') {
        
        $queryString = $this->buildSearchQuery($queryString, $type);
        
        try {
            /** @var \Solarium\QueryType\Select\Query\Query $query */
            $query = $this->getClient()->createQuery( \Solarium\Client::QUERY_SELECT );
            $query->setQueryDefaultOperator( Query::QUERY_OPERATOR_OR );
            $query->setQueryDefaultField( 'text' );
            $query->setQuery( $queryString )->setRows( $count )->setStart( $start );
        
            // this executes the query and returns the result
            /** @var Result $resultset */
            $result = $this->getClient()->execute( $query );
            
            return $this->buildResultSet($result);
        
        } catch ( HttpException $e ) {
            switch ($e->getCode()) {
            	case 400 :
            	    $json = json_decode( $e->getBody(), true );
            	    throw new SyntaxException(
            	        $queryString,
            	        __( 'There is an error in your search query, system returned: %s', $json['error']['msg'] )
            	    );
            	default :
            	    throw new SyntaxException( $queryString, __( 'An unknown error occured during search' ) );
            }
        
        }
        
    }

    /**
     * (non-PHPdoc)
     * @see \oat\tao\model\search\Search::flush()
     */
    public function flush() {
        // flush existing index
        $update = $this->getClient()->createUpdate();
        $update->addDeleteQuery('*:*');
        $result = $this->getClient()->update($update);
        $this->setIndexSubstitutions([]);
    }

    /**
     * @param $map
     * @throws \common_exception_Error
     * @throws \common_ext_ExtensionException
     */
    public function setIndexSubstitutions($map) {
        $ext = \common_ext_ExtensionsManager::singleton()->getExtensionById('tao');
        $ext->setConfig(self::SUBSTITUTION_CONFIG_KEY, $map);
        $this->substitutes = $map;
    }

    /**
     * @return mixed|null
     * @throws \common_ext_ExtensionException
     */
    public function getIndexSubstitutions() {
        if (is_null($this->substitutes)) {
            $ext = \common_ext_ExtensionsManager::singleton()->getExtensionById('tao');
            $this->substitutes = $ext->hasConfig(self::SUBSTITUTION_CONFIG_KEY)
                ? $ext->getConfig(self::SUBSTITUTION_CONFIG_KEY)
                : []
            ;
        }
        return $this->substitutes;
    }
    
    /**
     * Transform Tao search string into a Solr search string
     * 
     * @param string $queryString
     * @param string $rootClass
     * @return string
     */
    protected function buildSearchQuery( $queryString, $type )
    {
        $parts = explode( ' ', $queryString );
        foreach ($parts as $key => $part) {
        
            $matches = array();
            if (preg_match( '/^([^a-z_]*)([a-z_]+):(.*)/', $part, $matches ) === 1) {
                list( $fullstring, $prefix, $fieldname, $value ) = $matches;
                $sub = $this->getIndexSubstitutions();
                if (isset( $sub[$fieldname] )) {
                    $parts[$key] = $prefix . $sub[$fieldname] . ':' . $value;
                }
            }
        }
        $queryString = implode( ' ', $parts );
        $queryString = (strlen($queryString) == 0 ? '' : '(' . $queryString . ') AND ')
            .'type_r:' . str_replace( ':', '\\:', $type );
        return $queryString;
    }
    
    /**
     * Transform Solr result into a Tao ResultSet
     * 
     * @param Result $solrResult
     * @return \oat\tao\model\search\ResultSet
     */
    protected function buildResultSet(Result $solrResult)
    {
        $uris = [];
        foreach ($solrResult as $document) {
            $uris[] = $document->uri;
            //.' : '.implode(',',$document->label);
        }

        return new ResultSet($uris, $solrResult->getNumFound());
    }

    /**
     * (Re)Generate the index for a given resource
     * 
     * @param IndexIterator|array $documents
     * @return boolean true if successfully indexed
     */
    public function index($documents = [])
    {
        $indexer = new SolariumIndexer($this->getClient(), $documents);
        $count = $indexer->index();
        $map = $this->getIndexSubstitutions();
        $this->setIndexSubstitutions(array_merge($map, $indexer->getIndexMap()));
        return $count;
    }

    /**
     * (non-PHPdoc)
     * @see \oat\tao\model\search\Search::remove()
     */
    public function remove($resourceId)
    {
        // @todo test
        $update = $this->getClient()->createUpdate();
        $update->addDeleteQuery($resourceId);
        $result = $this->getClient()->update($update);
        return true;
    }

    /**
     * (non-PHPdoc)
     * @see \oat\tao\model\search\Search::supportCustomIndex()
     */
    public function supportCustomIndex()
    {
        return true;
    }
    
}