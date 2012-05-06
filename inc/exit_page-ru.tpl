<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Переход по внешней ссылке</title>
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
		var confirmed = confirm("Вы уверены что хотите навсегда отключить это предупреждение и сразу переходить на внешние сайты?");
		
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
        <h3>Вы покидаете сайт {LEAVING_HOST} по внешней ссылке:</h3>
        <pre>{TARGET_URL}</pre>
		<p>Администрация <em>{LEAVING_HOST}</em> не несет ответственности за содержимое сайта.
		Если у Вас нет серьезных оснований доверять этому сайту, лучше всего на него не переходить.</p>
        
		<p>Для перехода на сторонний сайт <a href="{TARGET_URL}" class="red" rel="noindex nofollow" title="Перейти на {TARGET_HOST}">нажмите сюда</a><br />
	    Вы также можете вернуться на <a href="{LEAVING_URL}" class="green" title="Вернуться на предыдущую страницу">предыдущую страницу</a></p>
		
		<form>
          <label>
            <input id="box" type="checkbox" onclick="setCookie('thumb_skip_exit_page',1,365)" />
            <b>Больше не показывать мне это сообщение</b>.</label>
        </form>
      </div></td>
  </tr>
</table>
</body>
</html>