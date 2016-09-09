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
namespace oat\tao\solarium\view;

use oat\tao\model\mvc\view\ViewHelperAbstract;
/**
 * Description of SolaruimHelpView
 *
 * @author Christophe GARCIA <christopheg@taotesting.com>
 */
class SolaruimHelpView extends ViewHelperAbstract {
     /**
     * return help html
     * @return string
     */
    public function render() {
        
        $render = ' <div class="tooltip-content">
        <div>
        <strong>ex:</strong> <em>label:exam* AND model:QTI</em>
        </div>
        <hr style="margin:5px 0;"/>';

        foreach ($this->searchIndex as $uri => $indexes) {
            foreach ($indexes as $index) {
                $prop = new \core_kernel_classes_Property($uri);
                $css  = ($index->isFuzzyMatching()) ? "icon-find" : "icon-target" ;
                $render .= '<div>
                                <span class="' . $css . '"></span> <strong>' . _dh($index->getIdentifier()). '</strong> 
                                    (' . _dh($prop->getLabel()) . ') </div>';
            }
        }

        $render .= '<hr style="margin:5px 0;"/>
            <div class="grid-row" style="min-width:250px; margin: 0">
                <div class="col-6" style="margin: 0">
                    <span class="icon-find"></span> = Fuzzy Matching
                </div>
                <div class="col-6" style="margin: 0">
                    <span class="icon-target"></span> = Exact Matching
                </div>
            </div>
        </div>';
        
        return $render;
        
    }
    
}
