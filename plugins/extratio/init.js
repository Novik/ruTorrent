plugin.loadMainCSS();
if(browser.isIE && browser.versionMajor < 8)
	plugin.loadCSS("ie");
plugin.loadLang();

plugin.curRule = null;
plugin.maxRuleNo = 0;

theWebUI.showRatioRules = function()
{
	theWebUI.request("?action=getratiorules",[plugin.loadRules, this]);
}

plugin.storeRuleParams = function()
{
	var no = 0;
	if(this.curRule)
	{
		no = parseInt(this.curRule.id.substr(4));
		this.rules[no].pattern = $('#ratio_pattern').val();
		this.rules[no].reason = $('#ratio_reason').val();
		this.rules[no].ratio = $('#dst_ratio').val();
		this.rules[no].channel = $('#dst_throttle').val();
	}
	return(no);
}

theWebUI.selectRatioRule = function( el )
{
	if(plugin.curRule!=el)
	{
		if(plugin.curRule)
			plugin.curRule.className = 'TextboxNormal';
		plugin.storeRuleParams();
		plugin.curRule = el;
		plugin.curRule.className = 'TextboxFocus';
		var no = parseInt(plugin.curRule.id.substr(4));
		var rle = plugin.rules[no];
		$('#ratio_pattern').val(rle.pattern);
                $('#ratio_reason').val(rle.reason);
		$('#dst_ratio').val(rle.ratio);
		$('#dst_throttle').val(rle.channel);
		plugin.setButtonsState();
	}
}

plugin.loadRules = function( rle )
{
	var fltThrottle = $('#dst_throttle');
	if(fltThrottle.length)
	{
		$('#dst_throttle option').remove();
		fltThrottle.append(
			$("<option>").val("").text(theUILang.dontSet),
		);
		for(var i=0; i<theWebUI.maxThrottle; i++)
			if(theWebUI.isCorrectThrottle(i))
				fltThrottle.append(
					$("<option>").val("thr_" + i).text(theWebUI.throttles[i].name),
				);
	}
	var fltRatio = $('#dst_ratio');
	if(fltRatio.length)
	{
		$('#dst_ratio option').remove();
		fltRatio.append(
			$("<option>").val("").text(theUILang.dontSet),
		);
		for(var i=0; i<theWebUI.maxRatio; i++)
			if(theWebUI.isCorrectRatio(i))
				fltRatio.append(
					$("<option>").val("rat_" + i).text(theWebUI.ratios[i].name),
				);
	}
	plugin.curRule = null;
	var list = $("#rlsul");
	list.empty();
	plugin.rules = rle;
	plugin.maxRuleNo = 0;
	for(var i=0; i<plugin.rules.length; i++)
	{
		var f = plugin.rules[i];
		if(plugin.maxRuleNo<f.no)
			plugin.maxRuleNo = f.no;
		list.append(
			$("<li>").append(
				$("<input>").attr({type: "checkbox", id: "_rre" + i}).prop("checked", f.enabled),
				$("<input>").attr(
					{type: "text", id: "_rrn" + i, onfocus: "theWebUI.selectRatioRule(this);"}
				).addClass("TextboxNormal").val(f.name),
			),
		);
	}
	for(var i=0; i<plugin.rules.length; i++)
	{
		var f = plugin.rules[i];
		if(f.no<0)
		{
			plugin.maxRuleNo++;
			f.no = plugin.maxRuleNo;
		}
	}
	plugin.correctCSS();
	theDialogManager.show("dlgEditRatioRules");
	$("#_rrn0").trigger('focus');
	plugin.setButtonsState();
}

plugin.setButtonsState = function()
{
	if(plugin.curRule)
	{
		if($(plugin.curRule).parent().prev().length)
			$('#ratUpRule').removeClass('disabled');
		else
			$('#ratUpRule').addClass('disabled');
		if($(plugin.curRule).parent().next().length)
			$('#ratDownRule').removeClass('disabled');
		else
			$('#ratDownRule').addClass('disabled');
		if(plugin.rules.length)
			$('#ratDelRule').removeClass('disabled');
		else
			$('#ratDelRule').addClass('disabled');		
	}
	else
		$('#ratDelRule,#ratUpRule,#ratDownRule').addClass('disabled');
	$('#ratio_reason').trigger('change');
}

theWebUI.upRatioRule = function()
{
	if(plugin.curRule && $(plugin.curRule).parent().prev().length)
	{
		var cur = $(plugin.curRule).parent();
		var prev = $(plugin.curRule).parent().prev();
	        var curId = parseInt(plugin.curRule.id.substr(4));
	        var prevId = parseInt( prev.children().get(0).id.substr(4) );

	        prev.children().get(0).id = "_rre"+curId;
	        prev.children().get(1).id = "_rrn"+curId;
	        cur.children().get(0).id = "_rre"+prevId;
	        cur.children().get(1).id = "_rrn"+prevId;

       	        var tmp = plugin.rules[curId];
	        plugin.rules[curId] = plugin.rules[prevId];
	        plugin.rules[prevId] = tmp;

		prev.before( cur );
		plugin.setButtonsState();
	}
}

