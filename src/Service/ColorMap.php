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

/* use App\ColorMaps\Accent; */

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
        $state['scale'] = count($this->_colors);
        $state['offset'] = $offset;

        if ( $offset ) {
            $state['scale'] = count($this->_colors)-$offset;
            $state['offset'] = $offset;
        }

        $state['index'] = 0;
        if ($count != 0){
            $state['stepping'] = 1.0/$count;
        } else {
            $state['stepping'] = 0.1;
        }
    }

    public function setColormap($name, $projectDir)
    {
        $file = $name.'.php';
        $map = 'COLOR_'.$name;
        include_once "$projectDir/src/Colormaps/$file";
        $this->_colors = constant($map);
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

        return $this->_colors[$index];
    }

    public function getColorMap($mode = 'default'):array
    {
        if ($mode == 'xmgrace') {
            $colormap;
            $index = 0;
            foreach ( $this->_colors as $color ){
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

        return $this->_colors;
    }
}

