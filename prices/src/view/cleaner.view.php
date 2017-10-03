<?php
if (!$USER->IsAdmin()):
	echo('Доступ запрещен');
else:?>
<div class="container">
	<div class="row">
		<div class="col-xs-12">
			<h1> Выберите бренды товаров, для которых требуется удалить цены</h1>

            <?php if($message): ?><div class="alert alert-<?=$message['type']?>"><?=$message['text'] ?></div><?php endif;?>
			<form class="form-horizontal" role="form" method="post" enctype="multipart/form-data" action="./">
				<div class="row">
                    <?
                    $arSelect = Array("ID", "NAME", "IBLOCK_ID");
                    $arFilter = Array("IBLOCK_ID"=> 21, "ACTIVE"=>"Y");
                    $res = CIBlockElement::GetList(Array("NAME"=>"ASC"), $arFilter, false, Array("nPageSize"=>500), $arSelect);
                    while($ob = $res->GetNextElement())
                    {
                        $arFields = $ob->GetFields();
                        ?>
                        <div><label for="brand<?=$arFields['ID']?>"><input type="checkbox" id="brand<?=$arFields['ID']?>" name="brands[]" value="<?=$arFields['ID']?>" /> <?=$arFields['NAME']?></label></div>
                    <?}
                    ?>

                </div>
				<div class="form-group">
					<div class="col-sm-offset-2 col-sm-10">
						<button type="submit" name="go" value="1" class="btn btn-default">Удалить цены</button>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>
<?php
endif;
?>