theWebUI.downRatioRule = function()
{
	if(plugin.curRule && $(plugin.curRule).parent().next().length)
	{
		var cur = $(plugin.curRule).parent();
		var next = $(plugin.curRule).parent().next();
	        var curId = parseInt(plugin.curRule.id.substr(4));
	        var nextId = parseInt( next.children().get(0).id.substr(4) );

	        next.children().get(0).id = "_rre"+curId;
	        next.children().get(1).id = "_rrn"+curId;
	        cur.children().get(0).id = "_rre"+nextId;
	        cur.children().get(1).id = "_rrn"+nextId;

	        var tmp = plugin.rules[curId];
	        plugin.rules[curId] = plugin.rules[nextId];
	        plugin.rules[nextId] = tmp;

		next.after( cur );
		plugin.setButtonsState();
	}	
}

theWebUI.addNewRatioRule = function()
{
	var list = $("#rlsul");
	plugin.maxRuleNo++;
	var f = { name: theUILang.ratioNewRule, enabled: 1, pattern: "mininova.org", reason: 1, ratio: "", channel: "", no: plugin.maxRuleNo };
	var i = plugin.rules.length;
	list.append( $("<li>").html("<input type='checkbox' id='_rre"+i+"'/><input type='text' class='TextboxNormal' onfocus=\"theWebUI.selectRatioRule(this);\" id='_rrn"+i+"'/>"));
	plugin.rules.push(f);
	$("#_rrn"+i).val( f.name );
	if(f.enabled)
		$("#_rre"+i).prop("checked",true);
	$("#_rrn"+i).trigger('focus');
	plugin.setButtonsState();
}

theWebUI.deleteCurrentRatioRule = function()
{
	if(plugin.curRule)
	{
		var no = parseInt(plugin.curRule.id.substr(4));
		plugin.rules.splice(no,1);
		$(plugin.curRule).parent().remove();
		plugin.curRule = null;
		if(plugin.rules.length)
		{
			for(var i=no+1; i<plugin.rules.length+1; i++)
			{
				$("#_rrn"+i).prop("id", "_rrn"+(i-1));
				$("#_rre"+i).prop("id", "_rre"+(i-1));
			}
			if(no>=plugin.rules.length)
				no = no - 1;
			$("#_rrn"+no).trigger('focus');
		}
		else
		{
			$('#dst_ratio,#ratio_pattern,#dst_throttle').val('');
			$('#ratio_reason').val('0');
		}
		plugin.setButtonsState();
	}
}

theWebUI.setRatioRules = function()
{
	this.request("?action=setratiorules");
}

