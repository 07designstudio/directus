<?php

namespace Directus;

use Directus\Acl\Acl;
use Directus\Auth\Provider as AuthProvider;
use Directus\Db\TableGateway\DirectusUsersTableGateway;
use Directus\Db\TableGateway\DirectusPrivilegesTableGateway;
use Directus\Db\TableGateway\DirectusSettingsTableGateway;
use Slim\Slim;
use Slim\Extras\Log\DateTimeFileWriter;

/**
 * NOTE: This class depends on the constants defined in config.php
 */
class Bootstrap {

    public static $singletons = array();

    /**
     * Returns the instance of the specified singleton, instantiating one if it
     * doesn't yet exist.
     * @param  string $key  The name of the singleton / singleton factory function
     * @param  mixed  $arg An argument to be passed to the singleton factory function
     * @return mixed           The singleton with the specified name
     */
    public static function get($key, $arg = null) {
        $key = strtolower($key);
        if(!method_exists(__CLASS__, $key))
            throw new \InvalidArgumentException("No such factory function on " . __CLASS__ . ": $key");
        if(!array_key_exists($key, self::$singletons))
            self::$singletons[$key] = call_user_func(__CLASS__."::$key", $arg);
        return self::$singletons[$key];
    }

    /**
     * Does an extension by the given name exist?
     * @param  string $extensionName
     * @return bool
     */
    public static function extensionExists($extensionName) {
        $extensions = self::get('extensions');
        return array_key_exists($extensionName, $extensions);
    }

    /**
     * Used to interrupt the bootstrapping of a singleton if the constants it
     * requires aren't defined.
     * @param  string|array $constants       One or more constant names
     * @param  string $dependentFunctionName The name of the function establishing the dependency.
     * @return  null
     * @throws  Exception If the specified constants are not defined
     */
    private static function requireConstants($constants, $dependentFunctionName) {
        if(!is_array($constants))
            $constants = array($constants);
        foreach($constants as $constant) {
            if(!defined($constant))
                throw new \Exception(__CLASS__ . "#$dependentFunctionName depends on undefined constant $constant");
        }
    }

    /**
     * SINGLETON FACTORY FUNCTIONS
     */

    /**
     * Make Slim app.
     * @return Slim
     */
    private static function app() {
        self::requireConstants(array('DIRECTUS_ENV','APPLICATION_PATH'), __FUNCTION__);
        $loggerSettings = array(
            'path' => APPLICATION_PATH . '/api/logs'
        );
        $app = new Slim(array(
            'mode'          => DIRECTUS_ENV,
            'debug'         => false,
            'log.enable'    => true,
            'log.writer'    => new DateTimeFileWriter($loggerSettings)
        ));
        return $app;
    }

    /**
     * Yield Slim logger
     * @return \Slim\Extras\Log\DateTimeFileWriter
     */
    private static function log() {
        return self::get('app')->getLog();
    }

    /**
     * Construct ZendDb adapter.
     * @param  array  $dbConfig
     * @return \Zend\Db\Adapter
     */
    private static function zenddb() {
        self::requireConstants(array('DIRECTUS_ENV','DB_HOST','DB_NAME','DB_USER','DB_PASSWORD'), __FUNCTION__);
        $dbConfig = array(
            'driver'    => 'Pdo_Mysql',
            'host'      => DB_HOST,
            'database'  => DB_NAME,
            'username'  => DB_USER,
            'password'  => DB_PASSWORD,
            'charset'   => 'utf8'
        );
        $ZendDb = new \Zend\Db\Adapter\Adapter($dbConfig);
        $connection = $ZendDb->getDriver()->getConnection();
        try { $connection->connect(); }
        catch(\PDOException $e) {
            echo "Database connection failed.<br />";
            self::get('log')->fatal(print_r($e, true));
            if('production' !== DIRECTUS_ENV)
                die(var_dump($e));
            die;
        }
        $dbh = $connection->getResource();
        $dbh->exec("SET CHARACTER SET utf8");
        $dbh->query("SET NAMES utf8");
        return $ZendDb;
    }

