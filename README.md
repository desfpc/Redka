# Redka
Lite redis php class


##How to install and use:
1) include class file: require_once ('./classes/redka/redka.php');
2) connect: $redis = new redka($host, $port, $language = 'en', $debugMode = false); // Languages is 'en' or 'ru', debugMode - (true or false) give more debug info
3) use redis commands: 

  $redis->has($key); 

  $redis->findKeys($pattern); 

  $redis->get($key); 

  $redis->set($key, $value, $expired);

  $redis->del($key);

  etc...
