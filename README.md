# WireIO

PHP client library for WireIO rest api. Developers can trigger events from their php applications

## Installation

Just require the PHP file wireio.php:

    require_once 'wireio.php';

## Usage

Using the library is straight forward, first you need initialize the client with your application keys
		
		$public_key = "awesome_public_key";
		$private_key = "awesome_private_key";
		$wio = new WireIOClient($public_key, $private_key);

Then to trigger an event
		
		$wio->on('jedi-boarding-ship', array('nick' => 'luke'));


## Contributing

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create new Pull Request
