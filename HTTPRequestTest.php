<?php

require_once 'PHPUnit/Framework.php';

define('LILINA_USERAGENT', 'Lilina/UnitTesting');

require_once LILINA_INCPATH . '/core/class-httprequest.php';

class FakeTransport {
	public static $code = 200;
	public static $chunked = false;
	public static $body = 'Test Body';
	public static $raw_headers = '';

	private static $messages = array(
		100 => '100 Continue',
		101 => '101 Switching Protocols',
		200 => '200 OK',
		201 => '201 Created',
		202 => '202 Accepted',
		203 => '203 Non-Authoritative Information',
		204 => '204 No Content',
		205 => '205 Reset Content',
		206 => '206 Partial Content',
		300 => '300 Multiple Choices',
		301 => '301 Moved Permanently',
		302 => '302 Found',
		303 => '303 See Other',
		304 => '304 Not Modified',
		305 => '305 Use Proxy',
		306 => '306 (Unused)',
		307 => '307 Temporary Redirect',
		400 => '400 Bad Request',
		401 => '401 Unauthorized',
		402 => '402 Payment Required',
		403 => '403 Forbidden',
		404 => '404 Not Found',
		405 => '405 Method Not Allowed',
		406 => '406 Not Acceptable',
		407 => '407 Proxy Authentication Required',
		408 => '408 Request Timeout',
		409 => '409 Conflict',
		410 => '410 Gone',
		411 => '411 Length Required',
		412 => '412 Precondition Failed',
		413 => '413 Request Entity Too Large',
		414 => '414 Request-URI Too Long',
		415 => '415 Unsupported Media Type',
		416 => '416 Requested Range Not Satisfiable',
		417 => '417 Expectation Failed',
		500 => '500 Internal Server Error',
		501 => '501 Not Implemented',
		502 => '502 Bad Gateway',
		503 => '503 Service Unavailable',
		504 => '504 Gateway Timeout',
		505 => '505 HTTP Version Not Supported'  
	);
	public function request() {
		$status = FakeTransport::$messages[FakeTransport::$code];
		//$encoding = (FakeTransport::$content_encoding) ? 'Content-Encoding: ' . FakeTransport::$content_encoding . "\n" : '';
		$response = "HTTP/1.0 $status\r\n";
		$response .= "Content-Type: text/plain\r\n";
		if(FakeTransport::$chunked)
			$response .= "Transfer-Encoding: chunked\r\n";
		$response .= FakeTransport::$raw_headers;
		$response .= "Connection: close\r\n\r\n";
		$response .= FakeTransport::$body;
		FakeTransport::reset();
		return $response;
	}
	protected function reset() {
		FakeTransport::$code = 200;
		FakeTransport::$chunked = false;
		FakeTransport::$body = 'Test Body';
		FakeTransport::$raw_headers = '';
	}
	public function test() {
		return true;
	}
}

class FileTransport {
	public static $file = '';
	public function request() {
		return file_get_contents('./http-data/' . FileTransport::$file);
	}
	public function test() {
		return true;
	}
}

class RawTransport {
	public static $data = '';
	public function request() {
		return RawTransport::$data;
	}
	public function test() {
		return true;
	}
}

class HTTPRequestTest extends PHPUnit_Framework_TestCase {
	public function setUp() {
		$this->http = new HTTPRequest('fake', 10, null, array('fake' => 'FakeTransport'));
		$this->chunked_transport = new HTTPRequest('file', 10, null, array('file' => 'FileTransport'));
		$this->raw_transport = new HTTPRequest('raw', 10, null, array('raw' => 'RawTransport'));
	}

	/**
	 * Flatterning key => value pairs into header lines
	 */
	public function testFlattern() {
		$headers = array(
			'Content-Type' => 'text/html'
		);
		$expected = array(
			"Content-Type: text/html"
		);
		$this->assertEquals(HTTPRequest::flattern($headers), $expected);
	}

	/**
	 * : is an invalid character in headers
	 * @expectedException Exception
	 */
	public function testErrorOnColonInName() {
		$headers = array(
			'Test:' => 'value'
		);
		$result = HTTPRequest::flattern($headers);
	}

	/**
	 * \n is an invalid character in headers
	 * @expectedException Exception
	 */
	public function testHeaderInjection() {
		$headers = array(
			'Test' => 'value\n'
		);
		$result = HTTPRequest::flattern($headers);
	}

