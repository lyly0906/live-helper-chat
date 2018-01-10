<?php

$fieldsDisplay = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionSugarcrm')->getFieldsForUpdate();
// Set values
$leads = json_encode($lead);
$leads = json_decode($leads,true);
foreach ($leads as $key=>$nameValue){
    if (key_exists($key, $fieldsDisplay)) {
        $fieldsDisplay[$key]['value'] = $nameValue;
    }
}
if ($lead !== false) : ?>

<?php if (isset($lead_updated) && $lead_updated == true) : ?>
<div class="alert alert-success" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
    <?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module', 'Lead was updated')?>
</div>
<?php endif;?>

<form action="" method="post" onsubmit="return sugarcrm.updateLeadFields('<?php echo $lead->id?>','<?php echo $chat->id?>',$(this))">
    <div class="row">
        <?php foreach ($fieldsDisplay as $fieldName => $nameValue) : ?>
            <div class="col-xs-<?php if (isset($nameValue['type']) && $nameValue['type'] == 'textarea') : ?>12<?php else : ?>4<?php endif;?>" <?php if(isset($nameValue['display']) && $nameValue['display'] == 'none') : ?> style="display: none"<?php endif;?>>
        		<div class="form-group">
        			<label><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module', $nameValue['title']);?></label>
        			<?php if (isset($nameValue['type']) && $nameValue['type'] == 'textarea') : ?>
        			     <textarea class="form-control input-sm" name="<?php echo $fieldName?>"><?php echo htmlspecialchars($nameValue['value'])?></textarea>
                    <?php elseif(isset($nameValue['type']) && $nameValue['type'] == 'hidden') : ?>
                        <input class="form-control input-sm" <?php if (isset($nameValue['disabled']) && $nameValue['disabled'] == true) : ?>disabled="disabled"<?php endif;?> type="hidden" name="<?php echo $fieldName?>" value="<?php echo htmlspecialchars($nameValue['value'])?>" />
        			<?php else : ?>
        			     <input class="form-control input-sm" <?php if (isset($nameValue['disabled']) && $nameValue['disabled'] == true) : ?>disabled="disabled"<?php endif;?> type="text" name="<?php echo $fieldName?>" value="<?php echo htmlspecialchars($nameValue['value'])?>" />
        			<?php endif;?>
        		</div>
        	</div>
        <?php endforeach;?>
    </div>
    <input type="submit" class="btn btn-default btn-sugarcrm" data-loading-text="<?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module', 'Loading')?>..." value="<?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('system/buttons','Update')?>" name="doUpdate" />
</form>
<?php endif;?>

