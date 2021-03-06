<?php
/**
* @class adminmodelsController
* @package simpleMVC
* @licence LGPL
* @author Jonathan Gotti < jgotti at jgotti dot net >
* @svnInfos:
*            - $LastChangedDate$
*            - $LastChangedRevision$
*            - $LastChangedBy$
*            - $HeadURL$
* @changelog
* - 2011-09-29 - new listExportAction() method
* - 2011-09-14 - bug correction on sql listing regarding moveUp/moveDown buttons
* - 2011-09-13 - now list manage 2 types of listing, sortTable and SQL
* - 2011-01-04 - bug correction regarding loosing primaryKey value when saving with a mismatch confirm field
* - 2010-11-25 - add a getFiltersArray() and prepareFilters() methods
* - 2010-09-05 - add a try/catch on save
* - 2010-08-13 - now will conserve config defined inside class over thoose defined in simpleMVC_xxx_config
* - 2010-02-26 - little rewrite of list filters support
* - 2010-02-12 - bug correction for list configuration in configure action
* - 2010-02-08 - make it compatible with url,redirectAction and forward that dropped support for controllerName as second argument
* - 2010-01-19 - configure: add possibility to use groupMethod with only one fieldSet
*              - improve multilingual support
* - 2010-01-15 - bug correction on save
* - 2010-01-13 - add differents pageTitles for add and edit actions.
* - 2009-09-xx - first attempt for validation integration.
* - 2009-08-24 - add filters to moveUp/Down and [de]activate links
* - 2009-07-06 - now load config for each action
* - 2009-07-02 - add LIST_FILTER configuration
* - 2009-06-22 - add support for given modelCollection in extended listAction must be set as $this->_models_
* - 2009-06-04 - add model and config file edition
*              - all configuration methods are disabled when not in devel mode
* - 2009-06-02 - add configuration for allowedActions
*              - add support for confirmation fields and sprintFatas to langMsg methods
* - 2009-05-28 - ncancel:loading of config file for ACTION allowed and add allowed action property $allowedActions
* - 2009-05-05 - better admin forms generation (grouping/ordering inputs fields)
* - 2009-04-06 - add support for activable models
* - 2009-03-31 - autodetection of field that need to be loaded when loadDatas is empty
* - 2009-03-19 - rewrite support for orderable models
*              - set_layout now consider for adminmodelsModelType templates
* - 2009-03-13 - made some change to list configuration to support ordering and formatStr
*              - put configFile as protected instead of private to permitt extended class to access it
* - 2009-03-12 - bug correction in getting modelFilePath from model with uppercase letter in modelName
*              - better handling of editing langMessage from empty dictionnaries
* - 2009-03-08 - little modif in setDictName to check dictionnaries from generated with adminmodelsController
* - 2009-02-08 - add some automated support for orderable models
* - 2009-01-14 - new methods setDictName and langMsg to better handle langManager dictionnary lookup
* - 2009-01-14 - new property $loadDatas to force loadDatas before rendering list.
* - 2009-01-05 - now listAction do a lookup on list headers using langManager::msg
* - 2008-09-11 - define dummy indexAction that forward to listAction
*              - remove setLayout from formAction
*              - list without listFields setted will ask value from model instead of taking it directly from model->datas
*/
abstract class abstractAdminmodelsController extends abstractController{

	public $modelType = null;
	/**
	* list of fields to show in generated model list
	* key are property names and values will be used as headers.
	*/
	public $listFields=array();
	/**
	* optional list of related model properties to load to properly render the list
	* (kindof performance optimisation to avoid each models to be load one by one)
	*/
	public $loadDatas = null;

	/** config of (un)doable basic actions */
	protected $_allowedActions=array(
		'edit'=>true,
		'list'=>true,
		'del'=>true,
		'add'=>true,
		'export'=>false
	);
	protected $configFile = '';
	protected $_config = array();
	protected $_modelConfig = array();
	/** @var set one or multiple databases connection constants names to generate model from */
	protected $dbConnectionsDefined = array('DB_CONNECTION');
	/**
	* @var use for modelGeneration to determine single form of plural table name
	* list of plural=>single or plural=>array(single1,single2) in which case the first will be the one to use for related hasOne
	*/
	public $singulars = array();
	/**
	* method that extended class should override to check if user is allow or not to access to extended controller or not.
	*/
	abstract function check_authorized();
	function init(){
		parent::init();
		if(! $this->check_authorized()){
			self::appendAppMsg('Unhautorized access.','error');
			return $this->redirectAction(ERROR_DISPATCH);
		}

		if(! $this->modelType )
			$this->modelType = isset($_POST['modelType'])?$_POST['modelType']:(isset($_GET['modelType'])?$_GET['modelType']:false);
		if( ! $this->modelType){
			self::appendAppMsg('Missing modelType error','error');
			return $this->redirectAction(ERROR_DISPATCH);
		}
		$this->view->modelType = $this->modelType;
		$this->view->setLayout(array(
			'header.tpl.php',
			($this->getName()=='adminmodels'?strtolower($this->modelType).'_:action.tpl.php|':'').':controller_:action.tpl.php|models_:action.tpl.php|default_:action.tpl.php',
			'footer.tpl.php'
		));

		$this->configFile = CONF_DIR.'/simpleMVCAdmin_'.FRONT_NAME.'_config.php';
		if( DEVEL_MODE_ACTIVE() ){
			if(! is_file($this->configFile) && is_writable(CONF_DIR))
				touch($this->configFile);
			if(! is_writable($this->configFile) ){
				self::appendAppMsg("$this->configFile isn't writable.",'error');
			}
		}
		$this->loadModelConfig();
		$this->pageTitle = ucFirst(langManager::msg($this->modelType,null,$this->getName().'_'.$this->modelType.'|'.$this->getName().'|default'));
	}