	/**
	 * Standard response header parsing
	 */
	public function testHeaderParsing() {
		RawTransport::$data = 
			"HTTP/1.0 200 OK\r\n".
			"Host: localhost\r\n".
			"Host: ambigious\r\n".
			"Nospace:here\r\n".
			"Muchspace:  there   \r\n".
			"Empty:\r\n".
			"Empty2: \r\n".
			"Folded: one\r\n".
			"\ttwo\r\n".
			"  three\r\n\r\n".
			"stop\r\n";
		$response = $this->raw_transport->request('http://example.com/');
		$expected = array(
			'host' => array(
				'localhost',
				'ambigious'
			),
			'nospace' => 'here',
			'muchspace' => 'there',
			'empty' => '',
			'empty2' => '',
			'folded' => "one two  three"
		);
		$this->assertEquals($response->headers, $expected);
	}

	/**
	 * Headers with only \n delimiting should be treated as if they're \r\n
	 */
	public function testHeaderOnlyLF() {
		RawTransport::$data = "HTTP/1.0 200 OK\r\nTest: value\nAnother Test: value\r\n\r\n";
		$response = $this->raw_transport->request('http://example.com/');
		$expected = array(
			'test' => 'value',
			'another test' => 'value'
		);
		$this->assertEquals($response->headers, $expected);
	}

	/**
	 * FTP should be rejected (this is HTTPRequest, not FTPRequest :) )
	 * @expectedException Exception
	 */
	public function testFTP() {
		$this->http->request('ftp://ftp.example.com/');
	}

	/**
	 * Standard chunking
	 */
	public function testChunked() {
		FileTransport::$file = 'chunked.txt';
		$response = $this->chunked_transport->request('http://example.com/');
		$expected = "This is the data in the first chunk\r\nand this is the second one";
		$this->assertEquals($expected, $response->body);
	}

	/**
	 * Standard chunking, with \n in data
	 */
	public function testChunked2(){
		FakeTransport::$body = "02\r\nab\r\n04\r\nra\nc\r\n06\r\nadabra\r\n0\r\nnothing\n";
		FakeTransport::$chunked = true;
		$response = $this->http->request('http://example.com/');
		$expected = "abra\ncadabra";
		$this->assertEquals($expected, $response->body);
	}

	/**
	 * Chunking with the end NUL missing
	 */
	public function testChunkedMissingEndNUL() {
		FakeTransport::$body = "02\r\nab\r\n04\r\nra\nc\r\n06\r\nadabra\r\n0c\r\n\nall we got\n";
		FakeTransport::$chunked = true;
		$response = $this->http->request('http://example.com/');
		$expected = "abra\ncadabra\nall we got\n";
		$this->assertEquals($expected, $response->body);
	}

	/**
	 * Test splitting headers and body
	 */
	public function testSeparatingBodyFromHead() {
		$response = $this->http->request('http://example.com/');
		$this->assertEquals('Test Body', $response->body);
	}

	/*public function testGzipEncoding() {
		$response = $this->http->request('http://example.com/');
		$this->assertEquals()
	}*/

	/**
	 * Ensure Connection: close doesn't make it through
	 */
	public function testConnectionClose() {
		$response = $this->http->request('http://example.com');
		$this->assertTrue( !isset($response->headers['connection']) );
	}

	/**
	 * Ensure status codes are parsed correctly
	 * @dataProvider successProvider
	 */
	public function testStatusCodes($code, $success) {
		FakeTransport::$code = $code;
		$response = $this->http->request('http://example.com/');
		$this->assertEquals($code, $response->status_code);
	}

	/**
	 * Ensure only 200s are counted as successes
	 * @dataProvider successProvider
	 */
	public function testSuccesses($code, $success) {
		FakeTransport::$code = $code;
		$response = $this->http->request('http://example.com/');
		$this->assertEquals($response->success, $success, $code . ' should have a success value of ' . $success);
	}

	/**
	 * Provides HTTP status codes and related information
	 *
	 * Format is array($code, $success), where $code is a HTTP status code and
	 * $success is whether such a response would be "successful"
	 * @return array
	 */
	public function successProvider() {
		return array(
			array(100, false),
			array(101, false),
			array(200, true),
			array(201, true),
			array(202, true),
			array(203, true),
			array(204, true),
			array(205, true),
			array(206, true),
			array(300, false),
			array(301, false),
			array(302, false),
			array(303, false),
			array(304, false),
			array(305, false),
			array(306, false),
			array(307, false),
			array(400, false),
			array(401, false),
			array(402, false),
			array(403, false),
			array(404, false),
			array(405, false),
			array(406, false),
			array(407, false),
			array(408, false),
			array(409, false),
			array(410, false),
			array(411, false),
			array(412, false),
			array(413, false),
			array(414, false),
			array(415, false),
			array(416, false),
			array(417, false),
			array(500, false),
			array(501, false),
			array(502, false),
			array(503, false),
			array(504, false),
			array(505, false),
		);
	}
}
