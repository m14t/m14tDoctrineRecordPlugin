<?php

abstract class m14tDoctrineRecord extends sfDoctrineRecord {

  /**
   * executes a MySQL INSERT ... ON DUPLICATE KEY UPDATE query.
   *
   * This is similar to a REPLACE query except when there is a
   * similarly existing row, it will update that row inplace.
   *
   * REPLACE essentially does a DROP followed by an INSERT, which
   * triggers onDelete CASCADES.  This will not do that.
   *
   * http://dev.mysql.com/doc/refman/5.0/en/insert-on-duplicate.html
   *
   * @param Doctrine_Connection $conn             optional connection parameter
   * @throws Doctrine_Connection_Exception        if it is not a MySql database
   * @return boolean                              true if saved, false otherwise
   */
  public function insertOrUpdate(Doctrine_Connection $conn = null) {
    if ($conn === null) {
      $conn = $this->_table->getConnection();
    }

    //-- make sure we are using a MySql Database
    if ( !($conn instanceof Doctrine_Connection_Mysql) ) {
      throw new Doctrine_Connection_Exception(sprintf(
        'Unsupported connnection %s.  DoctrineConnection must be of type Doctrine_Connection_Mysql.',
        get_class($conn)
      ));
    }

    //-- The preSave Event could trigger some field modifications
    //   so make sure we run this before $this->getModified()
    $event = $this->invokeSaveHooks('pre', 'save');
    $event = $this->invokeSaveHooks('pre', 'insert');
    $event = $this->invokeSaveHooks('pre', 'update');

    $modified = $this->getModified();
    if ( 0 < count($modified) ) {

      /*
      //-- FIXME:  Should we also do the following?
      $this->assignInheritanceValues();
      */

      $kvs = $this->generageKeyValues();
      $sql = $this->generateInsertOrUpdateSql($kvs['keys']);

      $conn->exec($sql, $kvs['params']);

      //-- Save the ID
      $id = $conn->lastInsertId();
      $this->set('id', $id, false);

      $this->invokeSaveHooks('post', 'save', $event);
      $this->invokeSaveHooks('post', 'insert', $event);
      $this->invokeSaveHooks('post', 'update', $event);

      return true;
    }
    return false;
  }


  /*
   * Generate the SQL needed for the $this->insertOrUpdate() query.
   *
   * @return string    SQL query.
   */
  protected function generateInsertOrUpdateSql($keys) {

    $table = $this->getTable();
    $table_name = $table->getTableName();
    $values = array_fill(0, count($keys), '?');

    $sql = sprintf(
      'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s=LAST_INSERT_ID(%s), %s;',
      $table_name,
      implode(', ', $keys),
      implode(', ', $values),
      'id',  //-- FIXME:  Make this dynamically get the primary key
      'id',  //-- FIXME:  Make this dynamically get the primary key
      implode(', ', array_map(
        function($k, $v) { return "$k = $v"; },
        $keys,
        $values
      ))
    );

    return $sql;
  }


  protected function generageKeyValues() {
    $modified = $this->getModified();
    $table = $this->getTable();
    $relations = array();
    foreach ( $table->getRelations() as $relation) {
      $relations[$relation->getLocal()] = $relation->getLocalFieldName();
    }

    $keys = array();
    $params = array();
    foreach ( $modified as $k => $v ) {
      if ( array_key_exists($k, $relations) ) {
        $keys[] = $relations[$k];
      } else {
        $keys[] = $k;
      }
      if ( $v instanceof Doctrine_Record ) {
        $params[] = $v['id'];
      } else {
        $params[] = $v;
      }
    }

    //-- Repeat all of the params since we will need them doubled up when
    //   passed to the exec().  Once for the INSERT, once for the UPDATE
    $count = count($params);
    for ( $i = 0; $i < $count ; $i++ ) {
      $params[] = $params[$i];
    }

    return array(
      'keys' => $keys,
      'params' => $params,
    );
  }


}
