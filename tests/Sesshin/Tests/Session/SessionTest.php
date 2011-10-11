<?php
/*
 * This file is part of Sesshin library.
 *
 * (c) Przemek Sobstel <http://sobstel.org>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sesshin\Tests\Session;
use Sesshin\Session\Session;

class SessionTest extends \PHPUnit_Framework_TestCase {

  /**
   * @var Sesshin\Session\Session
   */
  private function setUpDefaultSession($session = null) {
    if (is_null($session)) {
      $session = new Session();
    }

    $id_handler = $this->getMock('\Sesshin\Id\Handler', array('generateId', 'getId', 'setId', 'issetId', 'unsetId'));
    $session->setIdHandler($id_handler);

    $storage = $this->getMock('\Sesshin\Storage\StorageInterface', array('store', 'fetch', 'delete'));
    $session->setStorage($storage);

    $listener = $this->getMock('\Sesshin\Listener\Listener', array('trigger', 'bind', 'getQueue'));
    $session->setListener($listener);

    return $session;
  }

  /**
   * @covers Sesshin\Session\Session::create
   */
  public function testCreateMethodGeneratesId() {
    $session = $this->setUpDefaultSession();
    $session->getIdHandler()->expects($this->once())->method('generateId');
    $session->create();
  }

  /**
   * @covers Sesshin\Session\Session::create
   */ 
  public function testCreateMethodUnsetsAllValues() {
    $session = $this->setUpDefaultSession();
    $ref_prop_values = new \ReflectionProperty('\Sesshin\Session\Session', 'values');
    $ref_prop_values->setAccessible(true);
    $ref_prop_values->setValue($session, array(1, 2, 3, 4));
    $session->create();
    $this->assertEmpty($ref_prop_values->getValue($session));
  }

  /**
   * @covers Sesshin\Session\Session::create
   */ 
  public function testCreateMethodResetsFirstTrace() {
    $session = $this->setUpDefaultSession();
    $first_trace = $session->getFirstTrace();
    $session->create();
    $this->assertNotEquals($first_trace, $session->getFirstTrace());
  }

  /**
   * @covers Sesshin\Session\Session::create
   */ 
  public function testCreateMethodResetsLastTrace() {
    $session = $this->setUpDefaultSession();
    $last_trace = $session->getLastTrace();
    $session->create();
    $this->assertNotEquals($last_trace, $session->getLastTrace());
  }

  /**
   * @covers Sesshin\Session\Session::create
   */ 
  public function testCreateMethodResetsRequestsCounter() {
    $session = $this->setUpDefaultSession();
    $session->create();
    $this->assertEquals(1, $session->getRequestsCounter());
  }

  /**
   * @covers Sesshin\Session\Session::create
   */ 
  public function testCreateMethodResetsIdRegenerationTimestamp() {
    $session = $this->setUpDefaultSession();
    $regeneration_trace = $session->getRegenerationTrace();
    $session->create();
    $this->assertNotEquals($regeneration_trace, $session->getRegenerationTrace());
  }

  /**
   * @covers Sesshin\Session\Session::create
   */ 
  public function testCreateMethodGeneratesFingerprint() {
    $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session\Session', array('generateFingerprint')));
    $session->expects($this->once())->method('generateFingerprint');
    $session->create();
  }

  /**
   * @covers Sesshin\Session\Session::create
   */ 
  public function testCreateMethodOpensSession() {
    $session = $this->setUpDefaultSession();
    $session->create();
    $this->assertEquals(true, $session->isOpened());
  }
  
  /**
   * @covers Sesshin\Session\Session::open 
   */
  public function testOpenMethodWhenCalledWithTrueThenCreatesNewSessionIfSessionNotExistsAlready() {
    $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session\Session', array('create')));    
    $session->getIdHandler()->expects($this->any())->method('issetId')->will($this->returnValue(false));    
    $session->expects($this->once())->method('create');

    $session->open(true);
  }
  
  /**
   * @covers Sesshin\Session\Session::open 
   */
  public function testOpenMethodWhenCalledWithTrueThenDoesNotCreateNewSessionIfSessionIdExistsAlready() {
    $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session\Session', array('create')));    
    $session->getIdHandler()->expects($this->any())->method('issetId')->will($this->returnValue(true));    
    $session->expects($this->never())->method('create');

    $session->open(true);    
  }
  
  /**
   * @covers Sesshin\Session\Session::open 
   */
  public function testOpenMethodWhenCalledWithFalseThenDoesNotCreateNewSession() {
    $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session\Session', array('create')));
    $session->expects($this->never())->method('create');

    $session->open(false);
  }

  /**
   * @covers Sesshin\Session\Session::open
   */
  public function testOpenMethodLoadsSessionDataIfSessionExists() {
    $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session\Session', array('create', 'load', 'generateFingerprint')));
    $session->expects($this->any())->method('isOpened')->will($this->returnValue(false));
    $session->getIdHandler()->expects($this->any())->method('issetId')->will($this->returnValue(true));
    $session->expects($this->once())->method('load');

    $session->open();
  }

  /**
   * @covers Sesshin\Session\Session::open
   */
  public function testOpenMethodDoesNotLoadSessionDataIfSessionNotExists() {
    $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session\Session', array('create', 'load', 'generateFingerprint')));
    $session->expects($this->any())->method('isOpened')->will($this->returnValue(false));    
    $session->getIdHandler()->expects($this->any())->method('issetId')->will($this->returnValue(false));
    $session->expects($this->never())->method('load');

    $session->open();
  }

  /**
   * Fingerpring is generated, so it can be compared with the one in session
   * metadata for session validity.
   * 
   * @covers Sesshin\Session\Session::open
   */
  public function testOpenMethodGeneratesFingerprintIfSessionExists() {
    $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session\Session', array('create', 'load', 'generateFingerprint')));
    $session->expects($this->any())->method('isOpened')->will($this->returnValue(false));
    $session->getIdHandler()->expects($this->any())->method('issetId')->will($this->returnValue(true));
    $session->expects($this->once())->method('generateFingerprint');

    $session->open();
  }

  public function testImplementsArrayAccessForSessionValues() {
    $session = $this->getMock('\Sesshin\Session\Session', array('setValue'));
    $session->expects($this->once())->method('setValue')->with($this->equalTo('key'), $this->equalTo('value'));
    $session['key'] = 'value';

    $session = $this->getMock('\Sesshin\Session\Session', array('getValue'));
    $session->expects($this->once())->method('getValue')->with($this->equalTo('key'));
    $session['key'];

    $session = $this->getMock('\Sesshin\Session\Session', array('issetValue'));
    $session->expects($this->once())->method('issetValue')->with($this->equalTo('key'));
    isset($session['key']);

    $session = $this->getMock('\Sesshin\Session\Session', array('unsetValue'));
    $session->expects($this->once())->method('unsetValue')->with($this->equalTo('key'));
    unset($session['key']);
  }

}