	function _isAllowedAction_($action,$msg=null,$dispatchRedirect=DEFAULT_DISPATCH){
		if( empty($this->_allowedActions[$action]) ){
			if( null === $msg)
				$msg = 'unauthorized action!';
			if($msg)
				self::appendAppMsg($msg,'error');
			return $this->redirectAction($dispatchRedirect,array('modelType'=>$this->modelType,'embed'=>empty($_GET['embed'])?'':'on'));
		}
		return true;
	}

	function loadModelConfig(){
		if(! file_exists($this->configFile) )
			return false;
		$this->_config = parse_conf_file($this->configFile,true);
		foreach($this->_config as $k => $v){
			if( preg_match('!^(LIST(?:_FILTERS)?|ACTION|FORM(?:_ORDER)?)_'.$this->modelType.'$!',$k,$m)){
				if(! isset($this->_modelConfig[$m[1]])){
					#- $this->_modelConfig[$m[1]] = json_decode($v,$m[1]==='FORM_ORDER'?false:true);
					$this->_modelConfig[$m[1]] = json_decode($v,true);
				}
				if( $m[1] === 'ACTION' )
					$this->_allowedActions = $this->_modelConfig['ACTION'];
			}else if( preg_match('!^(LIST(?:_TYPE))_'.$this->modelType.'$!',$k,$m) && ! isset($this->_modelConfig[$m[1]]) ){
				$this->_modelConfig[$m[1]] = $v;
			}
		}
		$this->view->_config = $this->_config;
		$this->view->_modelConfig = $this->_modelConfig;
	}


	###--- current manipulation actions (list/add/edit/del...) ---###
	function indexAction(){
		return $this->forward('list');
	}


	static function prepareFilters(array $filters=null,array $AuthorizedFilters=null){
		$_filters = array();
		$filters = self::getFiltersArray($filters,$AuthorizedFilters);
		foreach($filters as $k=>$v){
			if( strlen($v) ){
				$_filters[] = "$k,$v";
		}
		}
		return implode(',',$_filters);
	}
	static function getFiltersArray(array $filters=null,array $AuthorizedFilters=null){
		// manage user given filters
		if(! is_array($filters) ){
			$filters = array();
		}else{
			foreach($filters as $k=>$v){
				if(! strlen($v) || ( $AuthorizedFilters!==null && ! in_array($k,$AuthorizedFilters,true)) )
					unset($filters[$k]);
			}
		}
		if( empty($_GET['_filters']))
			return $filters;
		//-- manage get ginven filters
		$getFilters = match('!(?<=^|,)([^,]+?),([^,]+?)(?=,|$)!',$_GET['_filters'],array(1,2),true);
		if( empty($getFilters))
			return is_array($filters)?$filters:array();
		$getFilters = array_combine($getFilters[0],$getFilters[1]);
		if( $AuthorizedFilters !== null){
			foreach($getFilters as $k=>$v){
				if(! in_array($k,$AuthorizedFilters,true))
					unset($getFilters[$k]);
			}
		}
		return is_array($filters)?array_merge($getFilters,$filters):$getFilters;
	}

	function filteredListAction(){
		//-- override _filters with thoose coming from post
		$_GET['_filters'] = self::prepareFilters(self::getFiltersArray($_POST));
		$this->forward('list');
	}

