plugin.loadMainCSS();
if(browser.isIE && browser.versionMajor < 8)
	plugin.loadCSS("ie");
plugin.loadLang();

theWebUI.curRule = null;
theWebUI.maxRuleNo = 0;

theWebUI.showRules = function()
{
	theWebUI.request("?action=getrules",[this.loadRules, this]);
}

theWebUI.storeRuleParams = function()
{
	var no = 0;
	if(this.curRule)
	{
		no = parseInt(this.curRule.id.substr(3));
		this.rules[no].pattern = $('#RLS_pattern').val();
		this.rules[no].replacement = $('#RLS_replacement').val();
		this.rules[no].hrefAsSrc = $('#RLS_src').val();
		this.rules[no].hrefAsDest = $('#RLS_dst').val();
		this.rules[no].rssHash = $('#RLS_rss').val();
	}
	return(no);
}

theWebUI.selectRule = function( el )
{
	if(this.curRule!=el)
	{
		if(this.curRule)
			this.curRule.className = 'TextboxNormal';
		this.storeRuleParams();
		this.curRule = el;
		this.curRule.className = 'TextboxFocus';
		var no = parseInt(this.curRule.id.substr(3));
		var rle = this.rules[no];
		$('#RLS_pattern').val(rle.pattern);
		$('#RLS_replacement').val(rle.replacement);
		$('#RLS_src').val(rle.hrefAsSrc);
		$('#RLS_dst').val(rle.hrefAsDest);
		$('#RLS_rss').val(rle.rssHash);
	}
}

theWebUI.loadRules = function( rle )
{
	this.curRule = null;
	var list = $("#rlslist");
	list.empty();
	$('#RLS_rss option').remove();	
	$('#RLS_rss').append("<option value=''>"+theUILang.allFeeds+"</option>");
	for(var lbl in this.rssGroups)
		$('#RLS_rss').append("<option value='"+lbl+"'>"+this.rssGroups[lbl].name+"</option>");
	for(var lbl in this.rssLabels)
		$('#RLS_rss').append("<option value='"+lbl+"'>"+this.rssLabels[lbl].name+"</option>");
	this.rules = rle;
	theWebUI.maxRuleNo = 0;
	for(var i=0; i<this.rules.length; i++)
	{
		var f = this.rules[i];
		if(theWebUI.maxRuleNo<f.no)
			theWebUI.maxRuleNo = f.no;
		list.append( $("<li>").html("<input type='checkbox' id='_re"+i+"'/><input type='text' class='TextboxNormal' onfocus=\"theWebUI.selectRule(this);\" id='_rn"+i+"'/>"));
		$("#_rn"+i).val(f.name);
		if(f.enabled)
			$("#_re"+i).prop("checked",true);
	}
	for(var i=0; i<this.rules.length; i++)
	{
		var f = this.rules[i];
		if(f.no<0)
		{
			theWebUI.maxRuleNo++;
			f.no = theWebUI.maxRuleNo;
		}
	}
	plugin.correctCSS();
	theDialogManager.show("dlgEditRules");
	$("#_rn0").trigger('focus');
}

theWebUI.addNewRule = function()
{
	var list = $("#rlslist");
	theWebUI.maxRuleNo++;
	var f = { name: theUILang.rssNewRule, enabled: 1, pattern: "|http://www.mininova.org/get/(\\d+)|i", replacement: "http://www.mininova.org/tor/${1}", rssHash: "", hrefAsSrc: 1, hrefAsDest: 0, no: theWebUI.maxRuleNo };
	var i = this.rules.length;
	list.append( $("<li>").html("<input type='checkbox' id='_re"+i+"'/><input type='text' class='TextboxNormal' onfocus=\"theWebUI.selectRule(this);\" id='_rn"+i+"'/>"));
	this.rules.push(f);
	$("#_rn"+i).val( f.name );
	if(f.enabled)
		$("#_re"+i).prop("checked",true);
	$("#_rn"+i).trigger('focus');
}

