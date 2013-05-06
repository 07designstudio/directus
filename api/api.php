<?php

// Initialization
//  - Apparently the autoloaders must be registered separately in both index.php and api.php

// Exceptional.io error handling
if(defined('EXCEPTIONAL_API_KEY')) {
    require_once 'vendor-manual/exceptional-php/exceptional.php';
    Exceptional::setup(EXCEPTIONAL_API_KEY);
}

// Composer Autoloader
require 'vendor/autoload.php';

// Directus Autoloader
use Symfony\Component\ClassLoader\UniversalClassLoader;
$loader = new UniversalClassLoader();
$loader->registerNamespace("Directus", dirname(__FILE__) . "/core/");
$loader->register();

// Non-autoload components
require dirname(__FILE__) . '/config.php';
require dirname(__FILE__) . '/core/db.php';
require dirname(__FILE__) . '/core/media.php';
require dirname(__FILE__) . '/core/functions.php';

// Define directus environment
defined('DIRECTUS_ENV')
    || define('DIRECTUS_ENV', (getenv('DIRECTUS_ENV') ? getenv('DIRECTUS_ENV') : 'production'));

switch (DIRECTUS_ENV) {
    case 'development_enforce_nonce':
    case 'development':
        break;
    case 'production':
    default:
        error_reporting(0);
        break;
}

use Directus\Auth\Provider as AuthProvider;
use Directus\Auth\RequestNonceProvider;
use Directus\Bootstrap;
use Directus\Db;
use Directus\Db\RowGateway\AclAwareRowGateway;
use Directus\Db\TableGateway\DirectusActivityTableGateway;
use Directus\Db\TableGateway\DirectusPreferencesTableGateway;
use Directus\Db\TableGateway\DirectusSettingsTableGateway;
use Directus\Db\TableGateway\DirectusUiTableGateway;
use Directus\Db\TableGateway\DirectusUsersTableGateway;
use Directus\Db\TableGateway\RelationalTableGateway as TableGateway;
use Directus\View\JsonView;
use Directus\View\ExceptionView;
use Zend\Db\Sql\Expression;

// API Version shortcut for routes:
$v = API_VERSION;

/**
 * Slim App & Directus Providers
 */

$app = Bootstrap::get('app');
$authProvider = new AuthProvider();
$requestNonceProvider = new RequestNonceProvider();

/**
 * Catch user-related exceptions & produce client responses.
 */

$app->config('debug', false);
$exceptionView = new ExceptionView();
$app->error(function (\Exception $exception) use ($app, $exceptionView) {
    $exceptionView->exceptionHandler($app, $exception);
});

// Routes which do not need protection by the authentication and the request
// nonce enforcement.
$authAndNonceRouteWhitelist = array(
    "auth_login",
    "auth_logout",
    "auth_session",
    "auth_clear_session",
    "auth_nonces",
    "auth_permissions",
    "debug_acl_poc",
);

$app->hook('slim.before.dispatch', function() use ($app, $authProvider, $requestNonceProvider, $authAndNonceRouteWhitelist) {
    /** Skip routes which don't require these protections */
    $routeName = $app->router()->getCurrentRoute()->getName();
    if(in_array($routeName, $authAndNonceRouteWhitelist))
        return;

    /** Enforce required authentication. */
    if(!$authProvider->loggedIn()) {
        $app->halt(401, "You must be logged in to access the API.");
    }

    /** Enforce required request nonces. */
    if(!$requestNonceProvider->requestHasValidNonce()) {
        if('development' !== DIRECTUS_ENV) {
            $app->halt(401, "Invalid request (nonce).");
        }
    }

    /** Include new request nonces in the response headers */
    $response = $app->response();
    $newNonces = $requestNonceProvider->getNewNoncesThisRequest();
    $nonce_options = $requestNonceProvider->getOptions();
    $response[$nonce_options['nonce_response_header']] = implode($newNonces, ",");
});


/**
 * Bootstrap Providers
 */

/**
 * @var \Zend\Db\Adapter
 */
$ZendDb = Bootstrap::get('ZendDb');

/**
 * Old \DB adapter
 * Transitional: initialize old and new until old is obsolete
 * @var \DB
 */
$db = Bootstrap::get('OldDb');

/**
 * @var \Directus\Acl
 */
$aclProvider = Bootstrap::get('AclProvider');

/**
 * Request Payload
 */

$params = $_GET;
$requestPayload = json_decode($app->request()->getBody(), true);

