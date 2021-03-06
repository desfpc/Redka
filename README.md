# Redka
Lite redis php class


##How to install and use:
1) Download and include class file: require_once ('redka.php');
2) Connect: $redis = new redka($host, $port, $language = 'en', $debugMode = false); // Languages is 'en' or 'ru', debugMode - (true or false) give more debug info

Or use composer: 
composer require desfpc/redka

#

Use redis commands: 

  $redis->has($key); 

  $redis->findKeys($pattern); 

  $redis->get($key); 

  $redis->set($key, $value, $expired);

  $redis->del($key);

  etc...
