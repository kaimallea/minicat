<?php
/**
 * Copyright 2012 Kai Mallea (kai@mallea.net)
 *
 * License: MIT (http://www.opensource.org/licenses/mit-license.php)
 */

require_once(__DIR__ . '/includes/assets.php');
require_once(__DIR__ . '/lib/Yaml/Yaml.php');
require_once(__DIR__ . '/lib/Yaml/Parser.php');
require_once(__DIR__ . '/lib/Yaml/Inline.php');
require_once(__DIR__ . '/lib/Yaml/Unescaper.php');
use Symfony\Component\Yaml\Yaml;

/* Idk the real identity of the person who created this ascii
   art, but it's awesome, so I'm using it. I shall call you...

   Minicat
                      _                        
                      \`*-.                    
                       )  _`-.                 
                      .  : `. .                
                      : _   '  \               
                      ; *` _.   `*-._          
                      `-.-'          `-.       
                        ;       `       `.     
                        :.       .        \    
                        . \  .   :   .-'   .   
                        '  `+.;  ;  '      :   
                        :  '  |    ;       ;-. 
                        ; '   : :`-:     _.`* ;
               [bug] .*' /  .*' ; .*`- +'  `*' 
                     `*-*   `*-*  `*-*'        


   MINIfy and conCATenate your JavaScript and CSS files

*/
class Minicat {
    const DEFAULT_CONFIG_FILENAME = 'minicat.yaml';
    private static $config;
    private static $verbose;
    private static $conditional_build;


    /**
     * Initialization ensures that:
     *      a. there is an explicitly defined config or we will look
     *         for the default (./minicat.yaml), and
     *      b. the config exists and can be parsed without issue
     */
    public static function init ($args) {
        foreach ($args as $arg => $val) {
            switch ($arg) {
                case 'h': // fall through
                case 'help':
                    self::print_help();
                    exit(1);
                case 'c':   // fall through
                case 'config':
                    self::$config = $val;
                    break;
                case 'v':   // fall through
                case 'verbose':
                    self::$verbose = true;
                    break;
                case 'i':   // fall through
                case 'conditional':
                    self::$conditional_build = $val;
                default:
                    self::print_help();
                    exit(1);
            }
        }

        if (self::$verbose) {
            self::log('Verbose mode');
            self::print_kitty();
        }

        if (!self::$config) { 
            self::log(sprintf('No config specified, trying %s', (self::$config = self::get_default_config_path())));
        }

        if (!file_exists(self::$config)) {
            self::log(sprintf('Could not open config file %s', self::$config), true);
            exit(1);
        }

        // self::$config begins its life as a string representing the
        // path to a config file. It will become an object made up of
        // parsed YAML
        try {
            self::$config = Yaml::parse(self::$config);
        } catch (Exception $e) {
            self::log($e->getMessage(), true);
            exit(1);
        }

        if (self::$conditional_build) {
            self::log('Conditional build mode');
        }
    }


    /**
     * Log a message to stdout
     */
    public static function log ($msg, $force=false) {
        if (self::$verbose || $force) {
            echo '[MC] ' . $msg . PHP_EOL;
        }
    }


    /**
     * Return path to minicat.yaml in current working dir
     */
    public static function get_default_config_path () {
        return getcwd() . DIRECTORY_SEPARATOR . self::DEFAULT_CONFIG_FILENAME;
    }


    /**
     * Print program help
     */
    public static function print_help () {}


    // ;)
    public static function print_kitty () {
        echo "
                      _                        
                      \`*-.                    
                       )  _`-.                 
                      .  : `. .                
                      : _   '  \               
                      ; *` _.   `*-._          
                      `-.-'          `-.       
                        ;       `       `.     
                        :.       .        \    
                        . \  .   :   .-'   .   
                        '  `+.;  ;  '      :   
                        :  '  |    ;       ;-. 
                        ; '   : :`-:     _.`* ;
               [bug] .*' /  .*' ; .*`- +'  `*' 
                     `*-*   `*-*  `*-*'        
        \n";
    }
}


if (PHP_SAPI === 'cli') {
    Minicat::init(
        getopt(
            'c:i:hv',
            array('config:', 'conditional:', 'help', 'verbose')
        )
    );
}