	function listAction(){
		$this->_isAllowedAction_('list');
		$PKname = abstractModel::_getModelStaticProp($this->modelType,'primaryKey');
		$conds = array();

		// if( ! empty($this->_config) ){
			if( !empty($this->_modelConfig['LIST']) ){
				$this->listFormats=$this->_modelConfig['LIST'];
				if( empty($this->listFields) ){
					$listKeys = array_keys($this->_modelConfig['LIST']);
					$this->listFields = array_combine($listKeys,$listKeys);
				}
			}

			if( ( !empty($this->_modelConfig['LIST_FILTERS']) ) && ! empty($_GET['_filters']) ){
				$dbAdapter = abstractModel::getModelDbAdapter($this->modelType);
				$filters = self::getFiltersArray(null,empty($this->_modelConfig['LIST_FILTERS'])?array():array_keys($this->_modelConfig['LIST_FILTERS']));
				if( ! empty($filters)){
					$datasDefs = abstractModel::_getModelStaticProp($this->modelType,'datasDefs');
					foreach($filters as $field=>$filter ){
						switch($this->_modelConfig['LIST_FILTERS'][$field]){
							case 'like':
								$conds[0] = (empty($conds)?'WHERE ':"$conds[0] AND ").$dbAdapter->protect_field_names($field).' LIKE ?';
								$conds[] = '%'.$filter.'%';
								break;
							default:
								$conds[0] = (empty($conds)?'WHERE ':"$conds[0] AND ").$dbAdapter->protect_field_names($field)."=?";
								$conds[] = $filter;
								break;
						}
					}
					if( !empty($conds)){
						$this->fieldFilters = $filters;
					}
				}
			}
		// }
		// List Filters for URLs
		$filter = empty($this->fieldFilters)?'':'/_filters/'.self::prepareFilters($this->fieldFilters);

		if( empty($this->_modelConfig['LIST_TYPE']) || $this->_modelConfig['LIST_TYPE'] !== 'sql' ){
			$needPreorder = true;
			if(! isset($this->_modelConfig['LIST_TYPE']) ){
				$this->_modelConfig['LIST_TYPE'] = 'js';
			}
			if(! empty($conds) ){
				$this->_models_ = abstractModel::getFilteredModelInstances($this->modelType,$conds);
			}
			$models = (isset($this->_models_) && $this->_models_ instanceof modelCollection )?$this->_models_:abstractModel::getAllModelInstances($this->modelType);
			$this->totalRows = $models->count();
		}else{
			$needPreorder = false;
			# pagination / ordering datas
			$listParams = array(
				'pageNb'=>1
				,'pageSize'=>10
				,'orderBy'=> $PKname
				,'orderWay'=>'ASC'
			);
			foreach( $listParams as $param=>$dfltVal){
				$clean = false;
				if( isset($_POST[$param])){
					$listParams[$param] = $_POST[$param];
					$clean = true;
				}else if( isset($_GET[$param])){
					$listParams[$param] = $_GET[$param];
					$clean = true;
				}else if( isset($_SESSION['sqlist'][$this->modelType][$param]) ){
					$listParams[$param] = $_SESSION['sqlist'][$this->modelType][$param];
					$clean = true;
				}
				if( $clean ){
					//-- make parameters safe
					switch(gettype($dfltVal)){
						case 'string':
							$listParams[$param] = preg_replace('![^a-z0-9_-]!i','',$listParams[$param]);
							break;
						default:
							settype($listParams[$param],'int');
					}
				}
			}
			$_SESSION['sqlist'][$this->modelType] = $listParams;
			//-- get listing datas
			$pageSizeSelector = '
			<select name="pageselectorsortTablesizes" id="pageselectorsortTablesizes">
				<option'.($listParams['pageSize']===10?' selected="selected"':'').'>10</option>
				<option'.($listParams['pageSize']===20?' selected="selected"':'').'>20</option>
				<option'.($listParams['pageSize']===30?' selected="selected"':'').'>30</option>
				<option'.($listParams['pageSize']===50?' selected="selected"':'').'>50</option>
				<option'.($listParams['pageSize']===100?' selected="selected"':'').'>100</option>
			</select>';
			abstractModel::_setModelPagedNav($this->modelType,array(
				'linkStr'=>$this->url('list',array('modelType'=>$this->modelType,'_filters'=>self::prepareFilters($this->fieldFilters),'pageNb'=>'%page'),true)
				,'first' => '<a href="%lnk" class="pagelnk">&lt;&lt;</a>'
				,'prev'  => '<a href="%lnk" class="pagelnk">&lt;</a>'
				,'next'  => '<a href="%lnk" class="pagelnk">&gt;</a>'
				,'last'  => '<a href="%lnk" class="pagelnk">&gt;&gt;</a>'
				,'curpage'  => '<input type="text" value="%page" onfocus="this.value=\'\';" onkeydown="if(event.keyCode==13){ var p=parseInt(this.value)||1;window.location=\'%lnk\'.replace(/pageNb\/%page/,\'pageNb\/\'+(p>%nbpages?%nbpages:(p<1?1:p)));return false;}" size="3" title="aller &agrave; la page" style="text-align:center;" />'
				,'formatStr'=> '<span style="float:right;" class="sorttable-pagesize-setting">'.langManager::msg('show %s rows',$pageSizeSelector).'</span><div style="white-space:nowrap;" class="sorttable-pagenav-settings">%first %prev page %1links / %nbpages %next %last</div>'
			));
			$conds[0] = (empty($conds[0])?'':$conds[0].' ')
				."ORDER BY ".abstractModel::getModelDbAdapter($this->modelType)->protect_field_names($listParams['orderBy'])." "
				.(in_array(strtoupper($listParams['orderWay']),array('ASC','DESC'),true)?$listParams['orderWay']:'asc')
			;
			list($models,$this->navStr,$this->totalRows) = abstractModel::getPagedModelInstances(
				$this->modelType
				,$conds
				,$listParams['pageNb']
				,$listParams['pageSize']
			);
		}

		$this->view->assign('_smvcAllowedAction',$this->_allowedActions);
		$this->setDictName();
		$supportedAddons = abstractModel::_modelGetSupportedAddons($this->modelType);
		$orderable = in_array('orderable',$supportedAddons);
		$activable = in_array('activable',$supportedAddons);

		if(empty($this->loadDatas)){ //-- attempt to autodetect datas that may need to be loaded
			$relDefs = abstractModel::modelHasRelDefs($this->modelType,null,true);
			foreach(array_keys($this->listFields) as $fld){
				if( isset($relDefs['hasOne'][$fld]) || isset($relDefs['hasMany'][$fld]) )
					$this->loadDatas = empty($this->loadDatas)?$fld:"$this->loadDatas|$fld";
			}
		}
		$models->loadDatas(empty($this->loadDatas)?null:$this->loadDatas);
		$datas = array();

		if( count($models) ){
			$this->view->_js_loadPlugin('jqueryui');
			//-- prepare common modelAddons management
			$orderableField = null;
			if( $orderable ){
				list($orderableField,$orderableGroupField) = $models->current()->_getOrderableFields();
				if( $needPreorder ){
					$models->sort($orderableField);
				}
				$orderableLastPos = array();
				if(! $orderableGroupField ){
					$orderableLastPos[] = $models->current()->orderableGetLastPK();
				}else{
					$orderableLastPos = $models->filterBy($orderableField,0)->orderableGetLastPK();
					if( $needPreorder ){
						$models->sort($orderableGroupField);
					}
				}
			}

			if( empty($this->listFields)){
				$this->listFields = array_keys(abstractModel::_getModelStaticProp($this->modelType,'datasDefs'));
				$this->listFields = array_combine($this->listFields,$this->listFields);
			}
			$nbZeroFill = strlen($models->count());
			foreach($models as $m){
				$row = array();
				$row['id'] = $m->PK.'/modelType/'.$this->modelType;
				foreach($this->listFields as $k=>$v){
					if(!empty($this->listFormats[$k])){
						$row[$k] = $m->__toString($this->listFormats[$k]);
						continue;
					}
					switch($k){
						case $orderableField:
							$row[$k] = '<span style="display:none;">'.sprintf('%0'.$nbZeroFill.'d',$m->{$k}).'</span><div class="ui-buttonset ui-buttonset-small"><a title="move up" href="'.($m->{$k}>0?$this->url('moveup').'/id/'.$row['id'].$filter.'" class="ui-button ui-button-i-arrow-1-n':'#" class="ui-button ui-button-i-arrow-1-n ui-state-disabled').'">move up</a>'
								.'<a href="'.(in_array($m->PK,$orderableLastPos)?'#" class="ui-button ui-button-i-arrow-1-s ui-state-disabled':$this->url('movedown').'/id/'.$row['id'].$filter.'" class="ui-button ui-button-i-arrow-1-s').'" title="move down">move down</a></div>';
							break;
						default:
							if(! ($activable && in_array($k,$m->_activableFields,'true')) ){
								$row[$k] = $m->{$k} instanceof abstractModel?$m->{$k}->_toString():$m->{$k};
							}else{
								$active=$m->{$k}?true:false;
								$title = ($active?'de-':'').'activate';
								$row[$k] = '<a href="'.$this->url('setActive').'/state/'.($active?0:1).'/prop/'.$k.'/id/'.$row['id'].$filter
									.'" title="'.$title.'" class="ui-button ui-button-tiny-i-'.($active?'check':'cancel').'">'.($active?'yes':'no').'</a>';
							}
							break;
					}
				}
				if( $this->_modelConfig['LIST_TYPE'] === 'sql' )
					$row['id'] = $m->PK;
				$datas[] = $row;
			}
			$this->view->listHeaders = array_map(array($this,'langMsg'),$this->_modelConfig['LIST_TYPE']==='sql'?$this->listFields:array_values($this->listFields));
		}
		// Add filters to id (for edit&trash buttons)
		if(!empty($this->fieldFilters)&& $count = count($datas)){
			for($i = 0 ; $i < $count ; $i++)
				$datas[$i]['id'] .= $filter ;
		}
		$this->_models_ = $models;
		$this->view->listDatas = $datas;
	}

