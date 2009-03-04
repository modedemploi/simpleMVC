/**
* simpleMVC debug toolbar
* @svnInfos:
*            - $LastChangedDate$
*            - $LastChangedRevision$
*            - $LastChangedBy$
*            - $HeadURL$
* @changelog
*            - 2009-02-10 - add support for DynCss
*            - 2009-02-07 - cookies path set to slash as default
*            - 2009-01-14 - add quick cookie management to keep trace of last opened panel
*/
jQuery().ready(function(){
var cookies={
	get:function(name){
			var re=new RegExp(name+'=([^;]*);?','gi'), result=re.exec(document.cookie)||[];
			return (result.length>1? unescape(result[1]): undefined);
	},
	set:function(name, value, expirationTime, path, domain, secure){
			var time=new Date;
			if(expirationTime)
					time.setTime(time.getTime()+(expirationTime*1000));
			document.cookie=name+ '='+ escape(value)+ '; '+
			(!expirationTime? '': '; expires='+time.toUTCString())+
			'; path='+(path?path:'/')+ (!domain? '': '; domain='+domain)+ (!secure? '': '; secure');
	},
	del:function(name, path, domain){
			var value=this.get(name);
			document.cookie=name+ '='+ '; path='+(path?path:'/')+
			(!domain? '': '; domain='+domain)+
			'; expires=Thu, 01-Jan-70 00:00:01 GMT';
			return value;
	}
};

	var toolBar = $('#sMVCtoolBar');
	var btShow  = $('#sMVCshow');
	var showDiv = $('#sMVCshow_div');
	var btPhperr  = $('#sMVCphperr');
	var phperrDiv = $('#sMVCphperr_div');
	var showDiv = $('#sMVCshow_div');
	var btDb  = $('#sMVCdb');
	var dbDiv = $('#sMVCdb_div');
	var btDataMenu  = $('#sMVCmodels');
	var dataMenu = $('ul#sMVCmodelsList');
	var btToggle = $('#sMVCtoolBarToggle');
	var btCss,cssDiv;

	//-- styling toolbar
	toolBar.css({position:'absolute',right:0,top:0,padding:0,zIndex:1000,background:'#F0F0F0'});
	var pannelStyle = {
		background:'#F0F0F0', position:'absolute', left:0, top:0,
		zIndex:999,  overflow:'auto', display:'none', textAlign:'left',padding:'10px',
		border:'solid #333 1px',borderTop:'none',borderLeft:'none'
	};
	var pannelTitleStyle = { color:'#555', fontSize:'18px', margin:'10px 0', borderBottom:'solid #555 1px' };
	var toolBarButtonStyle = {border:'solid #555 1px','border-top':'none',color:'#333',cursor:'pointer',background:'#F0F0F0',margin:0};
	$('button',toolBar).css(toolBarButtonStyle);


	function _toInt(value){ var i = parseInt(value); return isNaN(i)?0:i; }
	function getWidth(){
		var delta = _toInt(toolBar.css('border-left-width')) + _toInt(toolBar.css('border-right-width'))
			+ _toInt(toolBar.css('padding-left')) + _toInt(toolBar.css('padding-right'))
			+ _toInt(pannelStyle.padding)*2;
		return $('body').width()-toolBar.width()-delta;
	}

	function addPanel(bt,pannel,content,addcount){
		if( addcount ){
			bt.append('<small> ('+ (typeof(addcount)=='number'?addcount:content.length)+')</small>');
		}
		pannel = $(pannel);
		pannel.append(content).css(pannelStyle);
		$('h1',pannel).css(pannelTitleStyle);
		bt.bind('click',{p:pannel},function(e){
			var p = e.data.p;
			$('.sMVCpannel').not(p).hide();
			p.width(getWidth())
				.css('maxHeight',"98%")
				.toggle();
			setPanelCookie();
		});
	}

	function setPanelCookie(){
		// detect opened panel;
		var visiblePanel = 'none';
		if( showDiv.is(':visible') )
			visiblePanel = 'Show';
		else if( phperrDiv.is(':visible') )
			visiblePanel = 'Phperr';
		else if( dbDiv.is(':visible') )
			visiblePanel = 'Db';
		if( visiblePanel !== 'none' ){
			cookies.set('SMVCDevBarPanel',visiblePanel);
		}else{
			cookies.del('SMVCDevBarPanel');
		}
	}

	//-- Manage show display
	var showed = $('div.show');
	if( showed.length == 0){
		btShow.hide().remove();
		showDiv.hide().remove();
	}else{
		addPanel(btShow,showDiv,showed,true);
		showed.css('margin','0 20px');
		$('strong',showed).click(function(){$(this).siblings('pre').toggle();}).css({ cursor:'pointer'});
		$('.toggle',showDiv).click(function(){
			var titles = $('div.show strong');
			var opened   = titles.siblings('pre:visible').length
			var closed = titles.length - opened;
			if( opened > closed)
				titles.each(function(){ var t= $(this); if(t.siblings('pre:visible').length) t.click() });
			else
				titles.each(function(){ var t= $(this); if(t.siblings('pre:hidden').length) t.click() });
		});
	}

	//-- Manage PHP ERRORS display
	var phped = $('div.php_error');
	if( phped.length==0){
		btPhperr.hide().remove();
		phperrDiv.hide().remove();
	}else{
		phped.each(function(){this.innerHTML = this.innerHTML.replace(/(^<br\s*\/?>|<br\s*\/?>$)/,'');});
		addPanel(btPhperr,phperrDiv,phped,true);
	}

	//-- Manage DB::profiler reports display
	var report = $('#dbProfilerReport').parent('table');
	if( report.length==0){
		btDb.hide().remove();
		dbDiv.hide().remove();
	}else{
		report.children('caption').click(); // open profiler table
		var dbMsgs = $('b.dbMsg'); // append eventual class-db errors
		addPanel(btDb,dbDiv,report,(dbMsgs.length ? $('tbody tr',report).length+'/'+dbMsgs.length : $('tbody tr',report).length));
		if( dbMsgs.length){
			dbDiv.append('<h2>DB::messages</h2>').append(dbMsgs);
			$('h2',dbDiv).css(pannelTitleStyle).css('font-size','14px');

		}
	}

	//-- Manage models menu
	if(! dataMenu.length){
		btDataMenu.hide().remove();
	}else{
		btDataMenu.after(dataMenu);
		dataMenu.css({
			border:"solid #555 1px",
			borderTop:'none',
			position:'absolute',
			background:'#f0f0f0',
			zIndex:1000,
			top:btDataMenu.height()/2,
			left:0,
			textAlign:'left',
			listStyleType:'none',
			listStyleImage:'none',
			margin:'10px 0',
			padding:'0 10px'
		}).hide();
		$('li',dataMenu).css({borderBottom:'dotted #333 1px',fontSize:'12px',padding:'2px'}).filter(':last').css({fontStyle:'italic',border:'none'});
		$('a',dataMenu).css({color:'#333'});
		btDataMenu.bind('click',{p:dataMenu},function(e){
			var p = e.data.p;
			dataMenu.toggle();
		});
	}

	//-- Manage DynCss
	simpleMVCDynCssAppend= function(e){
		if( ! $('#sMVCcss').length){ //- create elements
			btCss = $('<button id="sMVCcss">DynCss</button></div>').appendTo(toolBar).css(toolBarButtonStyle);
			cssDiv= $('<div id="sMVCcss_div" class="sMVCpannel"><h1>jqueryDynCss generated</h1>').appendTo('#sMVCpannels');
			addPanel(btCss,cssDiv);
		}
		$('<pre></pre>').html(e.innerHTML).appendTo(cssDiv);
		if( cookies.get('SMVCDevToggle') != 1)
			btCss.hide();
	}


	//-- toggle button
	btToggle.click(function(){
		if(dataMenu.is(':visible'))
			btDataMenu.click();
		$('button',toolBar).not(this).toggle();
		var visible = $('button#sMVCmodels').is(':visible');
		this.innerHTML = visible?'&gt;':'&lt;';
		cookies.set('SMVCDevToggle',visible?1:0);
		$('.sMVCpannel:visible').css('width',getWidth());
	});
	if( cookies.get('SMVCDevToggle') != 1)
		btToggle.click();

	//-- no report to handle so just remove the bar
	var openedPanel = cookies.get('SMVCDevBarPanel');
	if( openedPanel )
		eval('bt'+openedPanel+'.click();');

});