/**
 * Extension Alias
 */
if(isset($_REQUEST['run_extension']) && $_REQUEST['run_extension']) {
    // Validate extension name
    $extensionName = $_REQUEST['run_extension'];
    if(!Bootstrap::extensionExists($extensionName)) {
        header("HTTP/1.0 404 Not Found");
        return JsonView::render(array('message' => 'No such extension.'));
    }
    // Validate request nonce
    if(!$requestNonceProvider->requestHasValidNonce()) {
        if('development' !== DIRECTUS_ENV) {
            header("HTTP/1.0 401 Unauthorized");
            return JsonView::render(array('message' => 'Unauthorized (nonce).'));
        }
    }
    $extensionsDirectory = APPLICATION_PATH . "/extensions";
    $responseData = require "$extensionsDirectory/$extensionName/api.php";
    $nonceOptions = $requestNonceProvider->getOptions();
    $newNonces = $requestNonceProvider->getNewNoncesThisRequest();
    header($nonceOptions['nonce_response_header'] . ': ' . implode($newNonces, ","));
    return JsonView::render($responseData);
}

/**
 * Slim Routes
 * (Collections arranged alphabetically)
 */

/**
 * AUTHENTICATION
 */

$app->post("/$v/auth/login/?", function() use ($app, $ZendDb, $aclProvider, $authProvider, $requestNonceProvider) {
    $response = array(
        'message' => "Wrong username/password.",
        'success' => false,
        'all_nonces' => $requestNonceProvider->getAllNonces()
    );
    if($authProvider::loggedIn()) {
        $response['success'] = true;
        return JsonView::render($response);
    }
    $req = $app->request();
    $email = $req->post('email');
    $password = $req->post('password');
    $Users = new DirectusUsersTableGateway($aclProvider, $ZendDb);
    $user = $Users->findOneBy('email', $email);
    if(!$user) {
        return JsonView::render($response);
    }
    $response['success'] = $authProvider
        ->login($user['id'], $user['password'], $user['salt'], $password);

    if($response['success']) {
        unset($response['message']);
        $set = array('last_login' => new Expression('NOW()'));
        $where = array('id' => $user['id']);
        $updateResult = $Users->update($set, $where);
    }
    JsonView::render($response);
})->name('auth_login');

$app->get("/$v/auth/logout/?", function() use ($app, $authProvider) {
    if($authProvider::loggedIn())
        $authProvider::logout();
    $app->redirect(DIRECTUS_PATH . "login.php");
})->name('auth_logout');

$app->get("/$v/auth/nonces/?", function() use ($app, $requestNonceProvider) {
    $all_nonces = $requestNonceProvider->getAllNonces();
    $response = array('nonces' => $all_nonces);
    JsonView::render($response);
})->name('auth_nonces');

// debug helper
$app->get("/$v/auth/session/?", function() use ($app) {
    if('production' === DIRECTUS_ENV)
        $app->halt('404');
    JsonView::render($_SESSION);
})->name('auth_session');

// debug helper
$app->get("/$v/auth/clear-session/?", function() use ($app) {
    if('production' === DIRECTUS_ENV)
        $app->halt('404');
    // Example #1 - http://php.net/manual/en/function.session-destroy.php
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    JsonView::render($_SESSION);
})->name('auth_clear_session');

// debug helper
$app->get("/$v/auth/permissions/?", function() use ($app, $aclProvider) {
    if('production' === DIRECTUS_ENV)
        $app->halt('404');
    $groupPrivileges = $aclProvider->getGroupPrivileges();
    JsonView::render(array('groupPrivileges' => $groupPrivileges));
})->name('auth_permissions');

/**
 * Debug helper.
 * For development & proof-oc-concept of ACL.
 */
$app->get("/$v/debug/acl_poc/?", function() use ($app, $aclProvider, $ZendDb) {
    if('production' === DIRECTUS_ENV)
        $app->halt('404');
    $DirectusActivityTableGateway = new DirectusActivityTableGateway($aclProvider, $ZendDb);
    // $DirectusActivityTableGateway->testInsertWriteBlacklistEnforcement();
    // $DirectusActivityTableGateway->testUpdateWriteBlacklistEnforcement();
    // $DirectusActivityTableGateway->testInsertAddEnforcement();
    // $DirectusActivityTableGateway->testUpdateBigEditEnforcementWithMagicOwnerColumn();
    // $DirectusActivityTableGateway->testUpdateBigEditEnforcementWithMagicOwnerColumnAndMultipleOwners();
    $DirectusActivityTableGateway->testUpdateBigEditEnforcementWithoutMagicOwnerColumn();
})->name('debug_acl_poc');

