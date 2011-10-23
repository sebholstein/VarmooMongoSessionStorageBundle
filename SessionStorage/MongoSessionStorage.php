<?php

namespace Varmoo\Bundle\MongoSessionStorageBundle\SessionStorage;

/*
 * This file is part of the VarmooMongoSessionStorageBundle
 * (c) 2011 Sebastian Müller <sebastian.mueller@varmoo.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\HttpFoundation\SessionStorage\NativeSessionStorage;

/**
 * MongoSessionStorage
 * 
 * @author Sebastian Müller <sebastian.mueller@varmoo.com>
 */
class MongoSessionStorage extends NativeSessionStorage
{
  private $mongoOptions;
  private $mongo;
  private $database;
  private $collection;
  
  /**
   * The constructor
   *
   * @param array   $options   An associative array of session options
   * @param array   $mongoOptions An associative array of MongoDB options
   * @param \Mongo  $mongo A \Mongo instance
   *
   * @see NativeSessionStorage::__construct()
   */
  public function __construct(array $options = array(), array $mongoOptions = array(), \Mongo $mongo)
  { 
    $this->mongoOptions = array_merge(array(
      'database' => 'test',
      'collection' => 'sessions'
    ), $mongoOptions);
    
    $this->mongo = $mongo;
    $this->database = $this->mongo->{$mongoOptions['database']};
    $this->collection = $this->database->{$this->mongoOptions['collection']};
    
    parent::__construct($options);
  }
  
  /**
   * {@inheritdoc}
   */
  public function start()
  {
    if (self::$sessionStarted) {
        return;
    }

    // use this object as the session handler
    session_set_save_handler(
        array($this, 'sessionOpen'),
        array($this, 'sessionClose'),
        array($this, 'sessionRead'),
        array($this, 'sessionWrite'),
        array($this, 'sessionDestroy'),
        array($this, 'sessionCleanup')
    );
    
    parent::start();
  }
  
  /**
   * Opens a session.
   *
   * @param  string $path  (ignored)
   * @param  string $name  (ignored)
   *
   * @return Boolean true, if the session was opened, otherwise an exception is thrown
   */
  public function sessionOpen($path = null, $name = null)
  {
    return true;
  }
  
  /**
   * Closes a session.
   *
   * @return Boolean true, if the session was closed, otherwise false
   */
  public function sessionClose()
  {
    return true;
  }
  
  /**
   * Reads a session.
   *
   * @param  string $id  A session ID
   *
   * @return string      The session data if the session was read or created
   *
   */
  public function sessionRead($id)
  {
    $session = $this->collection->findOne(array('session_id' => $id));
    if($session)
    {
      return $session['data'];
    }
    
    // no session created, create now
    $this->createNewSession($id);
    
    return '';
  }
  
  /**
   * Writes session data.
   *
   * @param  string $id    A session ID
   * @param  string $data  A serialized chunk of session data
   *
   * @return Boolean true, if the session was written
   *
   */
  public function sessionWrite($id, $data = '')
  {
    $session = $this->collection->findOne(array('session_id' => $id));
    
    if(!$session)
    {
      $this->createNewSession($id, $data);
    }
    
    $update = $this->collection->update(array('session_id' => $id), array(
      '$set' => array('data' => $data)
    ));
    
    if(!empty($update))
    {
      return true;
    }
    
    return false;
  }
  
  /**
   * Destroys a session
   *
   * @param string $id the session id
   *
   * @return Boolean, true if the session was successfully removed, false otherwise
   */
  public function sessionDestroy($id)
  { 
    return (boolean) $this->collection->remove(array('session_id' => $id));
  }
  
  /**
   * Removes old sessions
   *
   * @param integer $lifetime the session lifetime in seconds
   *
   */
  public function sessionCleanup($lifetime)
  { 
    $time = time();
    $expiredTime = $time - $lifetime;
    
    return (boolean) $this->collection->remove(array('lifetime' => array('$lt' => $expiredTime)));
  }
  
  /**
   * Creates a new session with the given $id and $data
   *
   * @param string $id
   * @param string $data
   */
  private function createNewSession($id, $data = '')
  {
    $this->collection->insert(array(
      'session_id' => (string) $id,
      'time' => time(),
      'data' => (string) $data,
    ));
    
    return true;
  }
}