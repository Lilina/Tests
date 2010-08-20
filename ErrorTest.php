<?php

require_once 'PHPUnit/Framework.php';

define('LILINA_PATH', realpath('../lilina'));
define('LILINA_INCPATH', LILINA_PATH . '/inc');

require_once LILINA_INCPATH . '/core/class-message.php';
require_once LILINA_INCPATH . '/core/class-error.php';

class ErrorTest extends PHPUnit_Framework_TestCase {
	/**
	 * @dataProvider provider
	 */
	public function testToString($error) {
		$this->assertEquals((string) $error, $error->message);
	}
	public function provider() {
		return array(
			array(new Error('Test error')),
			array(new Error()),
			array(new Error(''))
		);
	}
}