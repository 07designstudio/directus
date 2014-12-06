<?php
ob_start();
session_start();
$code = 0;
$bad_paths = array();

if(isset($_SESSION['step'])) {
  $step = $_SESSION['step'];
} else {
  $step = 0;
}

if(isset($_POST['backButton'])) {
  if($step > 0) {
    $step--;
    $_SESSION['step'] = $step;
  }
}


if($step == 0 && isset($_POST['start'])) {
  $_SESSION['step'] = 1;
  $step = 1;
}

if($step == 1 && isset($_POST['email']) && isset($_POST['site_name']) && isset($_POST['password']) && isset($_POST['password_confirm'])) {
  if(!empty($_POST['email']) && !empty($_POST['site_name']) && !empty($_POST['password']) && !empty($_POST['directus_path'])) {
    if($_POST['password'] == $_POST['password_confirm'] && strlen($_POST['password']) > 0) {
      $_SESSION['email'] = $_POST['email'];
      $_SESSION['site_name'] = $_POST['site_name'];
      $_SESSION['password'] = $_POST['password'];
      $_SESSION['directus_path'] = $_POST['directus_path'];
      $_SESSION['step'] = 2;
      $step = 2;
    }
  }
}

if($step == 2 && isset($_POST['host_name']) && isset($_POST['username']) && isset($_POST['db_name'])) {
  //Check for db connection
  ini_set('display_errors', 0);
  $conn = mysqli_init();
  mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
  $connection = mysqli_real_connect($conn, $_POST['host_name'], $_POST['username'], $_POST['password'], $_POST['db_name']);
  $_SESSION['host_name'] = $_POST['host_name'];
  $_SESSION['username'] = $_POST['username'];
  $_SESSION['db_password'] = $_POST['password'];
  $_SESSION['db_name'] = $_POST['db_name'];
  $_SESSION['db_prefix'] = $_POST['db_prefix'];
  if(isset($_POST['install_sample'])) {
    $_SESSION['install_sample'] = $_POST['install_sample'];
  } else {
    $_SESSION['install_sample'] = "no";
  }
  if($connection) {
    $_SESSION['step'] = 3;
    $step = 3;
  } else {
    $code = mysqli_connect_errno();
  }
}

if($step == 4 && isset($_POST['install'])) {
  if(isset($_POST['send_config_email'])) {
    $_SESSION['send_config_email'] = $_POST['send_config_email'];
  } else {
    $_SESSION['send_config_email'] = "no";
  }
  $_SESSION['step'] = 5;
  $step = 5;
}

