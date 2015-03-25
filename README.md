mariablockchain
==============

[![Join the chat at https://gitter.im/willgriffin/mariablockchain](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/willgriffin/mariablockchain?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

About
--------------

A relational database representation of related bitcoin based blockchain records.

This libraries functions that make use of indexes (most of the fun stuff) are only as accurate as the records stored in the database.

What this means is that to be of any value, either use the bitcoind rpc 'wallet' trigger to teach it about a transaction to be
accurate for your wallet, or use the 'block' trigger event to parse every block. A combination of both insures accuracy for any
address and immediacy of awareness of in-wallet transactions.

URL: [https://github.com/willgriffin/mariablockchain](https://github.com/willgriffin/mariablockchain)

Author: willgriffin

Example
--------------

```php

//note, requirement is modified fork \nbobtc\bitcoind incorporating bitcoin-lib-php atm hence the magic_bytes arguments
$rpc = new \Nbobtc\Bitcoind\Bitcoind(new \Nbobtc\Bitcoind\Client(
  $rpcprotocol."://".$rpcuser.":".$rpcpass."@".$rpchost.":".$rpcport."/",
  null, //cert for ssl
  $magic_byte,
  $magic_p2sh_byte
));

$cache = new \Memcache;
$cache->connect($memcachehost, $memcacheport);

$db = new \willgriffin\MariaInterface\MariaInterface (array(
  "host" => $dbhost,
  "user" => $dbuser,
  "pass" => $dbpass,
  "port" => $dbport,
  "name" => $dbname
));

$bitcoin = new MariaBlockChain($rpc, $db, $cache);

$tx = $bitcoin->transactions->get($txid);

$addresss = $bitcoin->addresses->get($bitcoinAddress);

$ledger = $blockchain->addresses->getLedger(
  '1124fWAtrp31Apd35zkoYqw2jRerE97HE4',
  ['startDate' => "2013-03-13", 'endDate' => "2015-03-13" ]);


```


Credits
--------------

mariablockchain was initiated with [generator-composer](https://github.com/T1st3/generator-composer), a [Yeoman](http://yeoman.io) generator that builds a PHP Composer project.

This project uses the following as development dependencies:

* [PHPUnit](http://phpunit.de/)
* [PhpDocumentor](http://phpdoc.org)
* [Php Copy/Paste Detector](https://github.com/sebastianbergmann/phpcpd)
* [nbobtc/bitcoind (modified)](https://github.com/willgriffin/bitcoind-php)

License
--------------

[License](https://github.com/willgriffin/mariablockchain/blob/master/LICENSE)


Donate
-------------
[1124fWAtrp31Apd35zkoYqw2jRerE97HE4](https://coink.it/#!/1124fWAtrp31Apd35zkoYqw2jRerE97HE4)
