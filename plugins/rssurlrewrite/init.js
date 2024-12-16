plugin.loadMainCSS();
plugin.loadLang();

plugin.curRule = null;
plugin.maxRuleNo = 0;
plugin.rules = [];

function makeRuleListItem(rule, index) {
	return $("<li>").append(
		$("<input>")
			.attr({type:"checkbox", id:`_re${index}`})
			.prop("checked", rule.enabled),
		$("<input>")
			.attr({type:"text", id:`_rn${index}`})
			.addClass("TextboxNormal")
			.on("focus", selectRule)
			.val(rule.name),
	)
}

/**
 * Actions to be performed when a RSS rule is selected.
 * This function is going to be used as an event handler
 * on rule's textbox focus events, so the `this` keyword
 * refers to the event target, i.e. the focused rule item.
 */
function selectRule() {
	if (plugin.curRule !== this) {
		if (plugin.curRule)
			plugin.curRule.className = 'TextboxNormal';
		theWebUI.storeRuleParams();
		plugin.curRule = this;
		plugin.curRule.className = 'TextboxFocus';
		var no = parseInt(plugin.curRule.id.substr(3));
		var rle = plugin.rules[no];
		$('#RLS_pattern').val(rle.pattern);
		$('#RLS_replacement').val(rle.replacement);
		$('#RLS_src').val(rle.hrefAsSrc);
		$('#RLS_dst').val(rle.hrefAsDest);
		$('#RLS_rss').val(rle.rssHash);
	}
}

function addNewRule() {
	const list = $("#rlslist");
	plugin.maxRuleNo++;
	const f = {
		name: theUILang.rssNewRule,
		enabled: 1,
		pattern: "|http://www.mininova.org/get/(\\d+)|i",
		replacement: "http://www.mininova.org/tor/${1}",
		rssHash: "",
		hrefAsSrc: 1,
		hrefAsDest: 0,
		no: plugin.maxRuleNo,
	};
	const i = plugin.rules?.length ?? 0;
	list.append(
		makeRuleListItem(f, i),
	);
	plugin.rules.push(f);
	$("#_rn"+i).trigger('focus');
}

function deleteCurrentRule() {
	if (plugin.curRule) {
		var no = parseInt(plugin.curRule.id.substr(3));
		plugin.rules.splice(no,1);
		$(plugin.curRule).parent().remove();
		plugin.curRule = null;
		if (plugin.rules?.length) {
			for (var i=no+1; i<plugin.rules.length+1; i++) {
				$("#_rn"+i).attr("id", "_rn"+(i-1));
				$("#_re"+i).attr("id", "_re"+(i-1));
			}
			if (no>=plugin.rules.length)
				no = no - 1;
			$("#_rn"+no).trigger('focus');
		} else {
			$('#RLS_replacement,#RLS_pattern,#RLS_rss').val('');
		}
	}
}

function checkCurrentRule() {
	if (plugin.curRule) {
		$('#RLS_result').val('');
		theWebUI.request("?action=checkrule", [theWebUI.showRuleResults, theWebUI]);
	}
}

theWebUI.showRules = function()
{
	theWebUI.request("?action=getrules",[theWebUI.loadRules, this]);
}	

theWebUI.storeRuleParams = function()
{
	var no = 0;
	if(plugin.curRule)
	{
		no = parseInt(plugin.curRule.id.substr(3));
		plugin.rules[no].pattern = $('#RLS_pattern').val();
		plugin.rules[no].replacement = $('#RLS_replacement').val();
		plugin.rules[no].hrefAsSrc = $('#RLS_src').val();
		plugin.rules[no].hrefAsDest = $('#RLS_dst').val();
		plugin.rules[no].rssHash = $('#RLS_rss').val();
	}	
	return(no);
}	

theWebUI.loadRules = function( rle )
{
	plugin.curRule = null;
	var list = $("#rlslist");
	list.empty();
	$('#RLS_rss option').remove();	
	$('#RLS_rss').append("<option value=''>"+theUILang.allFeeds+"</option>");
	for(var lbl in this.rssGroups)
		$('#RLS_rss').append("<option value='"+lbl+"'>"+this.rssGroups[lbl].name+"</option>");
	for(var lbl in this.rssLabels)
		$('#RLS_rss').append("<option value='"+lbl+"'>"+this.rssLabels[lbl].name+"</option>");
	plugin.rules = rle;
	plugin.maxRuleNo = 0;
	if (plugin.rules) {
		plugin.rules.forEach((rule, index) => {
			if (plugin.maxRuleNo < rule.no)
				plugin.maxRuleNo = rule.no;
			list.append(
				makeRuleListItem(rule, index),
			);
		});
		plugin.rules.forEach(rule => {
			if (rule.no < 0) {
				plugin.maxRuleNo++;
				rule.no = plugin.maxRuleNo;
			}
		});
	}
	plugin.correctCSS();
	theDialogManager.show("dlgEditRules");
	$("#_rn0").trigger('focus');
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
	for(var i=0; i<plugin.rules.length; i++)
	{
		var rle = plugin.rules[i];
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
	var rle = plugin.rules[no];
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
	if(!plugin.cssCorrected)
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
		plugin.cssCorrected = true;
	}
}

