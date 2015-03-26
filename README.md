mariablockchain
==============

About
--------------

A relational database representation of related bitcoin based blockchain records.

This library's functions that make use of indexes (most of the fun stuff) are only as accurate as the records stored in the database.

What this means is that to be of any value, either use the bitcoind rpc 'wallet' trigger to teach it about a transaction to be
accurate for your wallet, or use the 'block' trigger event to parse every block. A combination of both insures accuracy for any
address and immediacy of awareness of in-wallet transactions.

Use the bitcoind rpc 'wallet' trigger to secure accuracy for your wallet, or, use the 'block' trigger to parse every block for total accuracy. A combination of both will ensure accuracy for any address and your in-wallet transactions will become immediately aware. It's not recommended to use this library as it stands to parse the entire blockchain... yet

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

Copyright (c) 2013 will griffin

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Donate
-------------
[1124fWAtrp31Apd35zkoYqw2jRerE97HE4](https://coink.it/#!/1124fWAtrp31Apd35zkoYqw2jRerE97HE4)
