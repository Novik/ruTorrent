plugin.loadLang();

if(plugin.canChangeOptions())
{
	plugin.loadMainCSS();
	plugin.addAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function( arg )
	{
	        if(plugin.enabled)
	        {
			$$('useEncryption').checked = ( theWebUI.xmpp.UseEncryption == 1 );
			$$('advancedSettings').checked = ( theWebUI.xmpp.AdvancedSettings == 1);
			$$('jabberHost').value = theWebUI.xmpp.JabberHost;
			$$('jabberPort').value = theWebUI.xmpp.JabberPort;
			linked( $$('advancedSettings'), 0, ['useEncryption', 'jabberHost', 'jabberPort'] );
			$$('jabberJid').value = theWebUI.xmpp.JabberJID;
			$$('jabberFor').value = theWebUI.xmpp.JabberFor;
			$$('jabberPasswd').value = theWebUI.xmpp.JabberPasswd;
			$$('message').value = theWebUI.xmpp.Message;
		}
		plugin.addAndShowSettings.call(theWebUI,arg);
	}

	theWebUI.xmppWasChanged = function()
	{
		if( $$('useEncryption').checked != ( theWebUI.xmpp.UseEncryption == 1 ) )
		{
			return true;
		}
		if( $$('jabberHost').value != theWebUI.xmpp.JabberHost )
		{
			return true;
		}
		if( $$('advancedSettings').checked  != ( theWebUI.xmpp.AdvansedSettings  == 1 ) )
		{
			return true;
		}
		if( $$('jabberPort').value != theWebUI.xmpp.JabberPort)
		{
			return true;
		}
		if( $$('jabberJid').value != theWebUI.xmpp.JabberJID )
		{
			return true;
		}
		if( $$('jabberFor').value != theWebUI.xmpp.JabberFor )
		{
			return true;
		}
		if( $$('message').value != theWebUI.xmpp.Message )
		{
			return true;
		}
		if( $$('jabberPasswd').value != theWebUI.xmpp.JabberPasswd )
		{
			return true;
		}
		return false;
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function()
	{
		plugin.setSettings.call(this);
		if( plugin.enabled && this.xmppWasChanged() )
			this.request( "?action=setxmpp" );
	}

	rTorrentStub.prototype.setxmpp = function()
	{
		this.content = "advancedSettings=" + ( $$('advancedSettings').checked ? '1' : '0' ) +
			"&useEncryption=" + ( $$('useEncryption').checked  ? '1' : '0' ) +
			"&jabberHost=" + $$('jabberHost').value +
			"&jabberPort=" + $$('jabberPort').value +
			"&jabberJid=" + $$('jabberJid').value +
			"&jabberFor=" + $$('jabberFor').value +
			"&message=" + $$('message').value +
			"&jabberPasswd=" + $$('jabberPasswd').value;
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/xmpp/action.php";
		this.dataType = "script";
	}
}

plugin.onLangLoaded = function()
{
	if(this.canChangeOptions())
	{
		this.attachPageToOptions( $("<div>").attr("id","st_xmpp").html(
		"<fieldset>"+
			"<legend>"+ theUILang.xmpp +"</legend>"+
			"<table>"+
			"<tr>"+
				"<td>"+
					"<label for='jabberJid'>"+ theUILang.xmppJabberJID +"</label>"+
					"<input type='text' id='jabberJid' class='TextboxLarge' maxlength='100' />"+
				"</td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+
					"<label for='jabberPasswd'>"+ theUILang.xmppJabberPasswd +"</label>"+
					"<input type='password' id='jabberPasswd' class='TextboxNormal' maxlength='100' />"+
				"</td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+
					"<label for='jabberFor'>"+ theUILang.xmppJabberFor +"</label>"+
					"<input type='text' id='jabberFor' class='TextboxLarge' maxlength='100' />"+
				"</td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+
					"<label for='message'>"+ theUILang.xmppMessage +"</label>"+
					"<textarea id='message'></textarea>"+
				"</td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+
					"<input type='checkbox' id='advancedSettings' checked='false' "+
					"onchange='linked(this, 0, [\"jabberHost\", \"jabberPort\", \"useEncryption\"]);' />"+
						"<label for='advancedSettings'>"+ theUILang.xmppAdvancedSettings +"</label>"+
				"</td>"+
			"</tr>"+
			"<tr>"+
				"<td class='ctrls_level2' colspan=2>"+
					"<label id='lbl_jabberHost' for='jabberHost' class='disabled' disabled='true'>"+
					theUILang.xmppJabberHost +":</label>"+
					"<br><input type='text' id='jabberHost' class='TextboxNormal' maxlength='100' />"+
					"<br><label id='lbl_jabberPort' for='jabberPort' class='disabled' disabled='true'>"+theUILang.xmppJabberPort+":</label>"+
					"<br><input type='text' id='jabberPort' class='TextboxNormal' maxlength='100' />"+
					"<div class='checkbox'>" +
						"<input type='checkbox' id='useEncryption'/>"+
						"<label id='lbl_useEncryption' for='useEncryption'>"+ theUILang.xmppUseEncryption +"</label>"+
					"</div>" +
				"</td>"+
			"</tr>"+
			"</table>"+
		"</fieldset>")[0], theUILang.xmpp );
	}
}

plugin.onRemove = function()
{
	this.removePageFromOptions( "st_xmpp" );
}