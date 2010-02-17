<?php
/*
 * This file is part of the sfLucenePlugin package
 * (c) 2007 - 2008 Carl Vondrick <carl@carlsoft.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once(dirname(__FILE__).'/sfLuceneBaseTask.class.php');

/**
 * This task rebuilds the entire index.
 *
 * @author Thomas Rabaix <thomas.rabaix@soleoweb.com>
 * @package sfLucenePlugin
 * @subpackage Tasks
 * @version SVN: $Id: sfLuceneRebuildTask.class.php 7466 2008-02-12 05:34:08Z dwhittle $
 */

class sfLuceneUpdateModelSystemTask extends sfLuceneBaseTask
{
  protected function configure()
  {
    $this->addArguments(array(
    new sfCommandArgument('application', sfCommandArgument::REQUIRED, 'The application name'),
    new sfCommandArgument('index', sfCommandArgument::REQUIRED, 'The name of the index to rebuild'),
    new sfCommandArgument('culture', sfCommandArgument::REQUIRED, 'The name of the culture to rebuild'),
    ));

    $this->addOptions(array(
    new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'search'),
    new sfCommandOption('model', null, sfCommandOption::PARAMETER_OPTIONAL, 'The model to reindex', null),
    new sfCommandOption('limit', null, sfCommandOption::PARAMETER_OPTIONAL, 'The environment', 50),
    new sfCommandOption('delete', null, sfCommandOption::PARAMETER_OPTIONAL, 'set to true to delete all related record', false),
    
    ));

    $this->aliases = array('lucene-rebuild-system');
    $this->namespace = 'lucene';
    $this->name = 'update-model-system';
    $this->briefDescription = 'Update one index by using sub process to avoid memory leak (Doctrine Only)';

    $this->detailedDescription = <<<EOF
The [lucene:rebuild|INFO] task rebuilds all the sfLucene indexes.

It configures sfLucene according to your [search.yml|COMMENT] files and then rebuilds
all the indexes for every culture.

[Warning:|ERROR] Depending on how much you are indexing, this may take from a couple of
minutes to a couple of hours.  If you run this on a production server, the search
function will offline until it completes indexing.

For example:

  [./symfony lucene:rebuild myapp|INFO]

will initiate rebuilding.
EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {
    $app     = $arguments['application'];
    $index   = $arguments['index'];
    $culture = $arguments['culture'];

    $limit   = $options['limit'];
    $model   = $options['model'];
    $delete  = $options['delete'];

    $this->checkAppExists($app);
    $this->standardBootstrap($app, $options['env']);

    if(sfConfig::get('sf_orm') != 'doctrine')
    {
      
      throw new LogicException('This feature is only implemented for Doctrine ORM');
    }
    
    $start = microtime(true);

    $search = sfLucene::getInstance($index, $culture, $this->configuration);
    $search->optimize();
    
    $this->setupEventDispatcher($search);

    $models = $search->getParameter('models')->getAll();


    if($model)
    {
      if($delete)
      {
        $this->deleteModel($search, $model);
      }
      $this->update($app, $index, $culture, $model, $limit);
    }
    else
    {
      foreach($models as $model => $params)
      {
        if($delete)
        {
          $this->deleteModel($search, $model);
        }
        
        $this->update($app, $index, $culture, $model, $limit);
      }
    }
    
    $time = microtime(true) - $start;

   $final = $this->formatter->format('Update index done !!', array('fg' => 'green', 'bold' => true));
   $final .= $this->formatter->format(number_format($time, 5), array('fg' => 'cyan')) . ' seconds.';

   $this->dispatcher->notify(new sfEvent($this, 'command.log', array('', $final)));
  }

  public function deleteModel(sfLucene $lucene, $model)
  {
    $query = 'sfl_model:'.$model;
    $lucene->getLucene()->deleteByQuery($query);
    $lucene->getLucene()->commit();
  }
  
  public function getFilestatePath($model)
  {
    
    return sprintf(sfConfig::get('sf_data_dir').'/solr_index/update_%s.state', sfInflector::underscore($model));
  }
  
  public function update($app, $index, $culture, $model, $limit)
  {
    
    $file = $this->getFilestatePath($model);
    if(is_file($file))
    {
      $this->getFilesystem()->remove($file);
    }
        
    do
    {
      $final = $this->formatter->format('Updating model='.$model, array('fg' => 'green', 'bold' => true));
      
      $this->dispatcher->notify(new sfEvent($this, 'command.log', array('', $final)));
      
      $command = sprintf('php -d memory_limit=64M %s/symfony lucene:update-model %s %s %s %s --state=true',
        $this->configuration->getRootDir(),
        $app,
        $index,
        $culture,
        $model,
        $limit,
        $offset
      );

      try
      {
        $return_code = $this->getFilesystem()->sh($command);
        $this->logSection('lucene', 'end indexing model : '.$model);

        return 0;
      } 
      catch(sfException $e)
      {
        if(preg_match("/Allowed memory size of ([0-9]*) bytes/", $e->getMessage()))
        {
          $this->logSection('lucene', '  memory limit reach, starting new subprocess');

          continue;
        }
        else
        {
          throw $e;
        }
      }

    } while(1);
  }
}