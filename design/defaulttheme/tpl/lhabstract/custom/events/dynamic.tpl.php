<div class="form-group">		
<label><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('abstract/widgettheme',$fields['dynamic_invitation']['trans']);?></label>
<?php echo erLhcoreClassAbstract::renderInput('dynamic_invitation', $fields['dynamic_invitation'], $object)?>
</div>

<div class="form-group">		
<label><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('abstract/widgettheme',$fields['event_type']['trans']);?></label>
<?php echo erLhcoreClassAbstract::renderInput('event_type', $fields['event_type'], $object)?>
</div>

<div class="form-group">		
<label><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('abstract/widgettheme',$fields['iddle_for']['trans']);?></label>
<?php echo erLhcoreClassAbstract::renderInput('iddle_for', $fields['iddle_for'], $object)?>
</div>