/**
 * ENTRIES COLLECTION
 */

$app->map("/$v/tables/:table/rows/?", function ($table) use ($db, $aclProvider, $ZendDb, $params, $requestPayload, $app) {
    $currentUser = AuthProvider::getUserInfo();
    $id = null;
    $params['table_name'] = $table;
    $TableGateway = new TableGateway($aclProvider, $table, $ZendDb);

    /**
     * Tmp hack for password bug.
     * AARG#preSaveDataHook is insufficient for this.
     * We need back-end form processing :)
     */
    if("directus_users" === $table && in_array($app->request()->getMethod(), array('PUT','POST'))) {
        $users = $requestPayload;
        if(!is_numeric_array($users))
            $users = array($users);
        foreach($users as $user) {
            if(!isset($user['password']) || empty($user['password']))
                continue;
            $Users = new DirectusUsersTableGateway($aclProvider, $ZendDb);
            $UserRow = null;
            $isNew = !(isset($user['id']) && !empty($user['id']));
            // Salt can't be written by client
            if($isNew) {
                $user['salt'] = uniqid();
            } else {
                $UserRow = $Users->find($user['id']);
                if(false === $UserRow)
                    $app->halt('404', 'No such user with ID ' . $user['id']);
                $user['salt'] = $UserRow['salt'];
            }
            $user['password'] = AuthProvider::hashPassword($user['password'], $user['salt']);
            $where = array('id' => $user['id']);
            $Users->update($user, $where);
        }
    }

    switch($app->request()->getMethod()) {
        // POST one new table entry
        case 'POST':
            // $id = $db->set_entry_relational($table, $requestPayload);
            $activityLoggingEnabled = !(isset($_GET['skip_activity_log']) && (1 == $_GET['skip_activity_log']));
            $newRecord = $TableGateway->manageRecordUpdate($table, $requestPayload, $activityLoggingEnabled);
            $params['id'] = $newRecord['id'];
            break;
        // PUT a change set of table entries
        case 'PUT':
            // $db->set_entries($table, $requestPayload);
            if(!is_numeric_array($requestPayload)) {
                $params['id'] = $requestPayload['id'];
                $requestPayload = array($requestPayload);
            }
            $TableGateway->updateCollection($requestPayload);
            break;
    }
    // GET all table entries
    $get_old = $db->get_entries($table, $params);
    $Table = new TableGateway($aclProvider, $table, $ZendDb);
    $get_new = $Table->getEntries($params);
    JsonView::render($get_new, $get_old);
})->via('GET', 'POST', 'PUT');

$app->map("/$v/tables/:table/rows/:id/?", function ($table, $id) use ($db, $ZendDb, $aclProvider, $params, $requestPayload, $app) {
    $currentUser = AuthProvider::getUserInfo();
    $params['table_name'] = $table;
    $TableGateway = new TableGateway($aclProvider, $table, $ZendDb);
    switch($app->request()->getMethod()) {
        // PUT an updated table entry
        case 'PATCH':
        case 'PUT':
            $requestPayload['id'] = $id;
            $activityLoggingEnabled = !(isset($_GET['skip_activity_log']) && (1 == $_GET['skip_activity_log']));
            $TableGateway->manageRecordUpdate($table, $requestPayload, $activityLoggingEnabled);
            break;
        // DELETE a given table entry
        case 'DELETE':
            // @todo need to find a place where this actually occurs in the pre-existing application
            echo $db->delete($table, $id);
            // @todo then confirm this will have identical output:
            // $row = $TableGateway->find($id);
            // $row->delete();
            return;
    }
    $params['id'] = $id;
    // GET a table entry
    $get_old = $db->get_entries($table, $params);
    $Table = new TableGateway($aclProvider, $table, $ZendDb);
    $get_new = $Table->getEntries($params);
    JsonView::render($get_new, $get_old);
})->via('DELETE', 'GET', 'PUT','PATCH');

/**
 * ACTIVITY COLLECTION
 */

$app->get("/$v/activity/?", function () use ($db, $params, $ZendDb, $aclProvider) {
    $Activity = new DirectusActivityTableGateway($aclProvider, $ZendDb);
    $new_get = $Activity->fetchFeed($params);
    $old_get = $db->get_activity();
    JsonView::render($new_get, $old_get);
});

