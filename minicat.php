<?php
/**
 * Copyright 2012 Kai Mallea (kai@mallea.net)
 *
 * License: MIT (http://www.opensource.org/licenses/mit-license.php)
 */

require_once(__DIR__ . '/lib/Yaml/Yaml.php');
require_once(__DIR__ . '/lib/Yaml/Parser.php');
require_once(__DIR__ . '/lib/Yaml/Exception/ExceptionInterface.php');
require_once(__DIR__ . '/lib/Yaml/Exception/ParseException.php');
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


   Automate your JS/CSS minification and concatenation process

*/
class Minicat {
    const DEFAULT_CONFIG_FILENAME = 'minicat.yaml';
    private static $manifest;
    private static $config;
    private static $verbose;
    private static $conditional_build;
    private static $filenames_only;


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
                case 'm':   // fall through
                case 'manifest':
                    self::$manifest = $val;
                    break;
                case 'v':   // fall through
                case 'verbose':
                    self::$verbose = true;
                    break;
                case 'c':   // fall through
                case 'conditional':
                    self::$conditional_build = $val;
                    break;
                case 'f':   // fall through
                case 'filename':
                    self::$filenames_only = true;
                    break;
                default:
                    self::print_help();
                    exit(1);
            }
        }

/*
        if (self::$verbose) {
            //self::print_kitty();
        }
*/
        if (!self::$manifest) { 
            self::log(sprintf('No manifest specified, trying %s', (self::$manifest = self::get_default_config_path())));
        }

        if (!file_exists(self::$manifest)) {
            self::log(sprintf('Could not open manifest file %s', self::$manifest), true);
            exit(1);
        }

        try {
            self::$manifest = Yaml::parse(self::$manifest);
        } catch (Exception $e) {
            self::log($e->getMessage(), true);
            exit(1);
        }

        if ( !(self::$manifest['config']) ) {
            self::log('No config section found in manifest.');
            exit(1);
        }

        self::separate_config();
    
        if (self::$conditional_build) {
            self::$conditional_build = explode(' ', self::$conditional_build);
            self::log('Conditional build mode');
        }
        
        self::build();
    }


    public static function build () {
        self::log(sprintf('Identified %s target assets', count(self::$manifest)));

        foreach (self::$manifest as $target_asset => $source_asset_collection) {
            
            if (self::$conditional_build) {
                if (!self::conditional_test($target_asset)) {
                    self::log(sprintf('Skipping %s (no matches)', $target_asset));
                    continue;
                } else {
                    self::log(sprintf('Conditional match: %s', $target_asset));
                }
            }

            self::log(sprintf('Building %s...', $target_asset));
            self::log("Step 1: Minify...");
            foreach ($source_asset_collection as $source_asset) {
                self::log(sprintf('    |`-%s', $source_asset['file']));
                if (isset($source_asset['minify']) &&
                    strtolower($source_asset['minify']) === 'no') {
                    self::log('    |  `-Skipping minification');
                }
            }

            self::log('Step 2: Concatenate...');
            self::log('Build successful' . "\n");
        }
    }


    // Determine whether or not a source asset is present
    public static function conditional_test ($target) {
        foreach (self::$manifest[$target] as $source_asset) {
            
            if (self::$filenames_only) {
                foreach (self::$conditional_build as $conditional_file) {
                    if (strpos($conditional_file, basename($source_asset['file'])) !== false) {
                        return true;
                    }
                }
            } else {
                if (in_array($source_asset['file'], self::$conditional_build)) {
                    return true;
                }
            }
        }
        return false;
    }


    public static function separate_config () {
        for ($i = 0; $i < count(self::$manifest['config']); ++$i) {
            foreach(self::$manifest['config'][$i] as $setting => $val) {
                switch (strtolower($setting)) {
                    case 'js_compiler_path':
                        self::$config['js_compiler_path'] = $val;
                        break;
                    case 'js_compiler_command':
                        self::$config['js_compiler_command'] = $val;
                        break;
                    case 'css_compiler_path':
                        self::$config['css_compiler_path'] = $val;
                        break;
                    case 'css_compiler_command':
                        self::$config['css_compiler_command'] = $val;
                        break;
                    default:
                        self::log(sprintf('Unknown config setting "%s" (skipped)', $setting));
                }
            }
        }

        unset(self::$manifest['config']);
    }


    // Return only the extension portion of a path
    public static function extension ($str) {
        return pathinfo($str, PATHINFO_EXTENSION);
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
    public static function print_help () {
        echo 'Help coming soon!' . "\n";
    }


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
            'm:c:hvf',
            array('manifest:', 'conditional:', 'help', 'verbose', 'filename')
        )
    );
}