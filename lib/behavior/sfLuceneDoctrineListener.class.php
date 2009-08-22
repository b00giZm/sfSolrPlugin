<?php
/*
 * This file is part of the sfLucenePlugin package
 * (c) 2007 Carl Vondrick <carlv@carlsoft.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Responsible for handling Doctrine's behaviors.
 * @package    sfLucenePlugin
 * @subpackage Behavior
 * @author     Carl Vondrick <carlv@carlsoft.net>
 * @author     Thomas Rabaix <thomas.rabaix@soleoweb.com>
 */
class sfLuceneDoctrineListener extends Doctrine_Record_Listener
{
  /**
   * Executes save routine
   */
  public function postSave(Doctrine_Event $event)
  {

    try {
      $this->saveIndex($event->getInvoker());
    }
    catch(Exception $e) {
      // no context define, cannot do anything,
      if(sfContext::hasInstance())
      {
        sfContext::getInstance()->getLogger()->crit('{sfLuceneDoctrineListener::postSave} Error while saving document to solr : '.$e->getMessage());
      }
    }

  }

  /**
   * Deletes the object
   */
  public function postDelete(Doctrine_Event $event)
  {
    try {
      $this->deleteIndex($event->getInvoker());
    } catch(Exception $e) {
      // no context define, cannot do anything
      if(sfContext::hasInstance())
      {
        sfContext::getInstance()->getLogger()->crit('{sfLuceneDoctrineListener::postSave} Error while deleting document to solr : '.$e->getMessage());
      }
    }
    
  }

  /**
   * Saves index by deleting and inserting.
   */
  public function saveIndex($node)
  {
    $this->deleteIndex($node);
    $this->insertIndex($node);
  }

  /**
  * Deletes the old model
  */
  public function deleteIndex($node)
  {
    if(sfConfig::get('app_sfLucene_disable_listener', false))
    {

      return;
    }
    
    sfContext::getInstance()->getEventDispatcher()->notify(new sfEvent($this, 'lucene.log', array('{sfLucene} deleting model "%s" with PK = "%s"', get_class($node), current($node->identifier()))));

    foreach ($this->getSearchInstances($node) as $instance)
    {
      $instance->getIndexerFactory()->getModel($node)->delete();
    }
  }

  /**
  * Adds the new model
  */
  public function insertIndex($node)
  {
    if(sfConfig::get('app_sfLucene_disable_listener', false))
    {

      return;
    }

    sfContext::getInstance()->getEventDispatcher()->notify(new sfEvent($this, 'lucene.log', array('{sfLucene} deleting model "%s" with PK = "%s"', get_class($node), current($node->identifier()))));
  
    foreach ($this->getSearchInstances($node) as $instance)
    {
      $instance->getIndexerFactory()->getModel($node)->insert();
    }
  }

  public function getInheritanceClass($node, $conf_index)
  {
    foreach(array_keys($conf_index['models']) as $model)
    {
      if($node instanceof $model)
      {

        return $model;
      }
    }

    return null;
  }

  protected function getSearchInstances($node)
  {
    static $instances;

    $class = get_class($node);
    
    if (!isset($instances))
    {
      $instances = array();
    }


    if (!isset($instances[$class]))
    {
      $config = sfLucene::getConfig();

      foreach ($config as $name => $item)
      {
        $inheritance_class = $this->getInheritanceClass($node, $item);
        
        if(!$inheritance_class)
        {
          throw new sfException('Cannot find the correct inheritance class for the object type : '.get_class($node));
        }

        if (isset($item['models'][$inheritance_class]))
        {
          foreach ($item['index']['cultures'] as $culture)
          {
            $instances[$class][] = sfLucene::getInstance($name, $culture);
          }
        }
      }
    }

    return $instances[$class];
  }

  /**
  * Returns the behavior initializer
  */
  static public function getInitializer()
  {
    return sfLuceneDoctrineInitializer::getInstance();
  }
}