theWebUI.deleteCurrentRule = function()
{
        if(this.curRule)
        {
		var no = parseInt(this.curRule.id.substr(3));
		this.rules.splice(no,1);
		$(this.curRule).parent().remove();
		this.curRule = null;
		if(this.rules.length)
		{
			for(var i=no+1; i<this.rules.length+1; i++)
			{
				$("#_rn"+i).prop("id", "_rn"+(i-1));
				$("#_re"+i).prop("id", "_re"+(i-1));
			}
			if(no>=this.rules.length)
				no = no - 1;
			$("#_rn"+no).trigger('focus');
		}
		else
		{
			$('#RLS_replacement,#RLS_pattern,#RLS_rss').val('');
		}
	}
}

theWebUI.checkCurrentRule = function()
{
	if(this.curRule)
	{
		$('#RLS_result').val('');
		this.request("?action=checkrule",[this.showRuleResults, this]);
	}
}

theWebUI.showRuleResults = function( d )
{
	var msg = d.msg ? d.msg : theUILang.rssPatternError;
	$('#RLS_result').val(msg);
}

theWebUI.setRules = function()
{
	this.request("?action=setrules");
}

rTorrentStub.prototype.setrules = function()
{
	this.content = "mode=setrules";
	theWebUI.storeRuleParams();
	for(var i=0; i<theWebUI.rules.length; i++)
	{
		var rle = theWebUI.rules[i];
		var enabled = $("#_re"+i).prop("checked") ? 1 : 0;
		var name = $("#_rn"+i).val();
		this.content = this.content+"&name="+encodeURIComponent(name)+"&pattern="+encodeURIComponent(rle.pattern)+"&enabled="+enabled+
		        "&replacement="+encodeURIComponent(rle.replacement)+
			"&hash="+rle.rssHash+"&hrefAsSrc="+rle.hrefAsSrc+"&hrefAsDest="+rle.hrefAsDest+
			"&no="+rle.no;
	}
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rssurlrewrite/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.checkrule = function()
{
	var no = theWebUI.storeRuleParams();
	var rle = theWebUI.rules[no];
	this.content = "mode=checkrule&pattern="+encodeURIComponent(rle.pattern)+"&replacement="+encodeURIComponent(rle.replacement)+"&test="+encodeURIComponent($('#RLS_test').val());
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rssurlrewrite/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.getrules = function()
{
	this.content = "mode=getrules";
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rssurlrewrite/action.php";
	this.dataType = "json";
	this.method = 'GET';
	this.cache = true;
}

plugin.correctCSS = function()
{
        if(!this.cssCorrected)
        {
		rule = getCSSRule("div#CatList ul li.sel");
		rule3 = getCSSRule(".lf_rur li input.TextboxFocus");
		if(rule && rule3)
		{
			rule3.style.backgroundColor = rule.style.backgroundColor;
			rule3.style.color = rule.style.color;
		}
		rule = getCSSRule("div#stg .lm");
	        rule1 = getCSSRule(".lf_rur");
        	rule2 = getCSSRule(".lf_rur li input.TextboxNormal");
		var ruleMain = getCSSRule("html, body");
        	if(!ruleMain)
        		ruleMain = getCSSRule("html");
		if(rule && rule1 && rule2 && ruleMain)
		{
			rule1.style.borderColor = rule.style.borderColor;
			rule1.style.backgroundColor = rule.style.backgroundColor;
			rule2.style.backgroundColor = rule.style.backgroundColor;
			rule2.style.color = ruleMain.style.color;
		}
		rule = getCSSRule(".stg_con");
	        rule1 = getCSSRule(".rf_rur");
        	if(rule && rule1)
			rule1.style.backgroundColor = rule.style.backgroundColor;
		this.cssCorrected = true;
	}
}

if(plugin.canChangeMenu())
{
	plugin.createRSSMenuPrim = theWebUI.createRSSMenuPrim;
	theWebUI.createRSSMenuPrim = function()
	{
		plugin.createRSSMenuPrim.call(this);
		if(plugin.enabled)
		{
			var el = theContextMenu.get( theUILang.rssMenuManager );
			if( el )
				theContextMenu.add( el, [theUILang.rssRulesManager, "theWebUI.showRules()"] );
		}
	}

	theWebUI.showURLInfo = function(id)
	{
		if($type(theWebUI.rssItems[id]))
		{
			log(theUILang.rssURLGUID+": "+theWebUI.rssItems[id].guid);
			log(theUILang.rssURLHref+": "+theWebUI.rssItems[id].href);
		}
	}

	plugin.createRSSMenu = theWebUI.createRSSMenu;
	theWebUI.createRSSMenu = function(e,id)
	{
		plugin.createRSSMenu.call(this,e,id);
		if(plugin.enabled)
		{
			var el = theContextMenu.get( theUILang.rssMenuAddToFilter );
			if( el && $type(theWebUI.rssItems[id]))
				theContextMenu.add( el, [theUILang.rssURLInfo, "theWebUI.showURLInfo('"+id+"')"] );
		}
	}
}

plugin.createPluginMenu = function()
{
	if(this.enabled)
		theContextMenu.add([theUILang.mnu_rssurlrewrite, "theWebUI.showRules()"]);		
}

plugin.onLangLoaded = function()
{
	this.registerTopMenu(5);
	theDialogManager.make( "dlgEditRules", theUILang.rssRulesManager,
		"<div class='fxcaret'>"+
			"<div class='lfc_rur'>"+
				"<div class='lf_rur' id='ruleList'>"+
					"<ul id='rlslist'></ul>"+
				"</div>"+
				"<div id='RLSchk_buttons'>"+
					"<input type='button' class='Button' value='"+theUILang.rssAddRule+"' onclick='theWebUI.addNewRule(); return(false);'/>"+
					"<input type='button' class='Button' value='"+theUILang.rssDelRule+"' onclick='theWebUI.deleteCurrentRule(); return(false);'/>"+
					"<input type='button' id='chkRuleBtn' class='Button' value='"+theUILang.rssCheckRule+"' onclick='theWebUI.checkCurrentRule(); return(false);'/>"+
				"</div>"+
			"</div>"+
			"<div class='rf_rur' id='ruleProps'>"+
				"<fieldset id='rulePropsFieldSet'>"+
					"<legend>"+theUILang.rssRulesLegend+"</legend>"+
					"<select id='RLS_src'>"+
						"<option value='1'>"+theUILang.rssSrcHref+"</option>"+
						"<option value='0'>"+theUILang.rssSrcGuid+"</option>"+
					"</select><br/>"+
					"<input type='text' id='RLS_pattern' class='TextboxLarge'/><br/>"+
					"<select id='RLS_dst'>"+
						"<option value='1'>"+theUILang.rssDstHref+"</option>"+
						"<option value='0'>"+theUILang.rssDstGuid+"</option>"+
					"</select><br/>"+
					"<input type='text' id='RLS_replacement' class='TextboxLarge'/><br/>"+
					"<label>"+theUILang.rssStatus+":</label><select id='RLS_rss'><option value=''>"+theUILang.allFeeds+"</option></select>"+
				"</fieldset>"+
				"<fieldset id='rulePropsFieldSet'>"+
					"<legend>"+theUILang.rssRulesDebug+"</legend>"+
					"<label>"+theUILang.rssTestString+":</label><input type='text' id='RLS_test' class='TextboxLarge' value='http://www.mininova.org/get/12345'/><br/>"+
					"<label>"+theUILang.rssTestResult+":</label><input type='text' id='RLS_result' class='TextboxLarge'/><br/>"+
				"</fieldset>"+
			"</div>"+
		"</div>"+
		"<div id='RLS_buttons' class='aright buttons-list'>"+
			"<input type='button' class='OK Button' value='"+theUILang.ok+"' onclick='theDialogManager.hide(\"dlgEditRules\");theWebUI.setRules();return(false);'/>"+
			"<input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/>"+
		"</div>");
};

plugin.onRemove = function()
{
	theDialogManager.hide("dlgEditRules");
}
