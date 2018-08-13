<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2018 Jan Eitzinger
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

namespace App\Service;
use App\Service\Configuration;

include "../src/Colormaps/Set3.php";
include "../src/Colormaps/GistEarth.php";
include "../src/Colormaps/16Level.php";
include "../src/Colormaps/Set2.php";
include "../src/Colormaps/Flag.php";
include "../src/Colormaps/GistHeat.php";
include "../src/Colormaps/BlueWaves.php";
include "../src/Colormaps/Purples.php";
include "../src/Colormaps/RdBu.php";
include "../src/Colormaps/Set1.php";
include "../src/Colormaps/Gray.php";
include "../src/Colormaps/YlOrRd.php";
include "../src/Colormaps/Steps.php";
include "../src/Colormaps/Reds.php";
include "../src/Colormaps/SternSpecial.php";
include "../src/Colormaps/BlueWhite.php";
include "../src/Colormaps/BlueRed.php";
include "../src/Colormaps/Haze.php";
include "../src/Colormaps/RainbowBlack.php";
include "../src/Colormaps/Pastel2.php";
include "../src/Colormaps/Pastels.php";
include "../src/Colormaps/GistStern.php";
include "../src/Colormaps/RdGy.php";
include "../src/Colormaps/GistNcar.php";
include "../src/Colormaps/YlGn.php";
include "../src/Colormaps/GistRainbow.php";
include "../src/Colormaps/GnBu.php";
include "../src/Colormaps/PuBuGn.php";
include "../src/Colormaps/Pastel1.php";
include "../src/Colormaps/PiYG.php";
include "../src/Colormaps/BlueGreenRedYellow.php";
include "../src/Colormaps/GreenWhiteExponential.php";
include "../src/Colormaps/Volcano.php";
include "../src/Colormaps/Blues.php";
include "../src/Colormaps/GreenPink.php";
include "../src/Colormaps/RedPurple.php";
include "../src/Colormaps/BwLinear.php";
include "../src/Colormaps/Greens.php";
include "../src/Colormaps/BluePastelRed.php";
include "../src/Colormaps/RdPu.php";
include "../src/Colormaps/PuRd.php";
include "../src/Colormaps/Oranges.php";
include "../src/Colormaps/HueSatValue1.php";
include "../src/Colormaps/StdGamma.php";
include "../src/Colormaps/HueSatLightness1.php";
include "../src/Colormaps/Beach.php";
include "../src/Colormaps/YlOrBr.php";
include "../src/Colormaps/Copper.php";
include "../src/Colormaps/Peppermint.php";
include "../src/Colormaps/HueSatValue2.php";
include "../src/Colormaps/Hardcandy.php";
include "../src/Colormaps/RdYlGn.php";
include "../src/Colormaps/Plasma.php";
include "../src/Colormaps/HueSatLightness2.php";
include "../src/Colormaps/GreenWhiteLinear.php";
include "../src/Colormaps/Accent.php";
include "../src/Colormaps/EosB.php";
include "../src/Colormaps/Spectral.php";
include "../src/Colormaps/RedTemperature.php";
include "../src/Colormaps/Cool.php";
include "../src/Colormaps/Rainbow18.php";
include "../src/Colormaps/YGB2.php";
include "../src/Colormaps/Nature.php";
include "../src/Colormaps/Binary.php";
include "../src/Colormaps/GistYarg.php";
include "../src/Colormaps/EosA.php";
include "../src/Colormaps/GistGray.php";
include "../src/Colormaps/YlGnBu.php";
include "../src/Colormaps/Waves.php";
include "../src/Colormaps/PurpleRedStripes.php";
include "../src/Colormaps/OrRd.php";
include "../src/Colormaps/BuPu.php";
include "../src/Colormaps/RdYlBu.php";
include "../src/Colormaps/RainbowWhite.php";
include "../src/Colormaps/Hot.php";
include "../src/Colormaps/Bone.php";
include "../src/Colormaps/Paired.php";
include "../src/Colormaps/PRGn.php";
include "../src/Colormaps/Autumn.php";
include "../src/Colormaps/Ocean.php";
include "../src/Colormaps/BrBg.php";
include "../src/Colormaps/Prism.php";
include "../src/Colormaps/GreenRedBlueWhite.php";
include "../src/Colormaps/Thomas.php";
include "../src/Colormaps/PuOr.php";
include "../src/Colormaps/BuGn.php";
include "../src/Colormaps/Dark2.php";
include "../src/Colormaps/Greys.php";
include "../src/Colormaps/PuBu.php";
include "../src/Colormaps/MacStyle.php";
include "../src/Colormaps/Rainbow.php";

class ColorMap
{
    private $_colors;

