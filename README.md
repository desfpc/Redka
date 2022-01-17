# Redka
Lite redis php class


##How to install and use:
1) Use composer: 
composer require desfpc/redka
2) use desfpc/Redka/Redka in your script
3) Create Redis object: $redis = new Redka($host, $port, $lang, $debug);

- $host - Redis host;
- $port - Redis port;
- $lang - language name ('en' or 'ru')
- $debug - debug mode - boolean (true or false)

#

Use redis commands: 

  $redis->has($key); 

  $redis->findKeys($pattern); 

  $redis->get($key); 

  $redis->set($key, $value, $expired);

  $redis->del($key);

  etc...
