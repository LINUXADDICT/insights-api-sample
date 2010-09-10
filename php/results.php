<?php

require_once 'lib.php';

function get_index($token) {
  $params = array();
  $params['access_token'] = $token;
  $params['method'] = 'users.getLoggedInUser';
  $url = FacebookMethods::getRestApiUrl();
  $user = FacebookMethods::fetchUrl($url, $params);

  $params['method'] = 'fql.query';
  $params['query'] = 'SELECT page_id FROM page_admin '
    . 'WHERE uid = ' . $user;
  $pages = FacebookMethods::fetchUrl($url, $params);
  $params['query'] = 'SELECT application_id FROM developer '
    . 'WHERE developer_id = ' . $user;
  $apps = FacebookMethods::fetchUrl($url, $params);
  $ids = array();
  foreach ($pages as $page) {
    $ids[] = $page['page_id'];
  }
  foreach ($apps as $app) {
    $ids[] = $app['application_id'];
  }
  $url = FacebookMethods::getGraphApiUrl();
  $graph_params = array(
    'access_token' => $token,
    'ids' => implode(',', $ids),
    'method' => 'GET',
    );
  $profiles = FacebookMethods::fetchUrl($url, $graph_params);
  return $profiles;
}

function get_insights($token, $id, $start) {
  $start->modify('+1 day');
  $path = $id . '/insights';
  $url = FacebookMethods::getGraphApiUrl($path);
  $params = array(
    'access_token' => $token,
    'method' => 'GET',
    'since' => $start->format('Y-m-d'),
  );
  $start->modify('+1 day');
  $params['until'] = $start->format('Y-m-d');
  $insights = FacebookMethods::fetchUrl($url, $params);
  return $insights;
}


$token = $_REQUEST['access_token'];
$start = new DateTime($_REQUEST['date']);
if (isset($_REQUEST['id'])) {
  $id = $_REQUEST['id'];
  $insights = get_insights($token, $id, $start);
  header('Content-Type: text/plain');
  echo "object_id,metric,end_time,period,value\n";
  foreach ($insights['data'] as $metric) {
    foreach ($metric['values'] as $row) {
      $split = explode('/', $metric['id']);
      $date_str = explode('T', $row['end_time']);
      $date = new DateTime($date_str[0]);
      $date->modify('-1 day');
      $value = $row['value'];
      if (is_array($row['value']))
        $value = implode(' ', $row['value']);
      echo "{$split[0]},{$metric['name']},{$date->format('Y-m-d')},"
        . "{$metric['period']},{$value}\n";
    }
  }
} else {
  $profiles = get_index($token);
  // Render the index.
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
    "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >

  <head>
    <title>Facebook Insights API Sample Application</title>
  </head>

<body>
<form action="results.php" method="get">
<div>
<?php foreach ($profiles as $id => $profile) {
?>
<div>
<input type="radio" name="id" value="<?=$id?>" id="id_<?=$id?>" />
<label for="id_<?=$id?>">
<img src="http://graph.facebook.com/<?=$id?>/picture?type=square" alt="<?=$profile['name']?>" height="25" width="25" />
<em><?=$profile['name']?></em>
</label>
</div>
<?php }
?>
</div>

<div>
<input type="hidden" name="access_token" value="<?=$_REQUEST['access_token']?>" />
<input type="hidden" name="date" value="<?=$_REQUEST['date']?>" />
</div>

<div>
<input type="submit" value="Get Analytics" />
</div>
</form>
</body>
</html>
<?php }
?>