	function exportAction(){
		$this->_isAllowedAction_('export');
		//-- force list type to js to retrieve all datas.
		$this->_modelConfig['LIST_TYPE'] = 'js';
		$this->listAction();
		#- print_r($this->getDatas());
		$csv = new csv('php://output','a',';');
		header('Content-Type: application/csv');
		header('Content-Disposition: attachment; filename="'.$this->modelType.'.csv"');
		$headers = array_keys($this->listDatas[0]);
		if( isset($this->listHeaders) ){
			$headers = array_merge(array('id'),$this->listHeaders) + $headers;
		}
		$csv->append($headers);
		foreach($this->listDatas as $row){
			$row['id'] = strtok($row['id'],'/');
			$csv->append(array_map('strip_tags',$row));
		}
		smvcShutdownManager::shutdown(0,true);
	}
	function addAction(){
		$this->setDictName();
		if(empty($this->fieldFilters) && !empty($_GET['_filters'])) {
			$this->fieldFilters = self::getFiltersArray(null,empty($this->_modelConfig['LIST_FILTERS'])?array():array_keys($this->_modelConfig['LIST_FILTERS']));
		}
		if(!empty($this->fieldFilters))
			$this->view->assign($this->fieldFilters) ;
		$this->pageTitle = $this->langMsg('Add new '.$this->modelType);
		return $this->forward('form');
	}
	function editAction(){
		$this->setDictName();
		$this->_isAllowedAction_('edit');
		if(! isset($_GET['id']) ){
			self::appendAppMsg('Identifiant d\'enregistrement à modifié manquant','error');
			return $this->redirectAction('list',array('modelType'=>$this->modelType));
		}
		$model = abstractModel::getModelInstance($this->modelType,$_GET['id']);
		if(! $model instanceof $this->modelType ){
			self::appendAppMsg('Enregistrement inexistant en base de données','error');
			return $this->redirectAction('list',array('modelType'=>$this->modelType));
		}
		$this->view->_model_ = $model;
		$this->view->assign($model->datas);
		$this->pageTitle = $this->langMsg('Edit '.$this->modelType);
		return $this->forward('form');
	}
	function formAction(){
		$this->_isAllowedAction_(null===$this->view->_model_?'add':'edit',null,$this->getName().':list');
		$this->view->datasDefs = abstractModel::_getModelStaticProp($this->modelType,'datasDefs');
		$this->view->relDefs   = abstractModel::modelHasRelDefs($this->modelType,null,true);
		$args = array('modelType'=>$this->modelType) ;
		if (!empty($_GET['_filters']))
			$args['_filters'] = $_GET['_filters'] ;

		$this->view->actionUrl = $this->view->url('save',$args, true);
		$this->view->listUrl   = $this->view->url('list',$args, true);

		// if( !empty($this->_config) ){
			if( !empty($this->_modelConfig['FORM']) )
				$this->inputOpts = $this->_modelConfig['FORM'];
			if( !empty($this->_modelConfig['FORM_ORDER']))
				$this->fieldsOrder = $this->_modelConfig['FORM_ORDER'];
		// }
	}

