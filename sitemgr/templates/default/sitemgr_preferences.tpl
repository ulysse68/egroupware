<!-- BEGIN sitemgr_prefs -->
<p><b>{options}</b></p>
<form action="{formaction}" method="post">
<center>
<table border="0" width="95%" cellspacing="8">
<!-- BEGIN PrefBlock -->
	<tr>
		<td>
			<table border="1" cellpadding="5" cellspacing="0" width="100%">
			<tr><td>
			<table border="0" cellpadding="1" cellspacing="0" width="100%">
				<tr valign="top">
					<td width="50%">
						<b>{pref-title}</b><br />
						{pref-input}
					</td>
					<td width="50%">
						<i>{pref-note}</i>
					</td>
				</tr>
			</table>
			</td></tr>
			</table>
		</td>
	</tr>
<!-- END PrefBlock -->
</table>
</center>

<input type="submit" name="btnSave" value="{lang_save}">
</form>
<!-- END sitemgr_prefs -->
