<!-- BEGIN ConfirmDelete -->
<form method="post" action="{action_url}">
<input type="hidden" name="deleteconfirmed" value="{cat_id}">
<input type="hidden" name="cat_id" value="{cat_id}">
<input type="hidden" name="standalone" value="{standalone}">
<p align="center"><font size="+1" color="red"><b>{deleteheader}</b></font>
<p align="center">
<input type="submit" name="btnDelete" value="{lang_yes}">
<input type="submit" name="btnCancel" value="{lang_no}">
</form>
<!-- END ConfirmDelete -->