	function setActiveAction(){
		$filters=empty($_GET['_filters'])?'':urldecode($_GET['_filters']);
		if( ! isset($_GET['id'])){
			self::appendAppMsg('La page que vous avez demadée n\'exite pas.','error');
			$this->_isAllowedAction_('list',false);
			return $this->redirectAction('list',array('modelType'=>$this->modelType,"_filters"=>$filters),true);
		}
		$m = abstractModel::getModelInstance($this->modelType,$_GET['id']);
		if( ! $m instanceof abstractModel){
			self::appendAppMsg('La page que vous avez demadée n\'exite pas.','error');
			$this->_isAllowedAction_('list',false);
			return $this->redirectAction('list',array('modelType'=>$this->modelType,"_filters"=>$filters),true);
		}
		$m->{$_GET['prop']} = $_GET['state'];
		$m->save();
		$this->_isAllowedAction_('list',false);
		return $this->redirectAction('list',array('modelType'=>$this->modelType,"_filters"=>$filters),true);
	}

	function saveAction(){
		$this->setDictName();
		if( empty($_POST) ){
			self::appendAppMsg('Aucune données à enregistrée.','error');
			$this->_isAllowedAction_('list',false);
			return $this->redirectAction('list',array('modelType'=>$this->modelType,'embed'=>(empty($_GET['embed'])?'':'on')));
		}
		$modelPKName = abstractModel::_getModelStaticProp($this->modelType,'primaryKey');
		if(isset($_POST['_smvc_confirm']) ){ # manage confirm fields
			foreach($_POST['_smvc_confirm'] as $k=>$v){
				if( ! (isset($_POST[$k]) && $_POST[$k] === $v) ){
					self::appendAppMsg($this->langMsg("field %s mismatch it's confirmation",array($k,$v)),'error');
					unset($_POST['_smvc_confirm']);
					$this->view->assign($_POST);
					if( isset($_POST[$modelPKName])){
						$this->view->_model_ = abstractModel::getModelInstance($this->modelType,$_POST[$modelPKName]);
					}
					return $this->forward('form');
				}
				unset($_POST['_smvc_confirm'][$k]);
			}
			unset($_POST['_smvc_confirm']);
		}

		#- get instance
		if(! isset($_POST[$modelPKName]) ){
			$this->_isAllowedAction_('add');
			$model = abstractModel::getModelInstanceFromDatas($this->modelType,$_POST);
		}else{
			$this->_isAllowedAction_('edit');
			$model = abstractModel::getModelInstance($this->modelType,$_POST[$modelPKName]);
			if(! $model instanceof $this->modelType ){
				self::appendAppMsg('Mise à jour d\'un élément inexistant en base de données.','error');
				$this->_isAllowedAction_('list',false);
				return $this->redirectAction('list',array('modelType'=>$this->modelType,'embed'=>(empty($_GET['embed'])?'':'on')));
			}
			$model->_setDatas($_POST);
		}

		if( $model->hasFiltersMsgs() ){
			self::appendAppMsg($model->getFiltersMsgs(),'error');
			$this->view->assign($model->datas);
			if( isset($_POST[$modelPKName])){
				$this->view->_model_ = $model;
			}
			return $this->forward('form');
		}
		if($model->isTemporary())
			$successMsg = "Nouvel enregistrement ajouté.";
		else
			$successMsg = "Enregistrement mis à jour.";
		try{
			$model->save();
		}catch(Exception $e){
			self::appendAppMsg($e->getMessage(),'error');
			$this->view->assign($model->datas);
			return $this->forward('form');
		}
		self::appendAppMsg($successMsg,'success');
		if( empty($this->_allowedActions['list'])){
			$this->_isAllowedAction_('edit',false);
			return $this->redirectAction('edit',array('modelType'=>$this->modelType,'id'=>$model->PK,'embed'=>(empty($_GET['embed'])?'':'on'),'_filters'=>(empty($_GET['_filters'])?'':$_GET['_filters'])));
		}
		return $this->redirectAction('list',array('modelType'=>$this->modelType,'embed'=>(empty($_GET['embed'])?'':'on'),'_filters'=>(empty($_GET['_filters'])?'':$_GET['_filters'])));
	}

