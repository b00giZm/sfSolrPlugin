<?php
/*
 * This file is part of the sfLucenePLugin package
 * (c) 2007 Carl Vondrick <carlv@carlsoft.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Base class for the advanced form.  If you wish to overload this, please use
 * sfLuceneSimpleForm instead.
 *
 * This form represents the advanced search form that is displayed when the user
 * surfs to the "Advanced" page.
 *
 * @package    sfLucenePlugin
 * @subpackage Form
 * @author     Carl Vondrick <carlv@carlsoft.net>
 * @version SVN: $Id$
 */

abstract class sfLuceneAdvancedFormBase extends sfLuceneForm
{
  public function __construct($defaults = array(), $options = array(), $CSRFSecret = false)
  {
    parent::__construct($defaults, $options, $CSRFSecret);
  }

  public function setup()
  {
    $widgetSchema = new sfWidgetFormSchema(
    array( // fields
      'keywords' => new sfWidgetFormInput(),
      'musthave' => new sfWidgetFormInput(),
      'mustnothave' => new sfWidgetFormInput(),
      'hasphrase' => new sfWidgetFormInput()
    ),
    array( // options
    ),
    array( // attributes
    ),
    array( // labels
      'keywords' => 'May contain keywords',
      'musthave' => 'Must contain keywords',
      'mustnothave' => 'Must exclude keywords',
      'hasphrase' => 'Contains exact phrase'
    ),
    array( // helps
    )
    );

    $widgetSchema->addFormFormatter('sfLuceneAdvanced', new sfLuceneWidgetFormatterAdvanced());
    $widgetSchema->setFormFormatterName('sfLuceneAdvanced');
    $widgetSchema->setNameFormat('form[%s]');

    $validatorSchema = new sfValidatorSchema(
    array(
      'keywords' => new sfValidatorString(array('required' => false)),
      'musthave' => new sfValidatorString(array('required' => false)),
      'mustnothave' => new sfValidatorString(array('required' => false)),
      'hasphrase' => new sfValidatorString(array('required' => false))
    ),
    array( // options
    ),
    array( // messages
    )
    );

    if ($this->hasCategories())
    {
      $widgetSchema['category'] = new sfWidgetFormSelect(array('choices' => $this->getCategories(), 'multiple' => false));

      $widgetSchema->setLabel('category', 'Must be in category');

      $validatorSchema['category'] = new sfValidatorChoice(array('required' => false, 'choices' => $this->getCategories()));
    }

    $this->setWidgetSchema($widgetSchema);

    $this->setValidatorSchema($validatorSchema);
  }
}
