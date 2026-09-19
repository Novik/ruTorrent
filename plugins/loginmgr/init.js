plugin.loadLang();

if(plugin.canChangeOptions())
{
	// The stored password never reaches the browser, so the field starts
	// empty and there is nothing to compare it against. What is typed into it
	// is a new password and is sent; what is left alone is not, and the stored
	// one stays as it is. The flag is cleared every time the page is shown.
	plugin.passwordWasTyped = function(name)
	{
		return($('#'+name+'_lmpassword').data('lmtyped') === true);
	}

	plugin.accaddAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function(arg)
	{
		if(plugin.enabled)
		{
			$.each( theWebUI.theAccounts, function(name,val)
			{
				$('#'+name+'_lmenabled').prop("checked", (val.enabled==1));
				$('#'+name+'_lmlogin').val(val.login);
				$('#'+name+'_lmauto').val(val.auto);
				$('#'+name+'_lmpassword')
					.val("")
					.data('lmtyped', false)
					.attr("placeholder", val.password_set ? "••••••••" : "");
				$('#'+name+'_lmenabled').trigger('change');
			});
		}
		plugin.accaddAndShowSettings.call(theWebUI,arg);
	}

	plugin.accWasChanged = function()
	{
		var ret = false;
		$.each( theWebUI.theAccounts, function(name,val)
		{
			if( ($('#'+name+'_lmenabled').prop("checked") ^ val.enabled) ||
				($('#'+name+'_lmauto').val()!=val.auto) ||
				($('#'+name+'_lmlogin').val()!=val.login) ||
				plugin.passwordWasTyped(name))
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
			s+=("&"+name+"_enabled="+($('#'+name+'_lmenabled').prop("checked") ? 1 : 0)+
				"&"+name+"_auto="+$('#'+name+'_lmauto').val()+
				"&"+name+"_login="+encodeURIComponent($('#'+name+'_lmlogin').val()).trim());
			// Left out entirely when nothing was typed: accountManager::set()
			// only touches the stored password for the accounts that send one.
			if(plugin.passwordWasTyped(name))
				s+=("&"+name+"_password="+encodeURIComponent($('#'+name+'_lmpassword').val()).trim());
		});
		this.content = "mode=set"+s;
	        this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/loginmgr/action.php";
		this.dataType = "script";
	}
}

plugin.onLangLoaded = function() {
	const s = Object.entries(theWebUI.theAccounts).map(([name, acct]) => $("<fieldset>").append(
		$("<legend>").text(name),
		$("<div>").addClass("row").append(
			$("<div>").addClass("col-md-6").append(
				$("<input>").attr({type:"checkbox", id:`${name}_lmenabled`, onchange:`linked(this, 0, ['${name}_lmlogin','${name}_lmpassword','${name}_lmauto'])`}),
				$("<label>").attr({for:`${name}_lmenabled`, id:`lbl_${name}_lmenabled`}).text(theUILang.Enabled),
			),
			$("<div>").addClass("col-4 col-md-2").append(
				$("<label>").attr({id:`lbl_${name}_lmauto`, for:`${name}_lmauto`}).addClass("disabled").text(theUILang.accAuto + ": "),
			),
			$("<div>").addClass("col-8 col-md-4").append(
				$("<select>").attr({id:`${name}_lmauto`, maxlength:64}).append(
					...[
						[0, "acAutoNone"], [86400, "acAutoDay"], [604800, "acAutoWeek"], [2592000, "acAutoMonth"],
					].map(([value, text]) => $("<option>").val(value).text(theUILang[text])),
				),
			),
		),
		$("<div>").addClass("row").append(
			$("<div>").addClass("col-4 col-md-2").append(
				$("<label>").attr({id:`lbl_${name}_lmlogin`, for:`${name}_lmlogin`}).addClass("disabled").text(theUILang.accLogin + ": "),
			),
			$("<div>").addClass("col-8 col-md-4").append(
				$("<input>").attr({type:"text", id:`${name}_lmlogin`, maxlength:32}),
			),
			$("<div>").addClass("col-4 col-md-2").append(
				$("<label>").attr({id:`lbl_${name}_lmpassword`, for:`${name}_lmpassword`}).addClass("disabled").text(theUILang.accPassword + ": "),
			),
			$("<div>").addClass("col-8 col-md-4").append(
				$("<input>")
					.attr({type:"password", id:`${name}_lmpassword`, maxlength:64})
					.on("input", function() { $(this).data('lmtyped', true); }),
			),
		),
	));
	this.attachPageToOptions(
		$("<div>").attr("id","st_loginmgr").append(...s)[0],
		theUILang.accAccounts,
	);
}

plugin.onRemove = function() {
	this.removePageFromOptions("st_loginmgr");
}
