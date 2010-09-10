<?php

require_once 'lib.php';

// Replace these with your AppId and AppSecret.
define('APP_ID', 'YOUR_APP_ID_HERE');
define('APP_SECRET', 'YOUR_APP_SECRET_HERE');

function get_access_token($base_url) {
  if (isset($_REQUEST['access_token'])) {
    return $_REQUEST['access_token'];
  }
  $params = array();
  $params['client_id'] = APP_ID;
  $params['redirect_uri'] = $base_url;
  if (!isset($_REQUEST['code'])) {
    $params['scope'] = 'read_insights';
    $url = FacebookMethods::getGraphApiUrl('oauth/authorize', $params);
    throw new RedirectionException($url);
  } else {
    $params['client_secret'] = APP_SECRET;
    $params['code'] = $_REQUEST['code'];
    $url = FacebookMethods::getGraphApiUrl('oauth/access_token');
    $response = FacebookMethods::fetchUrl($url, $params);
    $response = strstr($response, 'access_token=');
    $result = substr($response, 13);
    $pos = strpos($result, '&');
    if ($pos !== false) {
      $result = substr($result, 0, $pos);
    }
    return $result;
    }
}

try {
  $base_url = 'http://' . $_SERVER['HTTP_HOST'] .'/insights/index.php';
  $token = get_access_token($base_url);
} catch (RedirectionException $re) {
  header('Location: ' . $re->getUrl());
} catch (FacebookApiException $fe) {
  $message = print_r($fe,true);
  echo $message;
  }

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >

  <head>
    <title>Facebook Insights API Sample Application</title>
    <script type="text/javascript" src="http://www.google.com/jsapi?key=ABQIAAAAFVsKBghRBqX8J_phgSflkRQOdEsfl6KlSqfv_5Ewfi-YozgPyBSnmXmUiPRm7Vm7yz3L4NruHJOLsg"></script>
    <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
    <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.4/jquery-ui.min.js"></script>
    <script type="text/javascript">
      $(function() {
        $("#datepicker").datepicker({dateFormat: 'yy-mm-dd'});
      });
    </script>
    <link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.4/themes/base/jquery-ui.css" />
  </head>

  <body>

    <form action="results.php" method="get">
      <div>
        <label for="datepicker">Date</label>
        <input type="text" name="date" id="datepicker" />
      </div>

      <div>
        <input type="hidden" name="access_token" value="<?php echo $token ?>" />
      </div>

      <div>
        <input type="submit" value="Get Index" />
      </div>
    </form>

  </body>
</html>
