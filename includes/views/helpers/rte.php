<?php
/**
* helper to easily incorporate jquery based Textarea
* @package simpleMVC
* @changelog
*            - 2009-07-16 - make some js code external and add dialog box for table creation.
*            - 2009-03-31 - add rteOptions to list of possible options
*            - 2009-03-27 - integration of filemanager plugin for image selection and replace dialogs with jqueryUI dialogs
*/

class rte_viewHelper extends  jsPlugin_viewHelper{
	/** path relative to jQuery_viewHelper::$pluginPath/$pluginName */
	public $requiredFiles = array(
		'js/jqueryPlugins/jqueryRte/jquery.rte.js',
		'js/jqueryPlugins/jqueryRte/rte.css',
		'js/simpleMVC_jquery.rte.js'
	);
	public $requiredPlugins = array(
		'filemanager'
	);
	///protected $areaRegistered=false;


	//-- optionnal list of default options for rte. such as button set etc...
	static public $defaultRteOptions = array(
		'imgPath' => null,
		'css_url' => null,
		'classOptions' => array(
				array('small','small'),
				array('hr:spacer','spacer')
		),
	);

	function init(){
		//-- define default rte options
		if( ! defined('FRONT_URL'))
			define('FRONT_URL',ROOT_URL.'/front');

		if( null === self::$defaultRteOptions['imgPath'] )
			self::$defaultRteOptions['imgPath'] = ROOT_URL.'/js/jqueryPlugins/jqueryRte/';
		if( null === self::$defaultRteOptions['css_url'] )
			self::$defaultRteOptions['css_url'] = ROOT_URL.'/'.FRONT_NAME.'.css';

		$defaultOpts = json_encode(self::$defaultRteOptions);

		$fmOptions = filemanager_viewHelper::$defaultOptions;
		$fmOptions['prefixValue'] = USER_DATAS_URL;
		$fmOptions = json_encode($fmOptions);

		$this->_js_scriptOnce("
		$.extend($.fn.rte.defaults,{
			insertImage:function(e){
				var rte = e.data.rte;
				if( rte.textarea.is(':visible') )
						return false;
				var range = rte.getSelectedElement(true);
				cbRteImageDialog(rte,range);
				return false;
			},
			insertTable:function(e){
				var rte = e.data.rte;
				if( rte.textarea.is(':visible') )
						return false;
				var range = rte.getSelectedElement(true);
				cbRteTableDialog(rte,range);
				return false;
			},
			createLink:function(e){
				var rte = e.data.rte;
				if( rte.textarea.is(':visible') )
					return false;
				if(! rte.hasSelection()){
					alert('La selection est vide, impossible de créer un lien.');
					return false;
				}
				// we must keep trace of range before focus change under IE
				var range = rte.getSelectedElement(true);
				cbRteLinkDialog(rte,range);
				return false;
			}
		},$defaultOpts);
		rteFmanagerOptions = $fmOptions;",'rteHelperInit');
	}

	/**
	* return necessary code to render a wysiwyg editor in your page.
	* @param string $areaSelector string to select textarea
	* @param mixed  $areaOption   this parameter permit you to quickly render textarea.
	*                             by default leave this null and it will look for an existing textarea in your template.
	*                             you can pass some jquery.rte options as a json sting or array that will be json_encode as rteOpts
	*                             for example you can set buttonSet like this: rte('myarea',array('rteOpts'=>'{buttonSet:'format|class|bold|underline'}'))
	*                             Providing any other options will render the textarea with the given attribute,
	*                             for exemple rte('myArea',array('value'=>'my area content','style'=>'border:solid silver 1px'));
	*                             will return "<textarea name="myArea" style="border:solid silver 1px;">my area content</textarea>.
	*                             Provide anything other than array or null will result in returning a basic empty textarea
	*                             with empty value.
	*                             if any options are passed the $textareaSelector will be used as attribute name and id for the generated textarea.
	*  @return string.
	*/
	function rte($areaSelector,$areaOptions=null){
		static $fmLoaded =false;
		if($fmLoaded){
			$preStr='';
		}else{
			$preStr = $this->filemanager('rteFm',array('isDialog'=>true,'prefixValue'=>USER_DATAS_URL));
			$fmLoaded=true;
		}
		#- ~ if( !$this->areaRegistered ){
			#- ~ $this->areaRegistered = true;
			#- ~ $this->js("$('.jqueryRte').rte();");
		#- ~ }
		if( $areaOptions === null )
			return $this->js("$('$areaSelector').rte();");

		if( ! is_array($areaOptions) ){
			$this->js("$('#$areaSelector').rte();");
			return "<textarea name=\"$areaSelector\" id=\"$areaSelector\" class=\"jqueryRte\"></textarea>\n";
		}else{
			$attrs = array();
			$value = '';
			if(! isset($areaOptions['id']))
				$areaOptions['id'] = $areaSelector;
			$rteOpts='';
			if( !empty($areaOptions['rteOpts'])){
				$rteOpts = is_array($areaOptions['rteOpts'])?json_encode($areaOptions['rteOpts']):$areaOptions['rteOpts'];
				unset($areaOptions['rteOpts']);
			}
			foreach($areaOptions as $k=>$v){
				if(strtolower($k)==='value' ){
					$value = $v; continue;
				}
				if( $v === '') continue;
				$attrs[] = $k.'="'.preg_replace('/(?<!\\\\)"/','\"',$v).'"';
			}
			$this->js("$('#$areaOptions[id]').rte($rteOpts);");
			return $preStr.'<textarea name="'.$areaSelector.'"'.(empty($attrs)?'':' '.implode(' ',$attrs))." class=\"jqueryRte\">$value</textarea>\n";
		}
	}

}
