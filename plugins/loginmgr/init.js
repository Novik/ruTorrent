plugin.loadLang();

if(plugin.enabled && plugin.canChangeOptions())
{
	plugin.accaddAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function(arg) 
	{
		if(plugin.enabled)
		{
			$.each( theWebUI.theAccounts, function(name,val)
			{
				$('#'+name+'_enabled').attr("checked", (val.enabled==1));
				$('#'+name+'_login').val(val.login);
				$('#'+name+'_password').val(val.password);
				$('#'+name+'_enabled').change();
			});
		}
		plugin.accaddAndShowSettings.call(theWebUI,arg);
	}

	plugin.accWasChanged = function() 
	{
		var ret = false;
		$.each( theWebUI.theAccounts, function(name,val)
		{
			if( ($('#'+name+'_enabled').attr("checked") ^ val.enabled) ||
				($('#'+name+'_login').val()!=val.login) ||
				($('#'+name+'_password').val()!=val.password))
			{
				ret = true;
				return(false);
			}
		});
		return(ret);
	}

	plugin.accSettings = theWebUI.setSettings;
	theWebUI.setSettings = function() 
	{
		plugin.accSettings.call(this);
		if(plugin.enabled && plugin.accWasChanged())
			this.request("?action=setacc");
	}

	rTorrentStub.prototype.setacc = function()
	{
		var s = '';
		$.each( theWebUI.theAccounts, function(name,val)
		{
			s+=("&"+name+"_enabled="+($('#'+name+'_enabled').attr("checked") ? 1 : 0)+
				"&"+name+"_login="+encodeURIComponent($.trim($('#'+name+'_login').val()))+
				"&"+name+"_password="+encodeURIComponent($.trim($('#'+name+'_password').val())));
		});
		this.content = "mode=set"+s;
	        this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/loginmgr/action.php";
		this.dataType = "script";
	}
}

plugin.onLangLoaded = function()
{
	var s = '';
	$.each( theWebUI.theAccounts, function(name,val)
	{
		s+="<fieldset>"+
			"<legend>"+name+"</legend>"+
			"<table>"+
				"<tr>"+
					"<td><input type='checkbox' id='"+name+"_enabled' onchange=\"linked(this, 0, ['"+name+"_login','"+name+"_password']);\"/><label for='"+name+"_enabled' id='lbl_"+name+"_enabled'>"+theUILang.Enabled+"</label></td>"+
				"</tr>"+
				"<tr>"+
					"<td><label id='lbl_"+name+"_login' for='"+name+"_login' class='disabled'>"+theUILang.accLogin+":</label></td>"+
					"<td class=\"alr\"><input type='text' id='"+name+"_login' class='TextboxLarge' maxlength='32' disabled='true' /></td>"+
				"</tr>"+
				"<tr>"+
					"<td><label id='lbl_"+name+"_password' for='"+name+"_password' class='disabled'>"+theUILang.accPassword+":</label></td>"+
					"<td class=\"alr\"><input type='password' id='"+name+"_password' class='TextboxLarge' maxlength='32' disabled='true' /></td>"+
				"</tr>"+
			"</table>"+
		"</fieldset>";
	});
	this.attachPageToOptions($("<div>").attr("id","st_loginmgr").html(s)[0],theUILang.accAccounts);
}

plugin.onRemove = function()
{
	this.removePageFromOptions("st_loginmgr");
}
