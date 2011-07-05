<?php
/***********************************************************
   ..

   Reference:
     http://atk4.com/doc/ref

 **ATK4*****************************************************
   This file is part of Agile Toolkit 4 
    http://agiletoolkit.org
  
   (c) 2008-2011 Agile Technologies Ireland Limited
   Distributed under Affero General Public License v3
   
   If you are using this file in YOUR web software, you
   must make your make source code for YOUR web software
   public.

   See LICENSE.txt for more information

   You can obtain non-public copy of Agile Toolkit 4 at
    http://agiletoolkit.org/commercial

 *****************************************************ATK4**/
// Field bundle
include_once'Form/Field.php';
/**
* This class implements generic form, which you can actually use without
* redeclaring it. Just add fields, buttons and use execute method.
*
* @author		Romans <romans@adevel.com>
* @copyright	See file COPYING
* @version		$Id$
*/
class Form_Basic extends AbstractView {
	protected $form_template = null;
	protected $form_tag = null;
	public $errors=array();
							// Here we will have a list of errors occured in the form, when we tried to submit it.
							//  field_name => error

	public $template_chunks=array();
							// Those templates will be used when rendering form and fields

	protected $data = array(); // This array holds list of values prepared for fields before their initialization. When fields
							// are initialized they will look into this array to see if there are default value for them.
							// Afterwards fields will link to $this->data, so changing $this->data['fld_name'] would actually
							// affect field's value.
							//  You should use $this->set() and $this->get() to read/write individual field values. You
							//  should use $this->setStaticSource() to load values from hash
							//  AAAAAAAAAA: this array is no more!

	public $bail_out = false;   // if this is true, we won't load data or submit or validate anything.
	protected $loaded_from_db = false;     // if true, update() will try updating existing row. if false - it would insert new
	public $onsubmit = null;
	public $onload = null;
	protected $ajax_submits=array();	// contains AJAX instances assigned to buttons
	protected $get_field=null;			// if condition was passed to a form throough GET, contains a GET field name
	protected $conditions=array();

	public $js_widget='ui.atk4_form';
	public $js_widget_arguments=array();

	public $default_exception='Exception_ValidityCheck';

	public $dq = null;
	function init(){
		/**
		* During form initialization it will go through it's own template and search for lots of small template
		* chunks it will be using. If those chunk won't be in template, it will fall back to default values. This way
		* you can re-define how form will look, but only what you need in particular case. If you don't specify template
		* at all, form will work with default look.
		*/
		parent::init();

		$this->getChunks();

		// After init method have been executed, it's safe for you to add controls on the form. BTW, if
		// you want to have default values such as loaded from the table, then intialize $this->data array
		// to default values of those fields.
		$this->api->addHook('pre-exec',array($this,'loadData'));
		$this->api->addHook('pre-render-output',array($this,'lateSubmit'));

	}
	protected function getChunks(){
		// commonly replaceable chunks
		$this->grabTemplateChunk('form_comment');
		$this->grabTemplateChunk('form_separator');
		$this->grabTemplateChunk('form_line');      // template for form line, must contain field_caption,field_input,field_error
		if($this->template->is_set('hidden_form_line'))
			$this->grabTemplateChunk('hidden_form_line');
		$this->grabTemplateChunk('field_error');    // template for error code, must contain field_error_str
		//$this->grabTemplateChunk('form');           // template for whole form, must contain Content, form_buttons, form_action,
													//  and form_name
		$this->grabTemplateChunk('field_mandatory'); // template for marking mandatory fields

		// ok, other grabbing will be done by field themselves as you will add them to the form.
		// They will try to look into this template, and if you don't have apropriate templates
		// for them, they will use default ones.
		$this->template_chunks['form']=$this->template;
		if(!$this->template_chunks['form']->is_set('Content')){
			throw new BaseException('Your form template needs to be upgraded for use with 4.1 version. Rename "form_body" tag into "Content". See http://agiletoolkit.org/upgrade_4_1 for more information');
		}
		$this->template_chunks['form']->del('Content');
		$this->template_chunks['form']->del('form_buttons');
		$this->template_chunks['form']->set('form_name',$this->name);
		return $this;
	}

	function initializeTemplate($tag, $template){
		$template = $this->form_template?$this->form_template:$template;
		$tag = $this->form_tag?$this->form_tag:$tag;
		return parent::initializeTemplate($tag, $template);
	}
	function defaultTemplate($template = null, $tag = null){
		if ($template){
			$this->form_template = $template;
		}
		if ($tag){
			$this->form_tag = $tag;
		}
		return array($this->form_template?$this->form_template:"form", $this->form_tag?$this->form_tag:"form");
	}
	function grabTemplateChunk($name){
		if($this->template->is_set($name)){
			$this->template_chunks[$name] = $this->template->cloneRegion($name);
		}else{
			//return $this->fatal('missing form tag: '.$name);
			// hmm.. i wonder what ? :)
		}
	}
	/**
	 * Should show error in field. Override this method to change from default alert
	 * @param object $field Field instance that caused error
	 * @param string $msg message to show
	 */
	function showAjaxError($field,$msg){
        // Depreciated
        return $this->displayFieldError();
    }

