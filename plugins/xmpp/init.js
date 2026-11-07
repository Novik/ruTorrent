plugin.loadLang();

if (plugin.canChangeOptions()) {
	// The stored password never reaches the browser, so the field starts empty
	// and there is nothing to compare it against. What is typed into it is a
	// new password and is sent; what is left alone is not, and the stored one
	// stays as it is. The flag is cleared every time the page is shown.
	plugin.passwordWasTyped = function() {
		return plugin.xmppPasswordTyped === true;
	}

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
			$$('jabberPasswd').value = "";
			$$('jabberPasswd').placeholder = theWebUI.xmpp.JabberPasswd_set ? "\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022" : "";
			plugin.xmppPasswordTyped = false;
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
		if ( plugin.passwordWasTyped() )
			return true;
		return false;
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function() {
		plugin.setSettings.call(this);
		if ( plugin.enabled && this.xmppWasChanged() )
			this.request( "?action=setxmpp" );
	}

	// rXmpp::set() reads the body back by splitting it on "&" and then on "=",
	// so every value goes out escaped. A value carrying either character would
	// otherwise become a field of its own or be cut short at the separator.
	const field = function(name, value) {
		return "&" + name + "=" + encodeURIComponent(value);
	}

	rTorrentStub.prototype.setxmpp = function() {
		// An escaped value and one a settings page from before this wrote
		// literally reach rXmpp::set() as the same bytes: "p%26ssword" is
		// either an escaped "p&ssword" or those ten characters. This is
		// what tells it apart, and it unescapes nothing without it.
		this.content = "formEncoding=percent-v1" +
			field("advancedSettings", $$('advancedSettings').checked ? '1' : '0') +
			field("useEncryption", $$('useEncryption').checked ? '1' : '0') +
			field("jabberHost", $$('jabberHost').value) +
			field("jabberPort", $$('jabberPort').value) +
			field("jabberJid", $$('jabberJid').value) +
			field("jabberFor", $$('jabberFor').value) +
			field("message", $$('message').value);
		// Left out entirely when nothing was typed: rXmpp::set() only replaces
		// the stored password when a request carries one.
		if ( plugin.passwordWasTyped() )
			this.content += field("jabberPasswd", $$('jabberPasswd').value);
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
								id.includes("Passwd")
									? $("<input>")
										.attr({type:"password", id:id, maxlength:100})
										.on("input", function() { plugin.xmppPasswordTyped = true; })
									: $("<input>").attr({type:"text", id:id, maxlength:100}),
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