if($step == 3 && isset($_POST['default_dest'])) {
  if(isset($_POST['default_url']) && isset($_POST['thumb_dest']) && isset($_POST['thumb_url']) && isset($_POST['temp_dest']) && isset($_POST['temp_url'])) {
    $_SESSION['default_dest'] = $_POST['default_dest'];
    $_SESSION['default_url'] = $_POST['default_url'];
    $_SESSION['thumb_dest'] = $_POST['thumb_dest'];
    $_SESSION['thumb_url'] = $_POST['thumb_url'];
    $_SESSION['temp_dest'] = $_POST['temp_dest'];
    $_SESSION['temp_url'] = $_POST['temp_url'];

    $good = true;
    if(!file_exists($_SESSION['default_dest'])) {
      array_push($bad_paths, 'default_dest');
    }
    if(!file_exists($_SESSION['thumb_dest'])) {
      array_push($bad_paths, 'thumb_dest');
    }
    if(!file_exists($_SESSION['temp_dest'])) {
      array_push($bad_paths, 'temp_dest');
    }

    if(empty($bad_paths)) {
      $_SESSION['step'] = 4;
      $step = 4;
    }
  }
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

  <title>Install Directus</title>
  <meta name="description" content="Directus">
  <meta name="author" content="RANGER Studio LLC">

  <link rel='shortcut icon' type='image/x-icon' href='/favicon.ico' />
  <link href='http://fonts.googleapis.com/css?family=Open+Sans:300,400,600' rel='stylesheet' type='text/css'>
  <link rel="stylesheet" href="install.css?v=1.0">
<style>.error{
  border: 1px solid red!important;;
  }</style>
</head>
<body>
  <form name="input" action="index.php" method="post">
    <!--
      <div>Default Adapter Destination</div>
      <input class="bad" value="">
    -->


<?php
if($step == 0) {
  ?>
  <div class="header">
    <div class="container">
      <img src="directus-logo.gif">
      <div>Install Directus</div>
    </div>
  </div>

  <div class="body">
    <div class="container intro">
      Welcome to Directus, a free and open source content management framework written in Backbone.js that provides a feature-rich environment
      for rapid development and management of custom SQL database solutions. Directus makes no assumptions about how you should architect your
      schema – giving you the freedom to tailor the database to your specific project needs and provide an intuitive, one-to-one interface to
      your users. And instead of encompassing your entire project, Directus focuses on a lightweight core suite designed to integrate with the
      frameworks already in your workflow.
      <input type="hidden" name="start" value="true">
      <button type="submit" class="button primary">Get Started</button>
  <?php
}

if($step == 1) {
    $error = null;
  if (version_compare(PHP_VERSION, '5.4.10', '<')) {
    $error = 'Your host needs to use PHP 5.4.10 or higher to run this version of Directus!';
  }

  if (!defined('PDO::ATTR_DRIVER_NAME')) {
    $error = 'Your host needs to have PDO enabled to run this version of Directus!';
  }

  if (!extension_loaded('gd') || !function_exists('gd_info')) {
    $error = 'Your host needs to have GD Library enabled to run this version of Directus!';
  }

  if($error) {
    ?>
    <div class="header">
        <div class="container">
          <img src="directus-logo.gif">
          <div>Missing Requirements</div>
        </div>
      </div>

      <div class="body">
        <div class="container">
          <h2><?php echo($error); ?></h2>
  <?php
  die();
  } else {
    $directus_path = preg_replace('#/(installation/.*)#i', '', $_SERVER['REQUEST_URI']) . '/';
  ?>
  <div class="header">
    <div class="container">
      <img src="directus-logo.gif">
      <div>Project Info</div>
    </div>
  </div>

  <div class="body">
    <div class="container">

  Project Name<input type="text" name="site_name" value="<?php echo(isset($_SESSION['site_name']) ? $_SESSION['site_name'] : ''); ?>"><br>
  Project Path<input type="text" name="directus_path" value="<?php echo(isset($_SESSION['directus_path']) ? $_SESSION['directus_path'] : $directus_path); ?>"><br>
  Admin Email<input type="email" name="email" value="<?php echo(isset($_SESSION['email']) ? $_SESSION['email'] : ''); ?>"><br>
  Admin Password<input type="password" name="password" value="<?php echo(isset($_SESSION['password']) ? $_SESSION['password'] : ''); ?>"><br>
  Confirm Admin Password<input type="password" name="password_confirm" value="<?php echo(isset($_SESSION['password']) ? $_SESSION['password'] : ''); ?>"><br>

<?php
  }
}

if($step == 2) {
?>
  <div class="header">
    <div class="container">
      <img src="directus-logo.gif">
      <div>Database Configuration</div>
    </div>
  </div>
  <div class="body">
    <div class="container">
        Host Name<input type="text" class="<?php if($code == 2002){echo "error";}?>" name="host_name" value="<?php echo(isset($_SESSION['host_name']) ? $_SESSION['host_name'] : ''); ?>"><br>
        Username<input type="text" class="<?php if($code == 1045){echo "error";}?>" name="username" value="<?php echo(isset($_SESSION['username']) ? $_SESSION['username'] : ''); ?>"><br>
        Password<input type="password" class="<?php if($code == 1045){echo "error";}?>" name="password" value="<?php echo(isset($_SESSION['db_password']) ? $_SESSION['db_password'] : ''); ?>"><br>
        Database Name<input type="text" class="<?php if($code == 1049){echo "error";}?>" name="db_name" value="<?php echo(isset($_SESSION['db_name']) ? $_SESSION['db_name'] : ''); ?>"><br>
        Database Prefix (optional)<input type="text" name="db_prefix" value="<?php echo(isset($_SESSION['db_prefix']) ? $_SESSION['db_prefix'] : ''); ?>"><br>
        <input type="checkbox" name="install_sample" value="yes" <?php echo(isset($_SESSION['install_sample']) && $_SESSION['install_sample'] == 'yes' ? 'checked' : ''); ?>>Install Sample Data<br>
<?php
}

if($step == 3) {
  $abspath = dirname( dirname(__FILE__) ) . '/';
  $isHTTPS = false;
  if ((isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS'])) ||
      (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
    $isHTTPS = true;
  }

  $site_url = ($isHTTPS) ? "https://" : "http://" . $_SERVER['HTTP_HOST'] . $_SESSION['directus_path'];
  ?>
  <div class="header">
    <div class="container">
      <img src="directus-logo.gif">
      <div>Storage Adapter Setup</div>
    </div>
  </div>
  <div class="body">
    <div class="container">
      <?php if(count($bad_paths) > 0) {
        echo("<div style='color:#FF4C4C'>Error: Paths dont have Write Permissions.</div><br/>");
      } ?>
      Default Adapter Destination<input type="text" class="<?php if(in_array("default_dest", $bad_paths)){echo "error";}?>" name="default_dest" value="<?php echo(isset($_SESSION['default_dest']) ? $_SESSION['default_dest'] : $abspath.'media/'); ?>" placeholder="/var/www/media/"><br>
      Default Adapter URL<input type="text" name="default_url" value="<?php echo(isset($_SESSION['default_url']) ? $_SESSION['default_url'] : $site_url.'media/'); ?>" placeholder="http://localhost/media/"><br>
      Thumbnail Adapter Destination<input type="text" class="<?php if(in_array("thumb_dest", $bad_paths)){echo "error";}?>" name="thumb_dest" value="<?php echo(isset($_SESSION['thumb_dest']) ? $_SESSION['thumb_dest'] : $abspath.'media/thumbs/'); ?>" placeholder="/var/www/media/thumbs/"><br>
      Thumbnail Adapter URL<input type="text" name="thumb_url" value="<?php echo(isset($_SESSION['thumb_url']) ? $_SESSION['thumb_url'] : $abspath.'media/thumbs/'); ?>" placeholder="http://localhost/media/thumb/"><br>
      Temp Adapter Destination<input type="text" name="temp_dest" class="<?php if(in_array("temp_dest", $bad_paths)){echo "error";}?>" value="<?php echo(isset($_SESSION['temp_dest']) ? $_SESSION['temp_dest'] : $abspath.'media/temp/'); ?>" placeholder="/var/www/media/temp/"><br>
      Temp Adapter URL<input type="text" name="temp_url" value="<?php echo(isset($_SESSION['temp_url']) ? $_SESSION['temp_url'] : $site_url.'media/temp/'); ?>" placeholder="http://localhost/media/temp/"><br>
<?php
}

if($step == 4) {
?>
  <div class="header">
    <div class="container">
      <img src="directus-logo.gif">
      <div>Confirmation</div>
    </div>
  </div>

  <div class="body">
    <div class="container summary">
      <h3>Main Configuration</h3>
      <hr>
      <table>
        <tbody>
          <tr>
            <td class="item">Site Name</td>
            <td class="result"><?php echo $_SESSION['site_name'];?></td>
          </tr>
          <tr>
            <td class="item">Admin Email</td>
            <td class="result"><span><?php echo $_SESSION['email'];?></span>
            </td>
          </tr>
          <tr>
            <td class="item">Admin Password</td>
            <td class="result">***</td>
          </tr>
        </tbody>
      </table>

      <h3>Database Configuration</h3>
      <hr>
      <table>
        <tbody>
          <tr>
            <td class="item">Host Name</td>
            <td class="result"><?php echo $_SESSION['host_name'];?></td>
          </tr>
          <tr>
            <td class="item">Username</td>
            <td class="result"><span><?php echo $_SESSION['username'];?></span></td>
          </tr>
          <tr>
            <td class="item">Password</td>
            <td class="result">***</td>
          </tr>
          <tr>
            <td class="item">Database Name</td>
            <td class="result"><?php echo $_SESSION['db_name'];?></td>
          </tr>
          <tr>
            <td class="item">Database Prefix</td>
            <td class="result"><?php echo(isset($_SESSION['db_prefix']) && !empty($_SESSION['db_prefix']) ? $_SESSION['db_prefix'] : '--');?></td>
          </tr>
        </tbody>
      </table>

      <h3>Pre-Installation Check</h3>
      <hr>
      <table>
        <tbody>
          <tr>
            <td class="item">PHP Version >= 5.5.0</td>
            <td class="result"><span class="label label-success">Yes</span></td>
          </tr>
          <tr>
            <td class="item">Database Support</td>
            <td class="result"><span class="label label-success">Yes</span></td>
          </tr>
          <tr>
            <td class="item">GD Support</td>
            <td class="result"><span class="label label-success">Yes</span></td>
          </tr>
          <tr>
            <td class="item">Logs Writable (../api/logs/)</td>
            <td class="result"><?php if(is_writable('../api/logs')) {echo('<span class="label label-success">Yes</span>');}else{echo('<span class="label label-important">No</span>');}?></td>
          </tr>
          <tr>
            <td class="item">mod_rewrite Enabled</td>
            <td class="result"><?php if(in_array('mod_rewrite', apache_get_modules())) {echo('<span class="label label-success">Yes</span>');}else{echo('<span class="label label-important">No</span> Please Make Enable mod_rewrite');}?></td>
          </tr>
          <tr>
            <td class="item">Config Writable (../api/config.php)</td>
            <td class="result"><?php if(is_writable('../api/config.php')) {$showConfig = false; echo('<span class="label label-success">Yes</span>');}else{$showConfig = true; echo('<span class="label label-important">No</span>');}?></td>
          </tr>
        </tbody>
      </table>

      <?php
      if($showConfig) {
        require_once('config_setup.php');
        echo("<span class='config-paste label label-important'>Manually copy the code below into ../api/config.php</span><br><textarea readonly>$configText</textarea><span id='failSpan'><button id='retryButton' class='button'>Check Config File</button></span>");
      }
      ?>

      <h3>Reccommended Optional Features</h3>
      <hr>
      <table>
        <tbody>
          <tr>
            <td class="item">Imagick PHP Extension<br>For TIFF, PSD, and PDF thumbnails</td>
            <td class="result"><?php if(extension_loaded('imagick')) {echo('<span class="label label-success">Yes</span>');} else {echo('<span class="label label-important">No</span>');}?></td>
          </tr>
        </tbody>
      </table>

      <h3>Email This Summary?</h3>
      <hr>
      <table>
        <tbody>
          <tr>
            <td class="item"><?php echo $_SESSION['email'];?></td>
            <td class="result"><input type="checkbox" value="yes" name="send_config_email" checked></td>
          </tr>
        </tbody>
      </table>
<?php
}

if($step == 5) {
  require_once('query.php');
  CreateTables($create_statements,$mysqli);
  RunInserts($insert_statements, $mysqli);
  AddDefaultUser($_SESSION['email'], $_SESSION['password'], $mysqli);
  AddStorageAdapters($mysqli);
  if(isset($_SESSION['install_sample']) && $_SESSION['install_sample'] == "yes") {
    InstallSampleData($mysqli);
  }
  if(isset($_SESSION['send_config_email']) && $_SESSION['send_config_email'] == "yes") {
        require_once('config_setup.php');
    $mailBody = '<html><h3>Main Configuration</h3>
      <hr>
      <table>
        <tbody>
          <tr>
            <td class="item">Site Name</td>
            <td class="result">'.$_SESSION['site_name'].'</td>
          </tr>
          <tr>
            <td class="item">Admin Email</td>
            <td class="result"><span>'.$_SESSION['email'].'</span>
            </td>
          </tr>
          <tr>
            <td class="item">Admin Password</td>
            <td class="result">***</td>
          </tr>
        </tbody>
      </table>

      <h3>Database Configuration</h3>
      <hr>
      <table>
        <tbody>
          <tr>
            <td class="item">Host Name</td>
            <td class="result">'.$_SESSION['host_name'].'</td>
          </tr>
          <tr>
            <td class="item">Username</td>
            <td class="result"><span>'.$_SESSION['username'].'</span></td>
          </tr>
          <tr>
            <td class="item">Password</td>
            <td class="result">***</td>
          </tr>
          <tr>
            <td class="item">Database Name</td>
            <td class="result">'.$_SESSION['db_name'].'</td>
          </tr>
          <tr>
            <td class="item">Database Prefix</td>
            <td class="result">'.$_SESSION['db_prefix'].'</td>
          </tr>
        </tbody>
      </table>

    <h3>Config File</h3><textarea>'.$configText.'</textarea></html>';
      $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
    mail($_SESSION['email'], "Directus Install Config Overview", $mailBody, $headers);
  }
  $mysqli->close();

  require_once('config_setup.php');
  WriteConfig();

  // @TODO: put all this data into an array.
  // so we can clear all session unset($_SESSION['installation']);
  $install_data = array(
    'step',
    'email',
    'site_name',
    'password',
    'directus_path',
    'host_name',
    'username',
    'db_password',
    'db_name',
    'db_prefix',
    'install_sample',
    'default_dest',
    'default_url',
    'thumb_dest',
    'thumb_url',
    'temp_dest',
    'temp_url',
    'send_config_email'
  );

  foreach($_SESSION as $key => $value) {
    if (in_array($key, $install_data)) {
      unset($_SESSION[$key]);
    }
  }

  header('Location: ../');
}

?>
<script>var step = <?php echo($step); ?>;</script>
      </div>
    </div>

    <div class="footer">
      <div class="container">
        <button type="submit" class="button right<?PHP if($step == 0){echo " hide";}?> primary disabled">Continue</button>
        <button name="backButton" class="button left<?PHP if($step == 0){echo " hide";}?>">Back</button>

        <div class="breadcrumb">
          <span class="<?PHP if($step == 1){echo "current";} elseif($step > 1){echo "complete";}?>">Project Info</span>
          <span class="separator icon icon-chevron-right"></span>
          <span class="<?PHP if($step == 2){echo "current";} elseif($step > 2){echo "complete";}?>">Database</span>
          <span class="separator icon icon-chevron-right"></span>
          <span class="<?PHP if($step == 3){echo "current";} elseif($step > 3){echo "complete";}?>">Storage Adapters</span>
          <span class="separator icon icon-chevron-right"></span>
          <span class="<?PHP if($step == 4){echo "current";} elseif($step > 4){echo "complete";}?>">Confirmation</span>
        </div>
      </div>
    </div>
  </form>

  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
  <script type="text/javascript" src="install.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>