	function delAction(){
		$this->_isAllowedAction_('del');
		if(! isset($_GET['id']) ){
			self::appendAppMsg('Manque d\'information sur l\'action à effectuer.','error');
			$this->_isAllowedAction_('list',false);
			return $this->redirectAction('list',array('modelType'=>$this->modelType));
		}
		$model =  abstractModel::getModelInstance($this->modelType,$_GET['id']);
		if(! $model instanceof $this->modelType ){
			self::appendAppMsg('Enregistrement introuvable en base de données.','error');
		}else{
			$model->delete();
			self::appendAppMsg('Enregistrement supprimée.','success');
		}
		$this->_isAllowedAction_('list',false);
		return $this->redirectAction('list',array('modelType'=>$this->modelType,'_filters'=>(empty($_GET['_filters'])?'':$_GET['_filters'])));
	}
	###--- methods for orderable models ---###
	function moveupAction(){
		$args = array('modelType'=>$this->modelType) ;
		if (!empty($_GET['_filters']))
			$args['_filters'] = $_GET['_filters'] ;
		if(! isset($_GET['id']) ){
			self::appendAppMsg('Manque d\'information sur l\'action à effectuer.','error');
			return $this->redirectAction('list',$args);
		}
		$m = abstractModel::getModelInstance($this->modelType,$_GET['id']);
		if(! $m instanceof $this->modelType ){
			self::appendAppMsg('Enregistrement introuvable en base de données.','error');
			return $this->redirectAction('list',$args);
		}
		$m->moveUp();
		return $this->redirectAction('list',$args);
	}
	function movedownAction(){
		$args = array('modelType'=>$this->modelType) ;
		if (!empty($_GET['_filters']))
			$args['_filters'] = $_GET['_filters'] ;
		if(! isset($_GET['id']) ){
			self::appendAppMsg('Manque d\'information sur l\'action à effectuer.','error');
			return $this->redirectAction('list',$args);
		}
		$m = abstractModel::getModelInstance($this->modelType,$_GET['id']);
		if(! $m instanceof $this->modelType ){
			self::appendAppMsg('Enregistrement introuvable en base de données.','error');
			return $this->redirectAction('list',$args);
		}
		$m->moveDown();
		return $this->redirectAction('list',$args);
	}
	###--- configurations methods ---###
	/**
	* display model administration configuration form
	*/
	function configureAction(){
		if(! DEVEL_MODE_ACTIVE() )
			return $this->forward(ERROR_DISPATCH);
		$this->view->_js_loadPlugin('jquery');
		$this->view->configFile = $this->configFile;
		$this->view->modelFile = $this->getModelFilePath($this->modelType);
		#--- to string configuration
		$this->_toStr = $this->readModel__ToStr($this->modelType);
		$this->datasDefs = array_keys(abstractModel::_getModelStaticProp($this->modelType,'datasDefs'));
		$hasOnes     = array_keys(abstractModel::_getModelStaticProp($this->modelType,'hasOne'));
		$hasMany     = array_keys(abstractModel::_getModelStaticProp($this->modelType,'hasMany'));
		foreach($this->datasDefs as $v)
			$this->datasFields .= "<span class=\"sMVC_dataField tk-border tk-corner tk-inlineStack\">%$v</span> &nbsp; ";
		foreach($hasOnes as $v)
			$this->hasOnes .= "<span class=\"sMVC_dataField tk-border tk-corner tk-inlineStack\">%$v</span> &nbsp; ";
		foreach($hasMany as $v)
			$this->hasManys .= "<span class=\"sMVC_dataField tk-border tk-corner tk-inlineStack\">%$v</span> &nbsp; ";
		#--- list fields configuration
		#- check for config file
		$this->primaryKey    = abstractModel::_getModelStaticProp($this->modelType,'primaryKey');
		$this->listedFields  = (isset($this->_modelConfig['LIST']))?$this->_modelConfig['LIST']:array();
		$datasDefs = $this->datasDefs;
		if( count($this->listedFields) ){ //-- restore selected order
			foreach(array_reverse($this->listedFields) as $k=>$v){
				$key = array_search($k,$datasDefs,true);
				if( false!==$key)
					unset($datasDefs[$key]);
				array_unshift($datasDefs,$k);
			}
		}
		$this->listDatasDefs = $datasDefs;
		#--- forms config
		$formSettings  = (!empty($this->_modelConfig['FORM']))?$this->_modelConfig['FORM']:array();
		$_idMsgs = array();
		foreach($formSettings as $k=>$setting){
			if( !empty($setting['type']) ){
				$inputTypes[$k] = $setting['type'];
				unset($setting['type']);
			}
			if(! empty($setting['help']) ){
				$_idMsgs[] = $setting['help'];
			}
			$inputOptions[$k] = empty($setting)?'':htmlentities(json_encode($setting));
		}
		$this->inputTypes = empty($inputTypes)?array():$inputTypes;
		$this->inputOptions = empty($inputOptions)?array():$inputOptions;
		if( !empty($this->_modelConfig['FORM_ORDER'])){
			$this->fieldOrder = $this->_modelConfig['FORM_ORDER'];
			if( isset($this->fieldOrder['fieldGroupMethod']) ){
				foreach( $this->fieldOrder as $k=>$fldGroup ){
					if( !empty($fldGroup['name']) )
						$_idMsgs[] = $fldGroup['name'];
				}
			}
		}
		$this->view->listUrl = $this->view->url('list',array('modelType'=>$this->modelType));
		#--- locale settings
		$this->langs = langManager::$acceptedLanguages;
		$_idMsgs = array_merge(array('save','back','Add new item',$this->modelType,'Edit '.$this->modelType,'Add new '.$this->modelType),$this->datasDefs,$hasMany,$hasOnes,$_idMsgs);
		foreach($this->langs as $l){
			$messages[$l] = parse_conf_file(langManager::lookUpDic('adminmodels_'.$this->modelType,$l),true);
			$idMsgs[$l]   = array_unique(empty($messages[$l])?$_idMsgs:array_merge($_idMsgs,array_keys($messages[$l])));
		}

		$this->messages = $messages;
		$this->idMsgs = $idMsgs;
		$this->view->_allowedActions = $this->_allowedActions;
	}

