<?php
/**
 * Obsidian Moon Engine by Dark Prospect Games
 *
 * An Open Source, Lightweight and 100% Modular Framework in PHP
 *
 * PHP version 7
 *
 * @category  ObsidianMoonEngine
 * @package   DarkProspectGames\ObsidianMoonEngine
 * @author    Alfonso E Martinez, III <opensaurusrex@gmail.com>
 * @copyright 2011-2018 Dark Prospect Games, LLC
 * @license   MIT https://darkprospect.net/MIT-License.txt
 * @link      https://github.com/dark-prospect-games/obsidian-moon-engine/
 */
namespace DarkProspectGames\ObsidianMoonEngine;

use \DarkProspectGames\ObsidianMoonEngine\Modules\CoreException;

/**
 * Class Core
 *
 * This class is the core of the framework and handles all of the loading and
 * processing of modules and controls that will be used by your application.
 *
 * @category ObsidianMoonEngine
 * @package  DarkProspectGames\ObsidianMoonEngine
 * @author   Alfonso E Martinez, III <opensaurusrex@gmail.com>
 * @license  MIT https://darkprospect.net/MIT-License.txt
 * @link     https://github.com/dark-prospect-games/obsidian-moon-engine/
 * @uses     AbstractController
 * @uses     AbstractModule
 * @uses     CoreException
 * @since    1.0.0 Created core module, 1.4.0 Handling objects passed to module
 *           instead of strings & added ability to have default view data.
 */
class Core
{

    /**
     * The Framework version
     *
     * @type string
     */
    public const VERSION = '1.7.2';
    /**
     * Collection of controllers that can be used by the app.
     *
     * @type AbstractController[]
     */
    protected $controls = [];
    /**
     * Collection of models and modules that are available to all views.
     *
     * @type mixed[]
     */
    protected $viewData = [];
    /**
     * The variable that stores compiled output that will be returned at the end.
     *
     * @type string
     */
    protected $output;
    /**
     * Array holding all of the configurations that we created the Core with.
     *
     * @type mixed[]
     */
    protected $configs  = [];
    /**
     * Contains keys and values of variables set in app.
     *
     * @type mixed[]
     */
    protected $globals  = [];
    /**
     * Array holding all of the Module objects currently loaded into Core.
     *
     * @type AbstractModule[]
     */
    protected $modules  = [];

    /**
     * Collection of errors messages passed from the framework.
     *
     * @type string[]
     */
    public $errors = [];

    /**
     * This creates an instance of the core class.
     *
     * @param mixed[] $conf Configurations being set and/or overwritten.
     *
     * @since  1.0.0
     * @throws CoreException
     */
    public function __construct(array $conf = [])
    {
        $this->globals = [
            'systemTime' => time(),
            'isAjax'  => $this->_getAjax(),
            'isHttp'  => $this->_getProtocol(),
        ];

        $this->configs = [
            'core' => __DIR__,
            'base' => \dirname($_SERVER['SCRIPT_FILENAME']),
            'libs' => \dirname($_SERVER['SCRIPT_FILENAME']) . '/../src',
        ];
        // Assign all configuration values to $conf_**** variables.
        if (\count($conf) > 0) {
            foreach ($conf as $key => $value) {
                $this->configs[$key] = $value;
            }
        }

        // CoreRouting is default routing method, can be overwritten when specified.
        if (!array_key_exists('routing', $this->configs)) {
            $this->configs['routing']
                = '\DarkProspectGames\ObsidianMoonEngine\Modules\Routing';
        }

        if (array_key_exists('modules', $conf)) {
            try {
                foreach ($conf['modules'] as $key => $value) {
                    $this->module($key, $value);
                }
            } catch (CoreException $e) {
                throw new CoreException($e->getMessage());
            }
        }
    }

    /**
     * We will automatically echo the framework's output buffer
     */
    public function __destruct()
    {
        if ($this->output) {
            echo $this->output;
        }
    }

    /**
     * Call either a module or a global from storage.
     *
     * This method will automatically grab the correct reference and return the
     * value as the app needs.
     *
     * @param string $name The global variable that is trying to be accessed.
     *
     * @return mixed
     * @throws CoreException
     */
    public function __get(string $name)
    {
        // Check if we are looking for a configuration variable
        if (0 === stripos($name, 'conf_')) {
            $name = str_replace('conf_', '', $name);
            if (array_key_exists($name, $this->configs)) {
                return $this->configs[$name];
            }
        }
        // Is the variable a module?
        if (array_key_exists($name, $this->modules)) {
            return $this->modules[$name];
        }
        // Is the variable a global?
        if (array_key_exists($name, $this->globals)) {
            return $this->globals[$name];
        }
        throw new CoreException("Could not find a variable by the name '{$name}'!");
    }

