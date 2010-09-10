<?php

class RedirectionException extends Exception {
  private $url;

  public function __construct($url) {
    $this->url = $url;
  }

  public function getUrl() {
    return $this->url;
  }
}

class FacebookMethods{

  private static function getUrl($httphost, $path, $params) {
    $url = $httphost;
    if ($path) {
      if ($path[0] === '/') {
        $path = substr($path, 1);
      }
      $url .= $path;
    }
    if ($params) {
      $url .= '?' . http_build_query($params);
    }
    return $url;
  }

  public static function getGraphApiUrl($path = '', $params = array()) {
    return self::getUrl('https://graph.facebook.com/', $path, $params);
  }

  public static function getRestApiUrl($params = array()) {
    return self::getUrl('https://api.facebook.com/',
                         'restserver.php', $params);
  }

  public static function fetchUrl($url, $params) {
    $params['format'] = 'json-strings';
    $ch = curl_init();
    $opts = array(
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_USERAGENT => 'facebook-php-2.0',
    CURLOPT_URL => $url,
  );
    $opts[CURLOPT_POSTFIELDS] = http_build_query($params, null, '&');
    curl_setopt_array($ch, $opts);
    $result = curl_exec($ch);
    if ($result === false) {
      $e = new Exception(curl_error($ch), curl_errno($ch));
      curl_close($ch);
      throw $e;
    }
    curl_close($ch);
    return json_decode($result, true);
  }
}