    private $_COLORMAPS = array(
        'Set3',
        'GistEarth',
        '16Level',
        'Set2',
        'Flag',
        'GistHeat',
        'BlueWaves',
        'Purples',
        'RdBu',
        'Set1',
        'Gray',
        'YlOrRd',
        'Steps',
        'Reds',
        'SternSpecial',
        'BlueWhite',
        'BlueRed',
        'Haze',
        'RainbowBlack',
        'Pastel2',
        'Pastels',
        'GistStern',
        'RdGy',
        'GistNcar',
        'YlGn',
        'GistRainbow',
        'GnBu',
        'PuBuGn',
        'Pastel1',
        'PiYG',
        'BlueGreenRedYellow',
        'GreenWhiteExponential',
        'Volcano',
        'Blues',
        'GreenPink',
        'RedPurple',
        'BwLinear',
        'Greens',
        'BluePastelRed',
        'RdPu',
        'PuRd',
        'Oranges',
        'HueSatValue1',
        'StdGamma',
        'HueSatLightness1',
        'Beach',
        'YlOrBr',
        'Copper',
        'Peppermint',
        'HueSatValue2',
        'Hardcandy',
        'RdYlGn',
        'Plasma',
        'HueSatLightness2',
        'GreenWhiteLinear',
        'Accent',
        'EosB',
        'Spectral',
        'RedTemperature',
        'Cool',
        'Rainbow18',
        'YGB2',
        'Nature',
        'Binary',
        'GistYarg',
        'EosA',
        'GistGray',
        'YlGnBu',
        'Waves',
        'PurpleRedStripes',
        'OrRd',
        'BuPu',
        'RdYlBu',
        'RainbowWhite',
        'Hot',
        'Bone',
        'Paired',
        'PRGn',
        'Autumn',
        'Ocean',
        'BrBg',
        'Prism',
        'GreenRedBlueWhite',
        'Thomas',
        'PuOr',
        'BuGn',
        'Dark2',
        'Greys',
        'PuBu',
        'MacStyle',
        'Rainbow'
    );

    public function __construct(
        Configuration $configuration
    )
    {
        $config = $configuration->getConfig();
        $map = 'COLOR_'.$config['plot_general_colorscheme']->value;
        $this->_color = constant($map);
        /* $this->_color = COLOR_RainbowBlack; */
    }

    public function getAllColorMaps()
    {
        return $this->_COLORMAPS;
    }

    private function _interpolateColor($color1, $color2, $factor=0.5)
    {
        for ($i = 0; $i < 3; $i++) {
            $result[$i] = floor($color1[$i] + $factor * ($color2[$i] - $color1[$i]));
        }
        return $result;
    }

    public function init(&$state, $count, $offset=0)
    {
        $state['scale'] = count($this->_color);
        $state['offset'] = $offset;

        if ( $offset ) {
            $state['scale'] = count($this->_color)-$offset;
            $state['offset'] = $offset;
        }

        $state['index'] = 0;
        if ($count != 0){
            $state['stepping'] = 1.0/$count;
        } else {
            $state['stepping'] = 0.1;
        }
    }

    public function setColormap($map)
    {
        $this->_color = constant($map);
    }

    public function setColorscale(&$state, $colorscale)
    {
        $state['colorscale'] = $colorscale;
    }

    public function getInterpolatedColor(&$state)
    {
        $position = $state['index'] * $state['stepping'];
        $color1 = $state['colorscale'];
        $color2;

        /* find color interval */
        foreach ($state['colorscale'] as $color) {
            if ($position < $color[0]) {
                $color2 = $color;
                break;
            }
            $color1 = $color;
        }

        /* compute linear factor within interval */
        $factor = ($position - $color1[0])/($color2[0] - $color1[0]);
        $result = $this->_interpolateColor($color1[1], $color2[1], $factor);
        $state['index']++;

        return "({$result[0]}, {$result[1]}, {$result[2]})";
    }

    public function getColor(&$state)
    {
        $index = floor($state['index']*$state['stepping']*$state['scale']+$state['offset']);
        $state['index']++;
        $state['mapping'] = $index;

        return $this->_color[$index];
    }

    public function getColorMap($mode = 'default'):array
    {
        if ($mode == 'xmgrace') {
            $colormap;
            $index = 0;
            foreach ( $this->_color as $color ){
                $str = str_replace('rgb', "", $color);

                $colormap[] = array(
                    'id' => $index,
                    'rgb' => $str,
                    'name' => "color".$index,
                );
                $index++;
            }
            $colormap[1] = array(
                'id' => 1,
                'rgb' => '(0, 0, 0)',
                'name' => "white",
            );
            $colormap[0] = array(
                'id' => 0,
                'rgb' => '(255, 255, 255)',
                'name' => "black",
            );

            return $colormap;
        }

        return $this->_color;
    }
}

