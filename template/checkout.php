<?php
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$order_id = $params['order_id'];
$qr_code = $params['qr_code'];
?>

<table style="width: 100%;text-align: left;">
	<tbody>
		<tr>
			<td valign="top" style="text-align:left;">
				<?= Loc::getMessage('SALE_HPS_EXPRESSPAY_EPOS_DESCRIPTION') ?>
				<br /> <?= Loc::getMessage('SALE_HPS_EXPRESSPAY_EPOS_DESCRIPTION_ONE') ?>
				<br /><b><?= Loc::getMessage('SALE_HPS_EXPRESSPAY_EPOS_DESCRIPTION_TWO') ?></b>
				<br /> <?= Loc::getMessage('SALE_HPS_EXPRESSPAY_EPOS_DESCRIPTION_THREE', ['##ORDER_ID##'=> $order_id]) ?>
				<br /> <?= Loc::getMessage('SALE_HPS_EXPRESSPAY_EPOS_DESCRIPTION_FOUR') ?>
				<br /> <?= Loc::getMessage('SALE_HPS_EXPRESSPAY_EPOS_DESCRIPTION_FIVE') ?>
			</td>
				<td style="text-align: center;padding: 0px 20px 0 0;vertical-align: middle">
					<img src="data:image/jpeg;base64,<?= $qr_code ?>" width="200" height="200" />
					<p><b><?= Loc::getMessage('SALE_HPS_EXPRESSPAY_EPOS_DESCRIPTION_QR_CODE') ?></b></p>
				</td>
		</tr>
	</tbody>
</table>