    /**
     * Global Setter
     *
     * We use this to set a global in the global storage array, however we don't
     * want them to be able to create variables that have prefix of 'conf_' since
     * that is reserved for framework configs.
     *
     * @param mixed $name  The global variable that is trying to be set.
     * @param mixed $value Value of the global that you are trying to set.
     *
     * @uses globals to store value of $value
     *
     * @return boolean
     */
    public function __set(string $name, $value)
    {
        if (0 === stripos($name, 'conf_')) {
            return false;
        }
        $this->globals[$name] = $value;

        // Check to make sure that the value got set, and that it is correct.
        return (array_key_exists($name, $this->globals) &&
            $this->globals[$name] === $value);
    }

    /**
     * Global Isset
     *
     * We use this to check if there is a global variable in the globals
     *
     * @param mixed $name The global variable that is going to be checked
     *
     * @uses globals to check if $name is a key
     *
     * @return boolean
     */
    public function __isset($name)
    {
        if (0 === stripos($name, 'conf_')) {
            $name = str_replace('conf_', '', $name);
            if (array_key_exists($name, $this->configs)) {
                return true;
            }
        }

        return (array_key_exists($name, $this->modules) ||
            array_key_exists($name, $this->globals));
    }

    /**
     * Global toString
     *
     * We use this to return the name and version of Framework if they try to
     * echo Core.
     *
     * @return string
     */
    public function __toString()
    {
        return 'Obsidian Moon Engine v' . self::VERSION .
            ', Copyright (c) 2011-2018 Dark Prospect Games, LLC';
    }

    /**
     * Tests the server to see if the user has submitted an AJAX request.
     *
     * @return boolean
     */
    private function _getAjax(): bool
    {
        return (array_key_exists('HTTP_X_REQUESTED_WITH', $_SERVER) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * Tests the server to see if it is running HTTP or HTTPS.
     *
     * @return string
     */
    private function _getProtocol(): string
    {
        // Check for Apache HTTPS.
        if (array_key_exists('HTTPS', $_SERVER)
            && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1)
        ) {
            return 'https';
        }

        // Check for Nginx HTTPS.
        if (array_key_exists('SERVER_PORT', $_SERVER)
            && ($_SERVER['SERVER_PORT'] === '443')
        ) {
            return 'https';
        }

        return 'http';
    }

    /**
     * This function will load classes as needed for the user to use.
     * It will allow them to be accessible via $core->modulename.
     *
     * @param string $moduleName   This is key name that we will save the module to.
     * @param object $moduleObject This is the modules that will be loaded into Core.
     *
     * @return boolean
     * @throws CoreException
     */
    public function module(string $moduleName, $moduleObject): bool
    {
        // Check to make sure the module doesn't already exist.
        if (array_key_exists($moduleName, $this->modules)) {
            throw new CoreException(
                "Module '\$this->$moduleName' has already been set!"
            );
        }

        if (method_exists($moduleObject, 'start')) {
            $moduleObject->start($this);
        }
        $this->modules[$moduleName] = $moduleObject;

        return true;
    }

    /**
     * Routing Caller
     *
     * We run the routing after all the modules etc have been loaded to make sure
     * that the correct Control is called.
     *
     * @return void
     * @throws CoreException
     */
    public function routing(): void
    {
        $routingClass = $this->configs['routing'];
        try {
            $this->module('routing', new $routingClass());
        } catch (CoreException $e) {
            throw new CoreException($e->getMessage());
        }
    }

    /**
     * Load a View into the System
     *
     * This method loads a 'view' and implants the data into it,
     * and if needed returns that value to be included in other views.
     *
     * <code>
     *     $this->core->view()
     * </code>
     *
     * @param null|string $_view   Name of the view to be called.
     * @param null|array  $_data   Data that can be passed into the view to
     *                             populate existing variables.
     * @param bool        $_return If this is set to true it will pass the value
     *                             out to user otherwise append to the output buffer.
     *
     * @return mixed
     * @throws CoreException
     */
    public function view($_view, ?array $_data = [], bool $_return = false)
    {
        // Load the default data before
        if (\count($this->viewData) > 0) {
            extract($this->viewData, EXTR_SKIP);
        }
        // Are we sending data straight to output?
        if ($_view === null) {
            $this->output .= $_data[0];

            return true;
        }

        // The location of the View to be loaded
        $fileName = $this->configs['libs'] . '/Views/' . $_view . '.php';
        if (!file_exists($fileName)) {
            throw new CoreException("Could not find View in '{$fileName}'!");
        }

        if (\count($_data) > 0) {
            extract($_data, EXTR_SKIP);
        }

        ob_start();
        include $fileName;
        $buffer = ob_get_contents();
        ob_end_clean();
        if ($_return) {
            return $buffer;
        }
        $this->output .= $buffer;

        return true;
    }

    /**
     * Adds data to be used in views
     *
     * This method will merge the data with current `viewData` values.
     *
     * @param array $modules Modules that will be globally available in views.
     * @param bool  $reset   Whether to empty the data set before assigning new data.
     *
     * @return void
     */
    public function data(array $modules, bool $reset = false): void
    {
        if ($reset) {
            $this->viewData = [];
        }

        $this->viewData = array_merge($this->viewData, $modules);
    }
}
