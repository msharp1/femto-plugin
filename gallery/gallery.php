<?php

/**
 * A plugin for Femto to display neat gap-less galleries.
 *
 * Requires the Image plugin.
 *
 * @author Sylvain Didelot
 * @see https://github.com/neckcen/femto-plugin/tree/master/image
 * @see http://www.crispymtn.com/stories/the-algorithm-for-a-perfectly-balanced-photo-gallery
 */

namespace femto\plugin\gallery;

/**
 * Check if Image plugin is enabled.
 *
 */
function config(&$config) {
    if(!in_array('image', $config['plugin_enabled'])) {
        throw new \Exception('Gallery requires the Image plugin.');
    }
}

/**
 * Build the gallery if requested.
 *
 * Search the page's content for %gallery:WIDTHxHEIGHT%. If found search for
 * all images in the current page's directory and display them.
 *
 * @param Page $page Femto page.
 */
function page_content_after($page) {
    $re = '`(?:<p>)?\{gallery:([0-9]+)x([0-9]+)\}(?:</p>)?`';
    if(!preg_match($re, $page['content'], $match)) {
        return;
    }
    list($tag, $display_width, $ideal_height) = $match;
    $directory = dirname($page['file']);
    $total_width = 0;
    $ratio_list = [];
    $picture_by_ratio = [];
    foreach(scandir($directory) as $file) {
        if($file == '.' || $file == '..') {
            continue;
        }
        $size = @getimagesize($directory.'/'.$file);
        if($size == false) {
            continue;
        }
        list($width, $height) = $size;
        $ratio = round($width / $height * 10000);
        $ratio_list[] = $ratio;
        if(!isset($picture_by_ratio[$ratio])) {
            $picture_by_ratio[$ratio] = [];
        }
        $picture_by_ratio[$ratio][] = $file;
        $total_width += $ideal_height * ($ratio / 10000);
    }

    $row = round($total_width/$display_width);
    $distribution = linear_partition($ratio_list, $row);

    $gallery = sprintf('<div class="gallery" style="width:%dpx"><ul>',
        $display_width);
    $directory = substr($directory, strlen(\femto\Femto::$config['content_dir']));
    $url = \femto\escape(\femto\Femto::$config['base_url'].'/plugin/image');
    foreach($distribution as $row) {
        $total_ratio = array_sum($row) / 10000;
        $height = floor($display_width / $total_ratio);
        // ensure the width sums to $display_width
        // aka largest remainder method
        $width = [];
        $remainder = [];
        foreach($row as $ratio) {
            $dec = $height * ($ratio / 10000);
            $int = floor($dec);
            $width[] = $int;
            $remainder[] = $dec - $int;
        }
        while(array_sum($width) < $display_width) {
            $k = array_search(max($remainder), $remainder);
            $width[$k]++;
            $remainder[$k] = 0;
        }
        foreach($row as $k => $ratio) {
            $file = \femto\escape($directory.'/'.array_shift($picture_by_ratio[$ratio]));
            $gallery .= sprintf(
              '<li style="width:%f%%"><a href="%s%s">'.
              '<img src="%s%s?w=%d&amp;h=%d" alt=""/></a></li>',
              $width[$k]/$display_width*100, $url, $file, $url, $file, $width[$k], $height
            );
        }
    }
    $gallery .= '</ul></div>';
    $page['content'] = str_replace($tag, $gallery, $page['content']);
}

/**
 * PHP implementation of the linear partition algorithm.
 *
 * @see http://stackoverflow.com/a/21259094
 *
 * @param $seq array List of values to distribute.
 * @param $k int Number of rows.
 */
function linear_partition($seq, $k) {
    if ($k <= 0) {
        return [];
    }

    $n = count($seq);

    if ($k > $n-1) {
        foreach ($seq as &$x) {
            $x=[$x];
        }
        return $seq;
    }

    $table = array_fill(0, $n, array_fill(0, $k, 0));
    $solution = array_fill(0, $n-1, array_fill(0, $k-1, 0));

    for ($i = 0; $i < $n; $i++) {
        $table[$i][0] = $seq[$i] + ($i ? $table[$i-1][0] : 0);
    }

    for ($j = 0; $j < $k; $j++) {
        $table[0][$j] = $seq[0];
    }

    for ($i = 1; $i < $n; $i++) {
        for ($j = 1; $j < $k; $j++) {
            $current_min = null;
            $minx = PHP_INT_MAX;

            for ($x = 0; $x < $i; $x++) {
                $cost = max($table[$x][$j - 1], $table[$i][0] - $table[$x][0]);
                if ($current_min === null || $cost < $current_min) {
                    $current_min = $cost;
                    $minx = $x;
                }
            }

            $table[$i][$j] = $current_min;
            $solution[$i-1][$j-1] = $minx;
        }
    }

    $k--;
    $n--;
    $ans = [];

    while ($k > 0) {
        array_unshift($ans, array_slice($seq,
          $solution[$n-1][$k-1] + 1, $n - $solution[$n-1][$k-1]));
        $n = $solution[$n-1][$k-1];
        $k--;
    }

    array_unshift($ans, array_slice($seq, 0, $n+1));
    return $ans;
}
