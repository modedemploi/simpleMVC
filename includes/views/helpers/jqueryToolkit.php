<?php
class jqueryToolkit_viewHelper extends jsPlugin_viewHelper{
	public $requiredFiles   = array(
		'js/jquery.toolkit/src/jquery.toolkit.js',
		'js/jquery.toolkit/src/jquery.toolkit.css',
	);
	public $requiredPlugins = array('jquery');

	static public $toolkitSrcPath = 'js/jquery.toolkit/src';
	static public $toolkitPluginsPath = 'js/jquery.toolkit/src/plugins';

	static public $subPlugins = array(
		'positionable' => 'position',
		'positionRelative'=> 'position',
		'notifybox'=> 'notify',
		'confirmbox'=> 'dialogbox',
	);
	static public $dependencies = array(
		'tooltip' => 'position',
		'validable' => 'tooltip'
	);
	static public $defaultsOptions = array(
		'tabbed' => array(
			'fixedHeight'=>false
		)
	);

	function jqueryToolkit($pluginNames=null){
		if( null !== $pluginNames)
			$this->initPlugins($pluginNames);
	}

	function loadPlugin($pluginName){
		static $loaded = array();
		if( strpos($pluginName,'|') )
			$pluginName = explode('|',$pluginName);
		if( is_array($pluginName)){
			foreach($pluginName as $n)
				$this->loadPlugin($n);
			return $this;
		}
		if( isset(self::$subPlugins[$pluginName]))
			$pluginName = self::$subPlugins[$pluginName];
		if( isset(self::$dependencies[$pluginName]))
			$this->loadPlugin(self::$dependencies[$pluginName]);

		$_pluginName = strtolower($pluginName);
		if( empty($loaded[$_pluginName])){
			if( $pluginName==='storage' ){
				$path = self::$toolkitSrcPath;
				$prefix = 'jquery.toolkit.';
			}else{
				$path = self::$toolkitPluginsPath."/$pluginName"	;
				$prefix = 'jquery.tk.';

			}
			if( is_file(ROOT_DIR.'/'.($f = "$path/$prefix$pluginName.js")) )
				$this->_js_includes($f);
			if( is_file(ROOT_DIR.'/'.($f = "$path/$prefix$pluginName.css")) )
				$this->_js_includes($f);
			$loaded[$_pluginName] = true;
		}
		return $this;
	}

	function initPlugins($pluginNames){
		$this->loadPlugin($pluginNames);
		$this->_js_script('$.toolkit.initPlugins("'.(is_array($pluginNames)?implode('|',$pluginNames):$pluginNames).'");');
	}

	function notify($msg,$state=null,array $options=null){
		$this->loadPlugin('notify');
		if( $state !== null )
			$options['state'] = $state;
		$this->_js_script('$.tk.notify.msg("'.str_replace("'","\'",$msg).'"'.(empty($options)?'':','.self::_optionString($options)).');');
		//'.$state?$('<div ".($state?'class="tk-state-'.$state.'"':'').'>'.str_replace("'","\'",$msg).'</div>'."').notify(".self::_optionString($options).");");
	}

	function tabbed($tabSelector='.tk-tabbed',array $options=null){
		$this->loadPlugin('tabbed');
		$options = array_merge(self::$defaultsOptions['tabbed'],(array) $options);
		$this->_js_script("$('$tabSelector').tabbed(".self::_optionString($options).');');
	}


}