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
 * Copyright (c) 2016 (original work) Open Assessment Technologies SA;
 *               
 * 
 */
namespace oat\tao\solarium\Action;

use oat\tao\solarium\SolariumSearch;
use oat\tao\model\search\SearchService;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;
use common_report_Report as Report;
use oat\tao\model\search\SyntaxException;
use oat\oatbox\extension\InstallAction;
use oat\tao\model\search\Search;

class InitSolarium extends InstallAction
{
    protected function getDefaultEndpoint()
    {
        return array(
            'host' => '127.0.0.1',
            'port' => '8983',
            'path' => '/solr/tao',
        );
    }
    
    public function __invoke($params) {
        
        if (!class_exists('oat\\tao\\solarium\\SolariumSearch')) {
            throw new \Exception('Tao Solarium Search not found');
        }
        
        $config = array(
            'endpoint' => array(
                'solrServer' => $this->getDefaultEndpoint()
            )
        );
        
        $p = $params;
        // host
        if (count($p) > 0) {
            $config['endpoint']['solrServer']['host'] = array_shift($p);
        }
        
        // port
        if (count($p) > 0) {
            $config['endpoint']['solrServer']['port'] = array_shift($p);
        }
        
        // path
        if (count($p) > 0) {
            $config['endpoint']['solrServer']['path'] = array_shift($p);
        }
        
        $taoVersion = $this->getServiceLocator()->get(\common_ext_ExtensionsManager::SERVICE_ID)->getInstalledVersion('tao');
        if (version_compare($taoVersion, '7.8.0') < 0) {
            return new Report(Report::TYPE_ERROR, 'Requires Tao 7.8.0 or higher');
        }
        
        $search = new SolariumSearch($config);
        try {
            $result = $search->query('*', 'sample');
            $success = $this->getServiceManager()->register(Search::SERVICE_ID, $search);
            return new Report(Report::TYPE_SUCCESS, __('Switched to Solr search using Solarium'));
        } catch (SyntaxException $e) {
            return new Report(Report::TYPE_ERROR, 'Solr server could not be found');
        }
        
    }
}