if(plugin.canChangeMenu())
{
	plugin.createRSSMenuPrim = theWebUI.createRSSMenuPrim;
	theWebUI.createRSSMenuPrim = function()
	{
		if(plugin.enabled)
		{
			let entries = plugin.createRSSMenuPrim.call(this);
			entries.push([CMENU_SEP]);
			entries.push([theUILang.rssRulesManager, "theWebUI.showRules()"]);
			return entries;
		}
		return plugin.createRSSMenuPrim.call(this);
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

plugin.onLangLoaded = function() {
	this.registerTopMenu(5, theUILang.mnu_rssurlrewrite, theWebUI.showRules);
	const dlgEditRulesContent = $("<div>").addClass("cont").append(
		$("<div>").addClass("row").append(
			$("<div>").addClass("col-md-6 d-flex flex-column align-items-center").append(
				$("<div>").addClass("lf_rur align-self-stretch").append(
					$("<ul>").attr({id: "rlslist"}),
				),
				$("<div>").addClass("buttons-group-row").append(
					$("<button>").on("click", addNewRule).text(theUILang.rssAddRule),
					$("<button>").on("click", deleteCurrentRule).text(theUILang.rssDelRule),
					$("<button>")
						.attr({id: "chkRuleBtn"})
						.on("click", checkCurrentRule)
						.text(theUILang.rssCheckRule),
				),
			),
			$("<div>").addClass("rf_rur col-md-6 d-flex flex-column align-items-stretch").append(
				$("<fieldset>").append(
					$("<legend>").text(theUILang.rssRulesLegend),
					$("<div>").addClass("d-flex flex-column align-items-stretch").append(
						$("<select>").attr({id: "RLS_src"}).append(
							$("<option>").val(1).text(theUILang.rssSrcHref),
							$("<option>").val(0).text(theUILang.rssSrcGuid),
						),
						$("<input>").attr({type: "text", id: "RLS_pattern"}),
						$("<select>").attr({id: "RLS_dst"}).append(
							$("<option>").val(1).text(theUILang.rssDstHref),
							$("<option>").val(0).text(theUILang.rssDstGuid),
						),
						$("<input>").attr({type: "text", id: "RLS_replacement"}),
						$("<div>").addClass("row align-items-center").append(
							$("<div>").addClass("p-0 pe-2 col-md-3 d-flex justify-content-start justify-content-md-end").append(
								$("<label>").attr({for: "RLS_rss"}).text(theUILang.rssStatus + ": "),
							),
							$("<div>").addClass("p-0 col-md-9 d-flex").append(
								$("<select>").attr({id: "RLS_rss"}).append(
									$("<option>").val("").text(theUILang.allFeeds),
								),
							),
						),
					),
				),
				$("<fieldset>").append(
					$("<legend>").text(theUILang.rssRulesDebug),
					$("<div>").addClass("row align-items-center").append(
						$("<div>").addClass("p-0 pe-2 col-md-3 d-flex justify-content-start justify-content-md-end").append(
							$("<label>").attr({for: "RLS_test"}).text(theUILang.rssTestString + ": "),
						),
						$("<div>").addClass("p-0 col-md-9 d-flex").append(
							$("<input>").attr(
								{type: "text", id: "RLS_test", placeholder: "http://www.mininova.org/get/12345"}
							),
						),
					),
					$("<div>").addClass("row align-items-center").append(
						$("<div>").addClass("p-0 pe-2 col-md-3 d-flex justify-content-start justify-content-md-end").append(
							$("<label>").attr({for: "RLS_result"}).text(theUILang.rssTestResult + ":"),
						),
						$("<div>").addClass("p-0 col-md-9 d-flex").append(
							$("<input>").attr({type: "text", id: "RLS_result", readonly: ""}),
						),
					),
				),
			),
		),
	);
	const dlgEditRulesButtons = $("<div>").addClass("buttons-list").append(
		$("<button>").addClass("OK").text(theUILang.ok).on("click", () => {theDialogManager.hide("dlgEditRules"); theWebUI.setRules()}),
		$("<button>").addClass("Cancel").text(theUILang.Cancel),
	);
	theDialogManager.make(
		"dlgEditRules", theUILang.rssRulesManager,
		[dlgEditRulesContent, dlgEditRulesButtons],
	);
};

plugin.onRemove = function()
{
	theDialogManager.hide("dlgEditRules");
}
