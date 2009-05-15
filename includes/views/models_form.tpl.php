<?php
/**
* @svnInfos:
*            - $LastChangedDate$
*            - $LastChangedRevision$
*            - $LastChangedBy$
*            - $HeadURL$
* @changelog
*            - add forgotten input hidden on primaryKey when using fieldsOrder  
*/
?>
<h1><?= $this->pageTitle ?></h1>
<form action="<?= $this->actionUrl ?>" method="post" class="adminForm">
<?php
	if( !empty($this->datasDefs) ){
		$inputOpts = array(
			'formatStr'=>'<tr class="formInput"><td>%label</td><td>%input</td></tr>'
		);
		if( is_object($this->fieldsOrder)){
			$fieldGroupMethod = $this->fieldsOrder->fieldGroupMethod;
			$formStr = '';
			if( isset($this->_model_)){
				$PK = $this->_model_->primaryKey;
				echo $this->modelFormInput($this->_model_,$PK,isset($this->inputOpts[$PK])?$this->inputOpts[$PK]:array());
			}
			foreach($this->fieldsOrder as $k=>$group){
				if( 'fieldGroupMethod'===$k || empty($group->fields))
					continue;
				$groupStr = "\n<table border=\"0\" cellspacing=\"0\" cellpadding=\"2\">";
				foreach($group->fields as $f){
					$opts = $inputOpts;
					if( isset($this->inputOpts[$f] ) )
						$opts = array_merge($inputOpts,$this->inputOpts[$f]);
					if((! isset($this->_model_)) && !empty($this->{$f})){
						$opts['value'] = $this->{$f};
					}
					$groupStr .= $this->modelFormInput(isset($this->_model_)?$this->_model_:$this->modelType,$f,$opts);
				}
				$groupStr .= "\n</table>\n";
				switch($fieldGroupMethod){
					case 'tabs':
						$tabs = (isset($tabs)?$tabs:'')."<li><a href=\"#tabs-$k\">$group->name</a></li>";
						$formStr .= "\n<div id=\"tabs-$k\">$groupStr\n</div>\n";
						break;
					case 'accordion':
						$formStr .= "\n<h3><a href=\"#\">$group->name</a></h3>\n<div>$groupStr\n</div>\n";
						break;
					default:
						$formStr .= "\n<fieldset id=\"fieldGroup_$group->name\">\n\t<legend>$group->name</legend>\n$groupStr\n</fieldset>\n";
				}
			}
			$tabs = isset($tabs)?"<ul>$tabs</ul>":'';
			echo "<div id=\"fieldsGroups\">\n$tabs$formStr\n</div>\n";
			if( !empty($fieldGroupMethod) && 'fieldset' !== $fieldGroupMethod){
				$this->js("$('form.adminForm #fieldsGroups').$fieldGroupMethod();",'jqueryui');
			}
		}else{
			$formFields = empty($this->fieldsOrder)?array_keys($this->datasDefs):$this->fieldsOrder;
			echo '<table border="0" cellspacing="0" cellpadding="2">';
			foreach($formFields as $k){
				$opts = $inputOpts;
				if( isset($this->inputOpts[$k] ) )
					$opts = array_merge($inputOpts,$this->inputOpts[$k]);
				if((! isset($this->_model_)) && !empty($this->{$k})){
					$opts['value'] = $this->{$k};
				}
				echo $this->modelFormInput(isset($this->_model_)?$this->_model_:$this->modelType,$k,$opts);
			}
			echo '</table>';
		}
	}
?>
<input type="reset" onclick="window.location = '<?= $this->listUrl ?>';"; value="<?= langManager::msg('back',null,$this->_langManagerDicName); ?>"  class="noSize"/>
<input type="submit" value="<?= langManager::msg('save',null,$this->_langManagerDicName); ?>" class="noSize" />
</form>
