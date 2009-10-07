<?php
/**
* abstract class to assist in defining new jsPlugins.
* defining a new jsPlugin is quite simple:
* - extends this class
* - define static $requireFiles and eventual $requiredPlugins
* - eventually define an init() method to take particuliar actions at load time.
* @class jsPlugin_viewHelper
* @changelog
*            - 2009-03-24 - add common method getuniqueId
*/
abstract class jsPlugin_viewHelper extends abstractViewHelper{
	public $requiredFiles   = array();
	public $requiredPlugins = array();
	public function __construct(viewInterface $view){
		parent::__construct($view);
		#- ensure that js plugin is loaded
		$this->helperLoad('js');
		#- load required plugins
		if( ! empty($this->requiredPlugins)){
			foreach($this->requiredPlugins as $p)
				$this->view->_js_loadPlugin($p);
		}
		#- include required Files
		foreach($this->requiredFiles as $f)
			$this->view->_js_includes($f,preg_match('!^http!',$f)?true:false);
		#- exectute init method if exists
		if(method_exists($this,'init'))
			$this->init();
		#- register plugin
		$this->view->_js_registerPlugin($this);
	}
	final static public function uniqueId(){
		static $id;
		if( ! isset($id) )
			$id=0;
		return 'jsPlugin'.(++$id);
	}
}