    /**
     * Construct old DB object
     * @return \DB
     */
    private static function olddb() {
        self::requireConstants('DB_NAME', __FUNCTION__);
        $ZendDb = self::get('ZendDb');
        $connection = $ZendDb->getDriver()->getConnection();
        $dbh = $connection->getResource();
        $db = new \DB($dbh, DB_NAME, $ZendDb);
        return $db;
    }

    /**
     * Construct Acl provider
     * @return \Directus\Acl
     */
    private static function acl() {
        $acl = new acl;
        if(AuthProvider::loggedIn()) {
            $currentUser = AuthProvider::getUserInfo();
            $ZendDb = self::get('ZendDb');
            $Users = new DirectusUsersTableGateway($acl, $ZendDb);
            $currentUser = $Users->find($currentUser['id']);
            if($currentUser) {
                $Privileges = new DirectusPrivilegesTableGateway($acl, $ZendDb);
                $groupPrivileges = $Privileges->fetchGroupPrivileges($currentUser['group']);
                $acl->setGroupPrivileges($groupPrivileges);
            }
        }
        return $acl;
    }

    /**
     * Construct CodeBird Twitter API Client
     * @return \Codebird\Codebird
     */
    private static function codebird() {
        $acl = self::get('acl');
        $ZendDb = self::get('ZendDb');
        // Social settings
        $SettingsTableGateway = new DirectusSettingsTableGateway($acl, $ZendDb);
        $requiredKeys = array('twitter_consumer_key','twitter_consumer_secret', 'twitter_oauth_token', 'twitter_oauth_token_secret');
        $socialSettings = $SettingsTableGateway->fetchCollection('social', $requiredKeys);
        // Codebird initialization
        \Codebird\Codebird::setConsumerKey($socialSettings['twitter_consumer_key']['value'], $socialSettings['twitter_consumer_secret']['value']);
        $cb = \Codebird\Codebird::getInstance();
        $cb->setToken($socialSettings['twitter_oauth_token']['value'], $socialSettings['twitter_oauth_token_secret']['value']);
        return $cb;
    }

    /**
     * Scan for extensions.
     * @return  array
     */
    private static function extensions() {
        self::requireConstants('APPLICATION_PATH', __FUNCTION__);
        $extensions = array();
        $extensionsDirectory = APPLICATION_PATH . '/extensions/';
        foreach (new \DirectoryIterator($extensionsDirectory) as $file) {
            if($file->isDot())
                continue;
            $extensionName = $file->getFilename();
            if(is_dir($extensionsDirectory . $extensionName))
                $extensions[$extensionName] = "extensions/$extensionName/main";
        }
        return $extensions;
    }

    /**
     * Scan for uis.
     * @return  array
     */
    private static function uis() {
        self::requireConstants('APPLICATION_PATH', __FUNCTION__);
        $uiDirectory = APPLICATION_PATH . '/ui';
        $uis = array();
        $objects = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uiDirectory), \RecursiveIteratorIterator::SELF_FIRST);
        foreach($objects as $name => $object){
            if("ui.js" === basename($name)) {
                $uiPath = substr($name, strlen(APPLICATION_PATH) + 1);
                $uiName = basename(dirname($name));
                $uis[$uiName] = substr($uiPath, 0, -3);
            }
        }
        return $uis;
    }

    /**
     * Construct Instagram API Client
     * @return \Codebird\Codebird
     */
    // private static function instagram() {
    //     $acl = self::get('acl');
    //     $ZendDb = self::get('ZendDb');
    //     // Social settings
    //     $SettingsTableGateway = new DirectusSettingsTableGateway($acl, $ZendDb);
    //     $requiredKeys = array('instagram_oauth_access_token','instagram_client_id');
    //     $socialSettings = $SettingsTableGateway->fetchCollection('social', $requiredKeys);
    //     $instagram = new \Instagram\Instagram;
    //     $instagram->setAccessToken($socialSettings['instagram_oauth_access_token']['value']);
    //     $instagram->setClientId($socialSettings['instagram_client_id']['value']);
    //     return $instagram;
    // }

}