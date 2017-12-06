<?php
if (!$USER->IsAdmin()):
    echo('Доступ запрещен <a href="/">Авторизация</a>');
else:?>
<div class="container">
	<div class="row">
		<div class="col-xs-12">
			<h1> Загрузите новый прайс (.xlsx)</h1>
            <div class="well well-sm">
                <p>Файл Excel должен состоять из одного листа и содержать колонки:</p>
                <ol>
                    <li>Бренд</li>
                    <li>Артикул</li>
                    <li>Наименование</li>
                    <li>Штрих-код</li>
                    <li>Цена, RUB</li>
                    <li>Цена, USD</li>
                    <li>Цена, EUR</li>
                </ol>
                <p>Одна из колонок цены должна быть заполнена.</p>
            </div>
			<?php
			$files = glob('upload/*.xlsx');

			usort($files, function($a, $b){
			    return filemtime($a) - filemtime($b);
			});

			if (!empty($files)): ?>
                <ol>
					<?php
					foreach ($files as $file)
					{
						$filename = basename($file);
						echo '<li>'.$filename.'</li>';
					}
					?>
                </ol>
			<?php endif; ?>
            <br><br>
            <?php if($message): ?><div class="alert alert-<?=$message['type']?>"><?=$message['text'] ?></div><?php endif;?>
			<form class="form-horizontal" role="form" method="post" enctype="multipart/form-data" action="./">
				<div class="form-group">
					<label class="control-label col-sm-2" for="newfile">Загрузить новый:</label>
					<div class="col-sm-10">
						<input id="newfile" type="file" class="form-control" name="file_new" />
					</div>
				</div>
				<div class="form-group">
					<div class="col-sm-offset-2 col-sm-10">
						<button type="submit" name="go" value="1" class="btn btn-default">Загрузить файл</button>
					</div>
				</div>
			</form>

            <a class="btn btn-danger" href="cleaner.php">Очистка цен товаров</a>
		</div>
	</div>
</div>
<?php
endif;
?>