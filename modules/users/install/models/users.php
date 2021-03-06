<?php
/**
* autoGenerated on 2011-10-26
* @package models
* @subpackage
* @class users
*/

require dirname(__file__).'/BASE_users.php';

class users extends BASE_users{
	// define here all your user defined stuff so you don't bother updating BASE_users class
	// should also be the place to redefine anything to overwrite what has been unproperly generated in BASE_users class
	/**
	* list of filters used as callback when setting datas in fields.
	* this permitt to automate the process of checking datas setted.
	* array('fieldName' => array( callable filterCallBack, array additionalParams, str errorLogMsg, mixed $errorValue=false);
	* 	minimal callback prototype look like this:
	* 	function functionName(mixed $value)
	* 	callback have to return the sanitized value or false if this value is not valid
	* 	logMsg can be retrieved by the metod getFiltersMsgs();
	* 	additionnalParams and errorLogMsg are optionnals and can be set to null to be ignored
	* 	(or simply ignored but only if you don't mind of E_NOTICE as i definitely won't use the @ trick)
	*   $errorValue is totally optional and permit to specify a different error return value for filter than false
	*   (can be usefull when you use filter_var to check boolean for example)
	* )
	*/
	static protected $filters = array(
		'login'=>array(
			 'trim'
			,'strip_tags'
			,array('range',array(5,20),'Login must be between 5 to 20 characters')
			,array('match','!^[a-zA-Z0-9_-]+$!','Login can only contain a-zA-Z0-9_- chararacters')
			,array('self::isUniqueLogin',null,'Login already exists')
		)
		,'password'=>array(
			 'trim'
			,'strip_tags'
			,array('minlength',6,'Password must be 6 characters long at least')
			,array('md5',MOD_USERS_SALT)
		)
		,'email'=>array(
			 'trim'
			,array('easymail::check_address',null,'Must be a valid email')
		)
	);

	static protected $modelName = 'users';

	/** formatString to display model as string */
	static public $__toString = '%login';

	/** names of modelAddons this model can manage */
	static protected $modelAddons = array('filters','activable');
	public $_activableFields = array('active');
	/**
	* if true then the model can't have an empty primaryKey value (empty as in php empty() function)
	* so passing an empty PrimaryKey at getInstance time will result to be equal to a getNew call
	*/
	static protected $_avoidEmptyPK = true;
	/**
	* Make use users::$_hasOne and/or users::$_hasMany if you want to override thoose defined in BASE_users
	* any key set to an empty value will be dropped, others will be appended if not exists or override if exists
	* MUST BE PUBLIC (yes it's a shame) but get_class_vars presents bug in some php version
	* static public $_hasOne = array();
	* static public $_hasMany = array();
	*/
	static public $_hasOne = array(
		// 'relName'=> array('modelName'=>'modelName','relType'=>'ignored|dependOn|requireBy',['localField'=>'fldNameIfNotPrimaryKey','foreignField'=>'fldNameIfNotPrimaryKey','foreignDefault'=>'ForeignFieldValueOnDelete'])
		 'userRole' => null
		,'role' => array(
			 'modelName'=>'userRoles'
			,'localField'=>'userRole'
			,'relType'=>'dependOn'
		)
	);


	###--- AUTOGENERATION PROCESS PROPOSED THOOSE ADDITIONAL METHODS ---###


	static function getByLoginPass($login,$pass){
		$pass = filtersModelAddon::md5($pass,MOD_USERS_SALT);
		return abstractModel::getFilteredModelInstance(__class__,array('WHERE login=? AND password=?',$login,$pass));
	}

	/**
	* start an active session for the user ( only one user can be active at the same time)
	* @return $this for method chaining
	*/
	function startSession(){
		$_SESSION['moduser'] = $this->datas;
		unset($_SESSION['moduser']['password']);
		return $this;
	}

	/**
	* will close active this user active session (will let session untouch if another user is logged in)
	* @param bool $full if true then will destroy full session datas
	* @param bool $regenSessionId if true then will destroy full session datas
	* @return $this for method chaining
	*/
	function closeSession($full=false,$regenSessionId=false){
		if( empty($_SESSION['moduser']['userId']) || $_SESSION['moduser']['userId'] !== $this->PK ){
			return $this;
		}
		self::resetSesssion($full,$regenSessionId);
		return $this;
	}

	/**
	* close any active user session
	* @param bool $full if true then will destroy full session datas
	* @param bool $regenSessionId if true then will destroy full session datas
	* @return void
	*/
	static function resetSession($full=false,$regenSessionId=false){
		if( $full ){
			$_SESSION = array();
		}else{
			unset($_SESSION['moduser']);
		}
		if( $regenSessionId ){
			session_regenerate_id(true);
		}
	}

	/**
	* return user currently logged in
	* @return users or null
	*/
	static function getCurrent(){
		if( empty($_SESSION['moduser']['userId']) )
			return null;
		return self::getInstance($_SESSION['moduser']['userId']);
	}

	/**
	* check if user's role has the passed right.
	* @see userRoles::hasRight()
	* @param mixed $right may be a userRight instance, a userRight PK or string domain.name
	* @return bool
	*/
	function hasRight($right){
		$this->role->rights->loadDatas('domain');
		return $this->role->hasRight($right);
	}
	/*
	function hasRight($right,$flush=false){
		$rights = $this->getRights($flush);
		if( $right instanceof userRights){
			return $rights->hasModel($right);
		}
		if( is_numeric($right) ){
			return $rights->filterByRightId($right,'==')->count()>0?true:false;
		}else	if( strpos($right,'.') ){
			return $rights->filterBy('FullName',$right)->count()>0?true:false;
		}
		//--finally check for any right in the userRightGroup
		return $rights->userRightGroup->filterByName($right)->count()>0?true:false;

	}
	function getRights($flush=false){
		if(empty($this->__rightsCache) || $flush){
			$this->__rightsCache = $this->userGroups->userRights->merge($this->userRights);
		}
		return $this->__rightsCache;
	}
	/**
	* proposed filter to avoid setting an already existing values to a unique field.
	*/
	public function filterLogin($val){
		if( !$this->isUniqueLogin() ){
			$this->appendFilterMsg("can't set login to an already used value: $val");
			return false;
		}
		return $val;
	}
	/**
	* check if the login value is unique or already exists in database
	*/
	public function isUniqueLogin($v=null){
		return !abstractModel::modelCheckFieldDatasExists('users', 'login', $v===null?$this->login:$v, false, $this->isTemporary()?null:$this->PK);
	}
	function onBeforeDelete(){
		if( in_array($this->PK,array(1)) ){
			return true; // can't delete first administrator account
		}
	}
}
/**
* @class usersCollection
*/
class usersCollection extends modelCollection{
	/**
	* you can override here default modelCollection methods
	*/
	protected $collectionType = 'users';

	public function __construct(array $modelList=null){
		parent::__construct($this->collectionType,$modelList);
	}
	static public function init(array $modelList=null){
		return new usersCollection($modelList);
	}
}
