<?php
/* 
 * This file is part of the plog.
 * Copyright (c) 2017 TANIGUCHI Masaya.
 * 
 * This program is free software: you can redistribute it and/or modify  
 * it under the terms of the GNU General Public License as published by  
 * the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful, but 
 * WITHOUT ANY WARRANTY; without even the implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU 
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License 
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
require 'lib/Parsedown.php';
$CONFIG = parse_ini_file('plog.ini', true);
class Plog
{
    private static $content = '';
    public static function root()
    {
        return str_replace($_SERVER['PATH_INFO'],'',$_SERVER['REQUEST_URI']);
    }
    public static function content()
    {
        return self::$content;
    }
    public static function title()
    {
        preg_match('#<h1>(?:<time>.*</time>)?(.+?)</h1>#', self::$content, $matches);

        return $matches[1];
    }
    public static function route()
    {
        if (preg_match('#^/(\d+)/(\d+)/(\d+)/[^/]+/?$#', $_SERVER['PATH_INFO'], $matches)) {
            self::read("content/$matches[1]-$matches[2]-$matches[3]-$matches[4].md");
        } elseif (preg_match('#^/(\d+)/(\d+)/(\d+)/?$#', $_SERVER['PATH_INFO'], $matches)) {
            self::list($matches);
        } elseif (preg_match('#^/(\d+)/(\d+)/?$#', $_SERVER['PATH_INFO'], $matches)) {
            self::list($matches);
        } elseif (preg_match('#^/(\d+)/?$#', $_SERVER['PATH_INFO'], $matches)) {
            self::list($matches);
        } elseif (preg_match('#^/webhook$#', $_SERVER['PATH_INFO'])) {
            self::hook();
        } elseif (preg_match('#^/?$#', $_SERVER['PATH_INFO'])) {
            self::read('page/welcome.php');
        } else {
            header('HTTP/1.1 404 Not Found');
            self::read('page/404.php');
        }
    }
    private static function list($matches)
    {
        switch (count($matches)) {
            case 2: $filenames = glob("content/$matches[1]-*.md"); break;
            case 3: $filenames = glob("content/$matches[1]-$matches[2]-*.md"); break;
            case 4: $filenames = glob("content/$matches[1]-$matches[2]-$matches[3]-*.md"); break;
        }
        if (count($filenames) > 0) {
            foreach ($filenames as $filename) {
                self::read($filename);
            }
        } else {
            self::read('page/404.php');
        }
    }
    private static function read($filename)
    {
        if (preg_match('#\.php$#', $filename)) {
            ob_start();
            require $filename;
            self::$content .= ob_get_clean();
        } else {
            preg_match('/\d+-\d+-\d+/', $filename, $matches);
            self::$content .= '<article>';
            self::$content .= "<time>$matches[0]</time>";
            self::$content .= Parsedown::instance()->text(file_get_contents($filename));
            self::$content .= '</article>';
        }
    }
    private static function hook()
    {
        if (isset($_GET['secret']) && $_GET['secret'] === $CONFIG['webhook']['secret']) {
            foreach ($CONFIG['webhook']['commands'] as $command) {
                exec($command, $dummy, $status);
                if ($status) {
                    break;
                }
            }
            file_put_contents($LOG_FILE, date('[Y-m-d H:i:s]').' '.$_SERVER['REMOTE_ADDR']." : valid access\n", FILE_APPEND | LOCK_EX);
        } else {
            file_put_contents($LOG_FILE, date('[Y-m-d H:i:s]').' '.$_SERVER['REMOTE_ADDR']." : invalid access\n", FILE_APPEND | LOCK_EX);
        }
        exit;
    }
}
?>