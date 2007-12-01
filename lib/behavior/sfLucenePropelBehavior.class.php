<?php
/*
 * This file is part of the sfLucenePlugin package
 * (c) 2007 Carl Vondrick <carlv@carlsoft.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Responsible for handling Propel's behaviors.
 * @package    sfLucenePlugin
 * @subpackage Behavior
 * @author     Carl Vondrick <carlv@carlsoft.net>
 * @version SVN: $Id$
 */
class sfLucenePropelBehavior
{
  /**
   * Stores the objects in the queue that are flagged to be saved.
   */
  protected $saveQueue = array();

  /**
   * Stores the objects in the queue that are flagged to be removed.
   */
  protected $deleteQueue = array();

  /**
   * Adds the node to the queue if is modified or is new.
   */
  public function preSave($node)
  {
    if ($node->isModified() || $node->isNew())
    {
      foreach ($this->saveQueue as $item)
      {
        if ($node->equals($item))
        {
          // already in queue, abort
          return;
        }
      }

      $this->saveQueue[] = $node;
    }
  }

  /**
   * Executes save routine if it can find it in the queue.
   */
  public function postSave($node)
  {
    foreach ($this->saveQueue as $key => $item)
    {
      if ($node->equals($item))
      {
        $this->saveIndex($node);

        unset($this->saveQueue[$key]);

        break;
      }
    }
  }

  /**
   * Adds the node to the queue if is not new.
   */
  public function preDelete($node)
  {
    if (!$node->isNew())
    {
      foreach ($this->deleteQueue as $item)
      {
        if ($node->equals($item))
        {
          // already in queue, abort
          return;
        }
      }

      $this->deleteQueue[] = $node;
    }
  }

  /**
   * Deletes the object if it can find it in the queue.
   */
  public function postDelete($node)
  {
    foreach ($this->deleteQueue as $key => $item)
    {
      if ($node->equals($item))
      {
        $this->deleteIndex($node);

        unset($this->deleteQueue[$key]);

        break;
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
    if (sfConfig::get('sf_logging_enabled'))
    {
     sfContext::getInstance()->getEventDispatcher()->notify(new sfEvent($this, 'application.log', array(sprintf('deleting model "%s" with PK = "%s"', get_class($node), $node->getPrimaryKey()))));
    }

    foreach ($this->getSearchInstances($node) as $instance)
    {
      $instance->getIndexer()->getModel($node)->delete();
    }
  }

  /**
  * Adds the new model
  */
  public function insertIndex($node)
  {
    if (sfConfig::get('sf_logging_enabled'))
    {
      sfContext::getInstance()->getEventDispatcher()->notify(new sfEvent($this, 'application.log', array(sprintf('saving model "%s" with PK = "%s"', get_class($node), $node->getPrimaryKey()))));
    }

    foreach ($this->getSearchInstances($node) as $instance)
    {
      $instance->getIndexer()->getModel($node)->insert();
    }
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
        if (isset($item['models'][$class]))
        {
          foreach ($item['index']['cultures'] as $culture)
          {
            $instances[$class][] = sfLucene::getInstance($name, $culture);
          }
        }
      }
    }

    if (!isset($instances[$class]) || count($instances[$class]) == 0)
    {
      throw new sfLuceneException('No sfLucene instances could be found for "' . $class . '"');
    }

    return $instances[$class];
  }

  /**
  * Returns the behavior initializer
  */
  static public function getInitializer()
  {
    return sfLucenePropelInitializer::getInstance();
  }
}