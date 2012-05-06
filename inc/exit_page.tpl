<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>You are about to leave {LEAVING_HOST}</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="robots" content="noindex" />
<style type="text/css">
	html, body, table { width: 100%; height: 100%; color: #222; margin:0; padding:0 }
	a { text-decoration:underline; font-weight:normal }
	.box { width:500px; margin:0 auto 100px auto }
	.green { color: green }
	.red { color:red }
	input { margin-left: 0; padding-left: 0 }
</style>
<script type="text/javascript">
	function setCookie(c_name,value,exdays)
	{
		var confirmed = confirm("Are you sure?");
		
		if( confirmed )
		{
			var exdate = new Date();
			exdate.setDate(exdate.getDate() + exdays);
			var c_value = escape(value) + ((exdays==null) ? "" : "; expires="+exdate.toUTCString());
			document.cookie = c_name + "=" + c_value;
		}
		else
		{	// Uncheck the box
			document.getElementById('box').checked = false;
		}
	}
</script>
</head>
<body>
<table>
  <tr>
    <td style="vertical-align:middle"><div class="box">
        <h3>You are about to leave {LEAVING_HOST} to visit this address:</h3>
        <pre>{TARGET_URL}</pre>
		<p><a href="{TARGET_URL}" class="red" rel="noindex nofollow" title="Contitue to {TARGET_HOST}">Continue</a> or <a href="{LEAVING_URL}" class="green" title="Return to previous page">Go back</a></p>
        
		<form>
          <label>
            <input id="box" type="checkbox" onclick="setCookie('thumb_skip_exit_page',1,365)" />
            <b>Disable future warnings</b>.</label>
        </form>
      </div></td>
  </tr>
</table>
</body>
</html>