/**
 * COLUMNS COLLECTION
 */

// GET all table columns, or POST one new table column

$app->map("/$v/tables/:table/columns/?", function ($table) use ($db, $params, $requestPayload, $app) {
    $params['table_name'] = $table;
    if($app->request()->isPost()) {
        /* @TODO improves readability: use two separate methods for fetching one vs all entries */
        $params['column_name'] = $db->add_column($table, $requestPayload); // NOTE Alters the behavior of db#get_table below
    }
    $response = $db->get_table($table, $params);
    JsonView::render($response);
})->via('GET', 'POST');

// GET or PUT one column

$app->map("/$v/tables/:table/columns/:column/?", function ($table, $column) use ($db, $ZendDb, $aclProvider, $params, $requestPayload, $app) {
    $params['column_name'] = $column;
    $params['table_name'] = $table;
    // Add table name to dataset. @TODO more clarification would be useful
    foreach ($requestPayload as &$row) {
        $row['table_name'] = $table;
    }
    if($app->request()->isPut()) {
        // $db->set_entries('directus_columns', $requestPayload);
        $TableGateway = new TableGateway($aclProvider, 'directus_columns', $ZendDb);
        $TableGateway->updateCollection($requestPayload);
    }
    $response = $db->get_table($table, $params);
    JsonView::render($response);
})->via('GET', 'PUT');

/**
 * GROUPS COLLECTION
 */

/** (Optional slim route params break when these two routes are merged) */

$app->get("/$v/groups/?", function () use ($db, $ZendDb, $aclProvider) {
    // @TODO need POST and PUT
    $get_old = $db->get_entries("directus_groups");
    $Groups = new TableGateway($aclProvider, 'directus_groups', $ZendDb);
    $get_new = $Groups->getEntries();
    JsonView::render($get_new, $get_old);
});

$app->get("/$v/groups/:id/?", function ($id = null) use ($db, $ZendDb, $aclProvider) {
    // @TODO need POST and PUT
    // Hardcoding ID temporarily
    is_null($id) ? $id = 1 : null;
    $get_old = $db->get_group($id);
    $Groups = new TableGateway($aclProvider, 'directus_groups', $ZendDb);
    $get_new = $Groups->find($id);
    JsonView::render($get_new, $get_old);
});

/**
 * MEDIA COLLECTION
 */

$app->map("/$v/media(/:id)/?", function ($id = null) use ($app, $db, $ZendDb, $aclProvider, $params, $requestPayload) {

    if(!is_null($id))
        $params['id'] = $id;

    // A URL is specified. Upload the file
    if (isset($requestPayload['url']) && $requestPayload['url'] != "") {
        $media = new Media($requestPayload['url'], RESOURCES_PATH);
        $media_data = $media->data();
        $requestPayload['type'] = $media_data['type'];
        $requestPayload['charset'] = $media_data['charset'];
        $requestPayload['size'] = $media_data['size'];
        $requestPayload['width'] = $media_data['width'];
        $requestPayload['height'] = $media_data['height'];
        $requestPayload['name'] = $media_data['name'];
        $requestPayload['date_uploaded'] = $media_data['date_uploaded'];
        if (isset($media_data['embed_id'])) {
            $requestPayload['embed_id'] = $media_data['embed_id'];
        }
    }

    if (isset($requestPayload['url']))
        unset($requestPayload['url']);


    $currentUser = AuthProvider::getUserInfo(); 

    $table = "directus_media";
    switch ($app->request()->getMethod()) {
        case "POST":
            $requestPayload['date_uploaded'] = gmdate('Y-m-d H:i:s');
            $params['id'] = $db->set_media($requestPayload);
            break;
        case "PATCH":
            $requestPayload['id'] = $id;
        case "PUT":
            if (!is_null($id)) {
                // $db->set_entries($table, $requestPayload);
                $TableGateway = new TableGateway($aclProvider, $table, $ZendDb);
                $activityLoggingEnabled = !(isset($_GET['skip_activity_log']) && (1 == $_GET['skip_activity_log']));
                $TableGateway->manageRecordUpdate($table, $requestPayload, $activityLoggingEnabled);
                break;
            }
            $db->set_media($requestPayload);
            break;
    }

    $get_old = $db->get_entries($table, $params);
    $Media = new TableGateway($aclProvider, $table, $ZendDb);
    $get_new = $Media->getEntries($params);
    JsonView::render($get_new, $get_old);
})->via('GET','PATCH','POST','PUT');