	/**
	* set how to display the model as a string
	*/
	function setToStringAction(){
		if(! DEVEL_MODE_ACTIVE() )
			return $this->forward(ERROR_DISPATCH);
		if( isset($_POST['_toStr']) )
			$this->replaceModel__ToStr($this->modelType,$_POST['_toStr']);
		return $this->redirectAction('configure',array('modelType'=>$this->modelType,'#'=>'string'));
	}
	/**
	* set model fields to display in list
	*/
	function setListAction(){
		if(! DEVEL_MODE_ACTIVE() )
			return $this->forward(ERROR_DISPATCH);
		if( empty($_POST['fields'])){
			$config['LIST_'.$this->modelType] = '--UNSET--';
			$config['LIST_TYPE_'.$this->modelType] = '--UNSET--';
		}else{
			$flds = array();
			foreach($_POST['fields'] as $fld){
				$flds[$fld] =  isset($_POST['formatStr'][$fld])?$_POST['formatStr'][$fld]:false;
			}
			$config['LIST_'.$this->modelType] = json_encode($flds);
			$config['LIST_TYPE_'.$this->modelType] = isset($_POST['listType'])?$_POST['listType']:'js';
		}
		#- write config
		write_conf_file($this->configFile,$config,true);
		return $this->redirectAction('configure',array('modelType'=>$this->modelType,'#'=>'list'));
	}
	function setFiltersAction(){
		$filters = array_filter($_POST['filters']);
		if(! DEVEL_MODE_ACTIVE() )
			return $this->forward(ERROR_DISPATCH);
		if( empty($_POST['filters'])){
			$config['LIST_FILTERS_'.$this->modelType] = '--UNSET--';
		}else{
			$config['LIST_FILTERS_'.$this->modelType] = json_encode($filters);
		}
		#- write config
		write_conf_file($this->configFile,$config,true);
		return $this->redirectAction('configure',array('modelType'=>$this->modelType,'#'=>'filters'));
	}

	/**
	* set how to render administrations forms for the given model
	*/
	function setFormInputsAction(){
		if(! DEVEL_MODE_ACTIVE() )
			return $this->forward(ERROR_DISPATCH);
		if(! empty($_POST) ){
			$c = array();
			foreach($_POST['inputTypes'] as $k=>$v){
				$options = array();
				if( $v !== '--- default ---')
					$options['type'] =  $v;
				if( ! empty($_POST['inputOptions'][$k] ) ){
					$tmp = json_decode($_POST['inputOptions'][$k],true);
					if( is_array($tmp) ){
						$options = array_merge($options,$tmp);
					}else{
						self::appendAppMsg("Error while trying to set $k options with an invalid json string <br /><code>".$_POST['inputOptions'][$k]."</code>");
					}
				}
				if( empty($options) )
					continue;
				$c[$k] = $options;
			}
			#- if(! isset($_POST['fieldSet']) ){ #- no grouping so just stock order of elements
			if(empty($_POST['fieldGroupMethod']) ){ #- no grouping so just stock order of elements
				$config['FORM_ORDER_'.$this->modelType] = json_encode(array_keys($_POST['inputTypes']));
			}else{
				$order = array('fieldGroupMethod'=>empty($_POST['fieldGroupMethod'])?'fieldset':$_POST['fieldGroupMethod']);
				foreach($_POST['fieldsOrder'] as $k=>$v){
					$name = ($k==='primary'?'':(empty($_POST['fieldSet'][$k])?"group$k":$_POST['fieldSet'][$k]));
					$order[]=array('name'=>$name,'fields'=>$v?explode(',',$v):'');
				}
				$config['FORM_ORDER_'.$this->modelType] = json_encode($order);
			}
			$config['FORM_'.$this->modelType] = empty($c)?'--UNSET--':json_encode($c) ;
			write_conf_file($this->configFile,$config,true);
		}
		return $this->redirectAction('configure',array('modelType'=>$this->modelType,'#'=>'forms'));
	}

	function resetFieldsOrder(){
		if(! DEVEL_MODE_ACTIVE() )
			return $this->forward(ERROR_DISPATCH);
		$config['FORM_ORDER_'.$this->modelType] = '--UNSET--';
		write_conf_file($this->configFile,$config,true);
		return $this->redirectAction('configure',array('modelType'=>$this->modelType,'#'=>'forms'));
	}
	/**
	* configure langmanager messages for the given model administration
	*/
	function setMessagesAction(){
		if(! DEVEL_MODE_ACTIVE() )
			return $this->forward(ERROR_DISPATCH);
		#-- langs
		$langs = array_keys($_POST['msgs']);
		foreach($langs as $l){
			$dict = $this->checkDisctionnaries($l,true);
			if( false === $dict )
				return $this->forward('configure');
			foreach($_POST['msgs'][$l] as $id=>$msg)
				$dict[$id] = strlen($msg)?$msg:'--UNSET--'; // unset empty values
			if( is_file(APP_DIR."/locales/$l/adminmodels_$this->modelType") && ! is_writable(APP_DIR."/locales/$l/adminmodels_$this->modelType")){
				self::appendAppMsg(APP_DIR."/locales/$l/adminmodels_$this->modelType is not writable.",'error');
			}else{
				write_conf_file(APP_DIR."/locales/$l/adminmodels_$this->modelType",$dict,true);
			}
		}
		return $this->redirectAction('configure',array('modelType'=>$this->modelType,'#'=>'messages'));
	}