rTorrentStub.prototype.setratiorules = function()
{
	this.content = "mode=setrules";
	plugin.storeRuleParams();
	const thrtlInstalled = thePlugins.isInstalled("throttle");
	for(var i=0; i<plugin.rules.length; i++)
	{
		var rle = plugin.rules[i];
		var enabled = $("#_rre"+i).prop("checked") ? 1 : 0;
		var name = $("#_rrn"+i).val();
		this.content += "&name="+encodeURIComponent(name) + "&pattern=" + encodeURIComponent(rle.pattern) + 
			"&enabled=" + enabled + "&reason=" + rle.reason + 
			"&ratio=" + rle.ratio + "&no="+rle.no;
		thrtlInstalled && (this.content += "&channel=" + rle.channel);
	}
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/extratio/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.getratiorules = function()
{
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/extratio/action.php";
	this.dataType = "json";
	this.method = 'GET';
	this.cache = true;
}

rTorrentStub.prototype.checklabels = function()
{
	this.content = "mode=checklabels";
	for(var i=0; i<this.hashes.length; i++)
		this.content += ("&hash="+this.hashes[i]);
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/extratio/action.php";
	this.dataType = "json";
}

plugin.setlabelResponse = rTorrentStub.prototype.setlabelResponse;
rTorrentStub.prototype.setlabelResponse = function( data )
{
	if(this.hashes.length)
	{
		var req = "?action=checklabels";
		for(var i=0; i<this.hashes.length; i++)
			req+=("&hash="+this.hashes[i]);
		theWebUI.request(req);
	}
	return($type(plugin.setlabelResponse) ? plugin.setlabelResponse(data) : data);
}

plugin.correctCSS = function()
{
        if(!this.cssCorrected)
        {
		rule = getCSSRule("div#CatList ul li.sel");
		rule3 = getCSSRule(".lf_rru li input.TextboxFocus");
		if(rule && rule3)
		{
			rule3.style.backgroundColor = rule.style.backgroundColor;
			rule3.style.color = rule.style.color;
		}
		rule = getCSSRule("div#stg .lm");
	        rule1 = getCSSRule(".lf_rru");
        	rule2 = getCSSRule(".lf_rru li input.TextboxNormal");
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
	        rule1 = getCSSRule(".rf_rru");
        	if(rule && rule1)
			rule1.style.backgroundColor = rule.style.backgroundColor;
		this.cssCorrected = true;
	}
}

plugin.createPluginMenu = function()
{
	if(this.enabled)
		theContextMenu.add([theUILang.mnu_ratiorule, "theWebUI.showRatioRules()"]);
}

plugin.onLangLoaded = function()
{
	this.registerTopMenu(7);
	theDialogManager.make( "dlgEditRatioRules", theUILang.ratioRulesManager,
		$("<div>").addClass("cont fxcaret").append(
			$("<div>").addClass("row").append(
				$("<div>").addClass("col-md-6 d-flex flex-column align-items-center").append(
					$("<div>").attr({id: "ratioRuleList"}).addClass("flex-grow-1 align-self-stretch").append(
						$("<ul>").attr({id: "rlsul"}),
					),
					$("<div>").attr({id: "exratio_buttons1"}).addClass("buttons-group-row").append(
						...[
							["ratAddRule", theUILang.ratAddRule, "addNewRatioRule"],
							["ratDelRule", theUILang.ratDelRule, "deleteCurrentRatioRule"],
							["ratUpRule", theUILang.ratUpRule, "upRatioRule"],
							["ratDownRule", theUILang.ratDownRule, "downRatioRule"],
						].map(([id, value, onclick]) => $("<input>").attr(
							{type: "button", id: id, onclick: "theWebUI." + onclick + "(); return(false);"}
						).addClass("Button").val(value)),
					),
				),
				$("<div>").addClass("col-md-6 flex-column align-items-stretch").append(
					$("<fieldset>").append(
						$("<legend>").text(theUILang.ratioIfLegend),
						$("<div>").addClass("d-flex flex-row").append(
							$("<select>").attr({id: "ratio_reason"}).addClass("flex-grow-1").append(
								...[
									theUILang.ratLabelContain, 
									theUILang.ratTrackerContain, 
									theUILang.ratTrackerPublic, 
									theUILang.ratTrackerPrivate,
								].map((val, index) => $("<option>").attr({value: index}).text(val)),
							),
						),
						$("<div>").addClass("d-flex flex-row").append(
							$("<input>").attr({type: "text", id: "ratio_pattern"}).addClass("flex-grow-1"),
						),
					),
					$("<fieldset>").append(
						$("<legend>").text(theUILang.ratioThenLegend),
						$("<div>").addClass("row align-items-center").append(
							$("<div>").addClass("col-md-6 d-flex justify-content-md-end").append(
								$("<label>").attr({for: "dst_ratio"}).text(theUILang.setRatioTo),
							),
							$("<div>").addClass("col-md-6 d-flex").append(
								$("<select>").attr({id: "dst_ratio"}).addClass("flex-grow-1"),
							),
						),
						thePlugins.isInstalled("throttle") && $("<div>").addClass("row align-items-center").append(
							$("<div>").addClass("col-md-6 d-flex justify-content-md-end").append(
								$("<label>").attr({for: "dst_throttle"}).text(theUILang.setChannelTo),
							),
							$("<div>").addClass("col-md-6 d-flex").append(
								$("<select>").attr({id: "dst_throttle"}).addClass("flex-grow-1"),
							),
						),
					),
					$("<fieldset>").append(
						$("<legend>").text(theUILang.ratShortcutLegend),
						$("<div>").addClass("row").append(
							$("<div>").addClass("col-4").append(
								$("<span>").addClass("fw-bold").text("Alt + ↑"),
							),
							$("<div>").addClass("col-8").append(
								$("<span>").text(theUILang.ratUpRule),
							),
						),
						$("<div>").addClass("row").append(
							$("<div>").addClass("col-4").append(
								$("<span>").addClass("fw-bold").text("Alt + ↓"),
							),
							$("<div>").addClass("col-8").append(
								$("<span>").text(theUILang.ratDownRule),
							),
						),
					),
				),
			),
		)[0].outerHTML +
		$("<div>").addClass("buttons-list").append(
			$("<input>").attr(
				{type: "button", onclick: "theDialogManager.hide(\"dlgEditRatioRules\");theWebUI.setRatioRules();return(false);"}
			).addClass("OK Button").val(theUILang.ok),
			$("<input>").attr({type: "button"}).addClass("Cancel Button").val(theUILang.Cancel),
		)[0].outerHTML
	);
	$('#ratio_reason').on('change', function() { 
		$('#ratio_pattern').css("visibility", iv($(this).val())>1 ? "hidden" : "visible");
	});
	$("#dlgEditRatioRules").on("keyup", (e) => {
		if (e.altKey) {
			switch (e.which) {
				case 38: // Alt+ArrowUp
				{
					theWebUI.upRatioRule();
					$("#dlgEditRatioRules").focus();
					break;
				}
				case 40: // Alt+ArrowDown
				{
					theWebUI.downRatioRule();
					$("#dlgEditRatioRules").focus();
					break;
				}
			}
		}
	});
};

plugin.onRemove = function()
{
	theDialogManager.hide("dlgEditRatioRules");
}