/**
 * PREFERENCES COLLECTION
 */

$app->map("/$v/tables/:table/preferences/?", function($table) use ($db, $ZendDb, $aclProvider, $params, $requestPayload, $app) {
    $currentUser = AuthProvider::getUserInfo();
    $params['table_name'] = $table;
    switch ($app->request()->getMethod()) {
        case "PUT":
            //This data should not be hardcoded.
            $id = $requestPayload['id'];
            $db->set_entry('directus_preferences', $requestPayload);
            // $db->insert_entry($table, $requestPayload, $id);
            break;
        case "POST":
            // This should not be hardcoded, needs to be corrected
            $requestPayload['user'] = $currentUser['id'];
            $id = $db->insert_entry($table, $requestPayload);
            $params['id'] = $id;
            break;
    }
    $currentUser = AuthProvider::getUserInfo();
    $get_old = $db->get_table_preferences($currentUser['id'], $table);
    $Preferences = new DirectusPreferencesTableGateway($aclProvider, $ZendDb);
    $get_new = $Preferences->fetchByUserAndTable($currentUser['id'], $table);
    JsonView::render($get_new, $get_old);
})->via('GET','POST','PUT');

/**
 * REVISIONS COLLECTION
 */

$app->get("/$v/tables/:table/rows/:id/revisions/?", function($table, $id) use ($db, $aclProvider, $ZendDb, $params) {
    $params['table_name'] = $table;
    $params['id'] = $id;
    $get_old = $db->get_revisions($params);
    $Activity = new DirectusActivityTableGateway($aclProvider, $ZendDb);
    $get_new = $Activity->fetchRevisions($id, $table);
    JsonView::render($get_new, $get_old);
});

/**
 * SETTINGS COLLECTION
 */

$app->map("/$v/settings(/:id)/?", function ($id = null) use ($db, $aclProvider, $ZendDb, $params, $requestPayload, $app) {
    switch ($app->request()->getMethod()) {
        case "POST":
        case "PUT":
            $db->set_settings($requestPayload);
            break;
    }

    $settings_old = $db->get_settings();
    $get_old = is_null($id) ? $settings_old : $settings_old[$id];

    $Settings = new DirectusSettingsTableGateway($aclProvider, $ZendDb);
    $settings_new = $Settings->fetchAll();
    $get_new = is_null($id) ? $settings_new : $settings_new[$id];

    JsonView::render($get_new, $get_old);
})->via('GET','POST','PUT');

/**:
 * TABLES COLLECTION
 */

// GET table index
$app->get("/$v/tables/?", function () use ($db, $params, $requestPayload) {
    $response = $db->get_tables($params);
    JsonView::render($response);
})->name('table_index');

// GET and PUT table details
$app->map("/$v/tables/:table/?", function ($table) use ($db, $ZendDb, $aclProvider, $params, $requestPayload, $app) {
    /* PUT updates the table */
    if($app->request()->isPut()) {
        $db->set_table_settings($requestPayload);
    }
    $response = $db->get_table_info($table, $params);
    JsonView::render($response);
})->via('GET', 'PUT')->name('table_meta');

/**
 * UPLOAD COLLECTION
 */

$app->post("/$v/upload/?", function () use ($db, $params, $requestPayload, $app) {
    $result = array();
    foreach ($_FILES as $file) {
      $media = new Media($file, RESOURCES_PATH);
      array_push($result, $media->data());
    }
    JsonView::render($result);
});

/**
 * UI COLLECTION
 */

$app->map("/$v/tables/:table/columns/:column/:ui/?", function($table, $column, $ui) use ($db, $aclProvider, $ZendDb, $params, $requestPayload, $app) {
    $params['table_name'] = $table;
    $params['column_name'] = $column;
    $params['ui_name'] = $ui;
    switch ($app->request()->getMethod()) {
      case "PUT":
      case "POST":
        $db->set_ui_options($requestPayload, $table, $column, $ui);
        break;
    }
    $get_old = $db->get_ui_options($table, $column, $ui);
    $UiOptions = new DirectusUiTableGateway($aclProvider, $ZendDb);
    $get_new = $UiOptions->fetchOptions($table, $column, $ui);
    JsonView::render($get_old, $get_new);
})->via('GET','POST','PUT');

/**
 * Run the Router
 */

if(isset($_GET['run_api_router']) && $_GET['run_api_router']) {
    // Run Slim
    $app->response()->header('Content-Type', 'application/json; charset=utf-8');
    $app->run();
}