	function setActionsAction(){
		if(! DEVEL_MODE_ACTIVE() )
			return $this->forward(ERROR_DISPATCH);
		foreach($_POST['actions'] as $k=>$v)
			$_POST['actions'][$k] =  (bool) $v;
		write_conf_file($this->configFile,array("ACTION_$this->modelType"=>json_encode($_POST['actions'])),true);
		return $this->redirectAction('configure',array('modelType'=>$this->modelType,'#'=>'actions'));
	}
	/**
	* re-generate models from database
	*/
	function generationAction(){
		if(! DEVEL_MODE_ACTIVE() )
			return $this->forward(ERROR_DISPATCH);
		$modelDir = defined('MODELS_DIR')?MODELS_DIR:LIB_DIR.'/models';
		#- check for read/write rights
		if(! is_dir($modelDir)){
			mkdir($modelDir);
			chmod($modelDir,0777);
		}
		if(! is_writable($modelDir) ){
			self::appendAppMsg("Model directory must be writable.",'error');
			return $this->redirect();
		}
		#- do generation for each setted databases
		modelgenerator::$excludePrefixedTables=true;
		modelgenerator::$singularizer = array($this,'singular');
		modelgenerator::$tablePrefixes='_';
		foreach($this->dbConnectionsDefined as $dbConn){
			eval('$g = new modelgenerator('.$dbConn.',$modelDir,1);');
			$g->onExist = 'o';
			$g->doGeneration($dbConn,'');
		}

		#- then ensure correct rights for files
		$modelFiles = glob("$modelDir/*.php");
		foreach($modelFiles as $f){
			chmod($f,0666);
		}
		return $this->redirect();
	}

	public function singular($plural){
		if( isset($this->singluars[$plural])){
			return (array) $this->singluars[$plural];
		}
		$res = array();
		if( preg_match("!ies$!",$plural))
			$res[] = substr($plural,0,-3).'y';
		if( preg_match("!(s|x)$!",$plural) )
			$res[] = substr($plural,0,-1);
		return $res;
	}

	private function readModel__ToStr($modelType){
		$modelStr = $this->getModelFile($modelType);
		if( false===$modelStr )
			return false;
		$modelToStr = match('!^(\s*static\s*public\s*\$__toString\s*=\s*(["\'])([^\2]*?)(\2)\s*;)\s*$!m',$modelStr,3);
		return $modelToStr;
	}
	private function replaceModel__ToStr($modelType,$newToStr){
		if(! DEVEL_MODE_ACTIVE() )
			return $this->forward(ERROR_DISPATCH);
		$modelStr = $this->getModelFile($modelType);
		if( false===$modelStr )
			return false;
		$newContent = preg_replace('!^(\s*)static\s*public\s*\$__toString\s*=\s*(["\'])([^\2]*?)(\2)\s*;\s*$!m','\1static public $__toString = "'.str_replace(array("\n",'"'),array('\n','\"'),$newToStr).'";',$modelStr);
		file_put_contents($this->getModelFilePath($modelType),$newContent);
		return $newContent;
	}
	private function getModelFilePath($modelType){
		$modelType = preg_replace('![^a-z0-9_]!i','',$modelType);
		$modelFile = (defined('MODELS_DIR')?MODELS_DIR:LIB_DIR.'/models')."/$modelType.php";
		return file_exists($modelFile)?$modelFile:false;
	}
	private function getModelfile($modelType){
		$path = $this->getModelFilePath($modelType);
		return $path?file_get_contents($path):false;
	}

	private function checkDisctionnaries($lang,$returnDict=false){
		$dictDir = APP_DIR.'/locales/'.$lang;
		$dictFile = $dictDir.'/adminmodels_'.$this->modelType;
		if( ! is_dir($dictDir) ){ #- check directory exists
			$success = mkdir($dictDir,0777,true); #- create dir recursively
			if(! $success){
				return ! self::appendAppMsg(" can't create directory $dictDir",'error');
			}else{
				chmod(dirname($dictDir),0777); #- chmod doesn't bother about umask settings
				chmod($dictDir,0777); #- chmod doesn't bother about umask settings
			}
		}
		if(! is_file($dictFile) ){ # check file exists and try to create it
			$success = touch($dictFile);
			if(! $success)
				return ! self::appendAppMsg("can't create dictionnary $dictFile",'error');
			chmod($dictFile,0666);
		}elseif( ! is_writable($dictFile) ){
			return ! self::appendAppMsg($dictFile.' must be writable to set messages.','error');
		}
		return $returnDict?parse_conf_file($dictFile,true):true;
	}

	function saveEditModelAction(){
		if(! DEVEL_MODE_ACTIVE() )
			return $this->forward(ERROR_DISPATCH);
		file_put_contents($this->getModelFilePath($this->modelType),preg_replace('/\r(?=\n)/','',$_POST['smvcModel']));
		return $this->redirectAction('configure',array('modelType'=>$this->modelType,'#'=>'model'));
	}
	function saveEditConfigAction(){
		if(! DEVEL_MODE_ACTIVE() )
			return $this->forward(ERROR_DISPATCH);
		file_put_contents($this->configFile,preg_replace('/\r(?=\n)/','',$_POST['smvcConfig']));
		return $this->redirectAction('configure',array('modelType'=>$this->modelType,'#'=>'config'));
	}

	###--- lang management helpers ---###
	public function setDictName(){
		#- set dicName
		list($c,$a) = abstractController::getCurrentDispatch(true);
		$m = $this->modelType;
		if( $this instanceof abstractAdminmodelsController && $this->getName()!='adminmodels')
			$this->_langManagerDicName = $c."_$m"."$a|$c"."_$m|adminmodels_$m|$c"."_$a"."|$c|default";
		else
			$this->_langManagerDicName = $c."_$m"."$a|$c"."_$m|$c"."_$a"."|$c|default";
	}

	public function langMsg($msg,$datas=null){
		return langManager::msg($msg,$datas,$this->_langManagerDicName);
	}
}
