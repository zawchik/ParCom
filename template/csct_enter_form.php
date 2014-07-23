<div class="container">
	<div class="row">
		<div class="span4 offset4 well">
			<legend>Вход в ParCom CMF</legend>
            <?php if (isset($_REQUEST['prcm_login'])){ ?>
          	<div class="alert alert-error">
                <a class="close" data-dismiss="alert" href="#">×</a>Ошибка входа
            </div>
            <?php } ?>
			<form method="POST" action="/parcom/" accept-charset="UTF-8">
            <input type="hidden" name="prcm_login" value="1" />
			<input type="text" id="ruser" class="span4" name="ruser" placeholder="Логин">
			<input type="password" id="pw" class="span4" name="pw" placeholder="Пароль">
            <label class="checkbox">
                <input type="checkbox" name="remember" value="1"> Запомнить
            </label>
			<button type="submit" name="submit" class="btn btn-info btn-block">Вход</button>
			</form>    
		</div>
	</div>
</div>