    function displayError($field=null,$msg=null){
        if(!$field){
            // Field is not defined
            // TODO: add support for error in template
            $this->js()->univ()->alert($msg?$msg:'Error in form')->execute();
        }
		if(!is_object($field))$field=$this->getElement($field);
		$this->js()->atk4_form('fieldError',$field->short_name,$msg)->execute();
	}
	function addField($type,$name,$caption=null,$attr=null){
		if($caption===null)$caption=ucwords(str_replace('_',' ',$name));

		$last_field=$this->add('Form_Field_'.$type,$name,null,'form_line')
			->setCaption($caption);
		$last_field->template->trySet('field_type',strtolower($type));
		if (is_array($attr)){
			foreach ($attr as $key => $value){
				$this->last_field->setProperty($key, $value);
			}
		}

		return $last_field;
	}
	function disable(){
		// disables last field
		$this->last_field->disable();
		return $this;
	}

	function addComment($comment){
		if(!isset($this->template_chunks['form_comment']))throw new BaseException('This form\'s template ('.$this->template->loaded_template.') does not support comments');
		return $this->add('Text')->set(
			$this->template_chunks['form_comment']->set('comment',$comment)->render()
		);
	}
	function addSeparator($separator_text=''){
		if(!isset($this->template_chunks['form_separator']))return $this;

		$c=clone $this->template_chunks['form_separator'];
		if(!$separator_text)$c->tryDel('separator');else $c->trySet('separator_text',$separator_text);
		return $this->add('Text')->set($c->render());
	}

	// Operating with field values
	function get($field){
		if(!$f=$this->hasField($field))throw new BaseException('Trying to get value of not-existing field: '.$field);
		return ($f instanceof Form_Field)?$f->get():null;
	}
	function clearData(){
		$this->downCall('clearFieldValue');
	}
	function setSource($table,$db_fields=null){
		if(is_null($db_fields)){
			$db_fields=array();
			foreach($this->elements as $key=>$el){
				if(!($el instanceof Form_Field))continue;
				if($el->no_save)continue;
				$db_fields[]=$key;
			}
		}
		$this->dq = $this->api->db->dsql()
			->table($table)
			->field('*',$table)
			->limit(1);
		return $this;
	}
	function set($field_or_array,$value=undefined){
		// We use undefined, because 2nd argument of "null" is meaningfull
		if($value===undefined){
			if(is_array($field_or_array)){
				foreach($field_or_array as $key=>$val){
					if(isset($this->elements[$key]) and $this->elements[$key] instanceof Form_Field)$this->set($key,$val);
				}
				return $this;
			}else{
				throw new ObsoleteException('Please specify 2 arguments to $form->set()');
			}
		}

		if(!isset($this->elements[$field_or_array])){
			foreach ($this->elements as $key => $val){
				echo "$key<br />";
			}
			throw new BaseException("Trying to set value for non-existant field $field_or_array");
		}
		//if($this->elements[$field_or_array] instanceof Form_Button)echo caller_lookup(0);
		if($this->elements[$field_or_array] instanceof Form_Field)
			$this->elements[$field_or_array]->set($value);
		else{
			//throw new BaseException("Form fields must inherit from Form_Field ($field_or_array)");
		}
		return $this;
	}
	function getAllData($include_nosave=false){
		$data=array();
		foreach($this->elements as $key=>$val){
			if($val instanceof Form_Field){
				if($include_nosave||$val->no_save!==true)$data[$key]=$val->get();
			}
		}
		return $data;
	}
	function addSubmit($label='Save',$name=null,$color=null){
		if(!$name)$name=str_replace(' ','_',$label);

		$submit = $this->add('Form_Submit',isset($name)?$name:$label,'form_buttons')
			->setLabel($label)
			->setNoSave();
		if (!is_null($color))
			$submit->setColor($color);

		return $submit;
	}
	function addButton($label,$name=null,$class=null,$style=null){
		if(is_null($name))$name=$label;
		// Now add the regular button first
		$name=str_replace(' ','_',$name);
		return $this->add('Button',$name,'form_buttons')
			->setLabel($label);
	}

	function setCondition($field,$value=null){
		if(!$this->dq)throw new BaseException('Cannot set condition on empty $form->dq');
		$this->dq
			->set($field,$value)
			->where($field,$value);
		$this->conditions[$field]=$value;
		return $this;
	}
	function setConditionFromGET($field='id',$get_field=null){
		// If GET pases an argument you need to put into your where clause, this is the function you should use.
		if(!isset($get_field))$get_field=$field;
		$this->get_field=$field;
		$this->api->stickyGET($get_field);
		return $this->setCondition($field,$_GET[$get_field]);
	}
	function addConditionFromGET($field='id',$get_field=null){
		$this->setConditionFromGET($field,$get_field);
	}
	function addCondition($field,$value=null){
		return $this->setCondition($field,$value);
	}
	function loadData(){
		/**
		* This call will be sent to fields, and they will initialize their values from $this->data
		*/
		if($this->bail_out)return;
		if($this->dq){
            // TODO: move into Controller / hook

			// if no condition set, use id is null condition
			if(empty($this->conditions))$this->setCondition('id',null);
			// we actually initialize data from database
			$data = $this->dq->do_getHash();
			if($data){
				$this->set($data);
				$this->loaded_from_db=true;
			}
		}
		$this->hook('post-loadData');
	}

