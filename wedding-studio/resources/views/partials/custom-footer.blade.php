<?php foreach($customAssets as $asset):if($asset['kind']==='js'&&$asset['placement']==='footer'):?><script src="<?=e(custom_asset_url($asset))?>" defer></script><?php endif;endforeach;?>
