<?php
/**
* @changelog
*            - 2009-04-01 - add $excluded parameter
*/
class adminModelsMenu_viewHelper extends abstractViewHelper{

	static $adminModelsControllerName = 'adminModels';

	function adminModelsMenu($id="sMVCmodelsList",$withConfigOption=false,$withRegenLink=false,$excluded=null){
		#- recupere la liste des models
		$modelDir = defined('MODELS_DIR')?MODELS_DIR:LIB_DIR.'/models';
		if(! is_dir($modelDir)){
			return '';
		}
		$models = array_map('basename',glob("$modelDir/*.php"));
		$items = array();
		$itemStr = '<li><div  class="ui-buttonset"><a href="'.$this->url('adminmodels:list',array('modelType'=>'%1$s'),true).'" class="ui-button" title="list">%1$s</a>'
			.($withConfigOption?'<a href="'.$this->url('adminmodels:configure',array('modelType'=>'%1$s'),true).'" class="ui-button ui-button-i-wrench" title="configure">configure</a>':'')
			.'</div></li>';
		foreach($models as $m){
			if( preg_match('!^BASE_!',$m) || (null !== $excluded && preg_match('!^('.$excluded.')\.php$!',$m)) )
				continue;
			$items[] = sprintf($itemStr,match('!(.*)\.php$!',$m));
		}
		if( $withRegenLink ){
			$items[] = '<li><a href="'.$this->url('adminmodels:generation',array('modelType'=>'fake')).'" class="ui-button ui-button-small-gear">Model (re-)generation</a></li>';
		}
		return  '<ul id="'.$id.'" >'.implode('',$items).'</ul>';
	}
}