	function isLoadedFromDB(){
		return $this->loaded_from_db;
	}
	function update(){
		if(!$this->dq)throw new BaseException("Can't save, query was not initialized");
		if(!is_null($this->get_field))$this->api->stickyForget($this->get_field);
		foreach($this->elements as $short_name => $element)
			if($element instanceof Form_Field)if(!$element->no_save){
				//if(is_null($element->get()))
				$this->dq->set($short_name, $element->get());
		}
		if($this->loaded_from_db){
			// id is present, let's do update
			return $this->dq->do_update();
		}else{
			// id is not present
			return $this->dq->do_insert();
		}
	}
	function submitted(){
        /* downcall from ApiWeb */
		/**
		* Default down-call submitted will automatically call this method if form was submitted
		*/
		// We want to give flexibility to our controls and grant them a chance to
		// hook to those spots here.
		// On Windows platform mod_rewrite is lowercasing all the urls.
		if($_GET['submit']!=$this->name)return;
		if($this->bail_out)return;
		$this->downCall('loadPOST');
		$this->downCall('validate');

        if(!empty($this->errors))return false;
        try{
            if(($output=$this->hook('submit',array($this)))){
                if(!is_array($output))$output=array($output);
                $this->js(null,$output)->execute();
            }
        }catch (Exception_ValidityCheck $e){
            $f=$e->getField();
            if($f && is_string($f) && $fld=$this->hasElement($f)){
                $fld->displayFieldError($e->getMessage());
            } else $this->js()->univ()->alert($e->getMessage().' in undefined field')->execute();
        }
        return true;
	}
    function lateSubmit(){
		if(@$_GET['submit']!=$this->name)return;

        if($this->bail_out || $this->isSubmitted()){
            $this->js()->univ()->consoleError('Form '.$this->name.' submission is not handled. See: http://agiletoolkit.org/doc/form/submit')->execute();
        }
    }
	function isSubmitted(){
		// This is alternative way for form submission. After  form is initialized you can call this method. It will
		// hurry up all the steps, but you will have ready-to-use form right away and can make submission handlers
		// easier
		$this->loadData();
		$result = $_POST && $this->submitted();
		$this->bail_out=true;
		return $result;
	}
    function onSubmit($callback){
        $this->addHook('submit',$callback);
    }
	function setLayout($template){
		// Instead of building our own Content we will take it from
		// pre-defined template and insert fields into there
		$this->template_chunks['custom_layout']=$this->add('SMLite')->loadTemplate($template);
		$this->template_chunks['custom_layout']->trySet('_name',$this->name);
		$this->template->trySet('form_class_layout',$c='form_'.basename($template));
		return $this;
	}
    function setFormClass($class){
        $this->template->trySet('form_class',$class);
        return $this;
    }
	function render(){
		// Assuming, that child fields already inserted their HTML code into 'form'/Content using 'form_line'
		// Assuming, that child buttons already inserted their HTML code into 'form'/form_buttons

		if($this->js_widget){
			$fn=str_replace('ui.','',$this->js_widget);
			$this->js(true)->_load($this->js_widget)->$fn($this->js_widget_arguments);
		}

		if(isset($this->template_chunks['custom_layout'])){
			foreach($this->elements as $key=>$val){
				if($val instanceof Form_Field){
					$prop=$this->template_chunks['custom_layout']->get($key);
					if(is_array($prop))$prop=join(' ',$prop);
					if($prop){
						$val->setProperty('style',$prop);
					}
					if(!$this->template_chunks['custom_layout']->is_set($key)){
						$this->js(true)->univ()->log('No field in layout: '.$key);
					}
					$this->template_chunks['custom_layout']->trySet($key,$val->getInput());

					if($this->errors[$key]){
						$this->template_chunks['custom_layout']
							->trySet($key.'_error',$val->error_template->set('field_error_str',$this->errors[$key])->render());
					}
				}
			}
			$this->template->set('Content',$this->template_chunks['custom_layout']->render());
		}
		$this->template_chunks['form']
			->set('form_action',$this->api->getDestinationURL(null,array('submit'=>$this->name)));
		$this->owner->template->append($this->spot,$r=$this->template_chunks['form']->render());
	}
	function hasField($name){
		return isset($this->elements[$name])?$this->elements[$name]:false;
	}
	function isClicked($name){
		return $_POST['ajax_submit']==$name;
	}
	/* external error management */
	function setFieldError($field, $name){
		if (isset($this->errors[$field])){
			$existing = $this->errors[$field];
		} else {
			$existing = null;
		}
		$this->errors[$field] = $existing . $name;
	}
	/**
	 * Makes field's value set to null if empty value has been specified
	 */
}
?>
