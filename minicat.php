<?php
/**
 * @copyright 2012 Kai Mallea
 * @author Kai Mallea <kai@mallea.net>
 * @license MIT
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
     *
     * @param array $args Command line arguments from getopt()
     *
     * @return void
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


    /**
     * The main build loop which peforms minification and
     * concatenation on each asset
     *
     * @return void
     */
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
                } else {
                    $temp_files[] = self::minify($source_asset['file']);
                }
            }

            self::log('Step 2: Concatenate...');

            self::concat($temp_files, $target_asset);

            self::log('Build successful' . "\n");
        }
    }


    /**
     * Check if any of the "conditional" files passed in on
     * the command line are defined in the manifest for a 
     * particular target file.
     *
     * @param string $target Target file
     *
     * @return true|false
     */
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


    /**
     * Remove the "config" section from the config file and store it
     * in a class variable
     *
     * @return void
     */
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


    /**
     * Return a file's extension
     *
     * @param string $filename File name
     *
     * @return string File's extension
     */
    public static function extension ($filename) {
        return pathinfo($filename, PATHINFO_EXTENSION);
    }


    /**
     * Concatenate an array of files using native OS commands
     *
     * @param array $source_files Files to concatenate
     * @param string $target_file File to output
     *
     * @return void
     */
    public static function concat ($source_files, $target_file) {
        if (strtolower(substr(PHP_OS, 0, 3)) === 'win') {
            $cmd = sprintf('copy /b /y %s %s', implode('+', $source_files), $target_file);
        } else {
            $cmd = sprintf('cat %s > %s', implode(' ', $source_files), $target_file);
        }

        exec($cmd, $output, $result);

        if ($result) {
            self::log(
                sprintf('There was an error concatenating %s',
                    implode(',', $source_files),
                    true)
            );

            exit(1);
        }
    }


    /**
     * Minify a file and output a temporary file
     *
     * @param string $source_file File to minify
     *
     * @return string Path to minified temp file
     */
    public static function minify ($source_file) {
        $temp_dir = sys_get_temp_dir();
        $temp_file = tempnam($temp_dir, $source_file);

        if (!$temp_file) {
            self::log('Could not create temp file in ' . $temp_dir, true);
            exit(1);                    
        }

        $ext = self::extension($source_file);
        $minify_cmd = str_replace(
            array('{$compiler_path}', '{$input_file}', '{$output_file}'),
            array(self::$config[$ext.'_compiler_path'], $source_file, $temp_file),
            self::$config[$ext.'_compiler_command']
        );

        exec($minify_cmd, $output, $result);
        
        if ($result) {
            self::log('There was a problem minifying ' . $source_file, true);
            exit(1);
        }

        return $temp_file;
    }


    /**
     * Log a message to stdout in verbose mode
     *
     * @param string $msg Message to log
     * @param boolean $force Force output even when not in verbose mode
     *
     * @return string File's extension
     */
    public static function log ($msg, $force=false) {
        if (self::$verbose || $force) {
            echo '[MC] ' . $msg . PHP_EOL;
        }
    }


    /**
     * Return path to minicat.yaml in current working dir
     *
     * @return string Current working directory + '/minicat.yaml'
     */
    public static function get_default_config_path () {
        return getcwd() . DIRECTORY_SEPARATOR . self::DEFAULT_CONFIG_FILENAME;
    }


    /**
     * Print program help
     *
     * @return void
     */
    public static function print_help () {
        echo 'Help coming soon!' . "\n";
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