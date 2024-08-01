plugin.loadLang();

if (plugin.canChangeOptions()) {
	plugin.addAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function(arg) {
		if (plugin.enabled) {
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
		plugin.addAndShowSettings.call(theWebUI, arg);
	}

	theWebUI.xmppWasChanged = function() {
		if ( $$('useEncryption').checked != ( theWebUI.xmpp.UseEncryption == 1 ) )
			return true;
		if ( $$('jabberHost').value != theWebUI.xmpp.JabberHost )
			return true;
		if ( $$('advancedSettings').checked  != ( theWebUI.xmpp.AdvansedSettings  == 1 ) )
			return true;
		if ( $$('jabberPort').value != theWebUI.xmpp.JabberPort)
			return true;
		if ( $$('jabberJid').value != theWebUI.xmpp.JabberJID )
			return true;
		if ( $$('jabberFor').value != theWebUI.xmpp.JabberFor )
			return true;
		if ( $$('message').value != theWebUI.xmpp.Message )
			return true;
		if ( $$('jabberPasswd').value != theWebUI.xmpp.JabberPasswd )
			return true;
		return false;
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function() {
		plugin.setSettings.call(this);
		if ( plugin.enabled && this.xmppWasChanged() )
			this.request( "?action=setxmpp" );
	}

	rTorrentStub.prototype.setxmpp = function() {
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

plugin.onLangLoaded = function() {
	if (this.canChangeOptions()) {
		const stgXmpp = $("<div>").attr({id:"st_xmpp"}).append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.xmpp),
				$("<div>").addClass("row").append(
					...[
						["jabberJid", theUILang.xmppJabberJID],
						["jabberPasswd", theUILang.xmppJabberPasswd],
						["jabberFor", theUILang.xmppJabberFor],
					].flatMap(([id, text]) => {
						return [
							$("<div>").addClass("col-12 col-md-2").append(
								$("<label>").attr({for:id}).text(text),
							),
							$("<div>").addClass("col-12 col-md-4").append(
								$("<input>").attr({type:"text", id:id, maxlength:100}),
							),
						];
					}),
				),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-12 col-md-2 align-items-start").append(
						$("<label>").attr({for:"message"}).text(theUILang.xmppMessage),
					),
					$("<div>").addClass("col-12 col-md-10").append(
						$("<textarea>").attr({id:"message"}),
					),
				),
			),
			$("<fieldset>").append(
				$("<legend>").text(theUILang.xmppAdvancedSettings),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-12").append(
						$("<input>").attr({type:"checkbox", id:"advancedSettings", onchange:"linked(this, 0, ['jabberHost', 'jabberPort', 'useEncryption']);"}),
						$("<label>").attr({for:"advancedSettings"}).text(theUILang.Enabled),
					),
					...[
						["jabberHost", theUILang.xmppJabberHost], ["jabberPort", theUILang.xmppJabberPort],
					].flatMap(([id, text]) => {
						return [
							$("<div>").addClass("col-12 col-md-2").append(
								$("<label>").attr({id:`lbl_${id}`, for:id}).addClass("disabled").text(text + ": "),
							),
							$("<div>").addClass("col-12 col-md-4").append(
								$("<input>").attr({type:"text", id:id, maxlength:100}),
							),
						];
					}),
					$("<div>").addClass("col-12 checkbox").append(
						$("<input>").attr({type:"checkbox", id:"useEncryption"}),
						$("<label>").attr({id:"lbl_useEncryption", for:"useEncryption"}).text(theUILang.xmppUseEncryption),
					),
				),
			),
		);
		this.attachPageToOptions(
			stgXmpp[0],
			theUILang.xmpp,
		);
	}
}

plugin.onRemove = function() {
	this.removePageFromOptions("st_xmpp");
}
