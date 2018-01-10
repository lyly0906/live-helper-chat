<ul class="nav nav-tabs">
    <li role="presentation" >
        <a href="<?php echo erLhcoreClassDesign::baseurl('questionary/list')?>" >问卷</a></li>
    <li role="presentation" >
        <a href="<?php echo erLhcoreClassDesign::baseurl('faq/list')?>" >常见问答</a></li>
    <li role="presentation" class="active">
        <a href="<?php echo erLhcoreClassDesign::baseurl('form/index')?>" >表单</a></li>
    <li role="presentation" >
        <a href="<?php echo erLhcoreClassDesign::baseurl('browseoffer/index')?>" >浏览推送</a></li>
    <li role="presentation" >
        <a href="<?php echo erLhcoreClassDesign::baseurl('abstract/list/Survey')?>">调查</a></li>
</ul>

<ul>
    <li><a href="<?php echo erLhcoreClassDesign::baseurl('abstract/list')?>/Form"><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('form/index','List of forms');?></a></li>
    <?php if (erLhcoreClassUser::instance()->hasAccessTo('lhform','generate_js')) : ?>
    <li><a href="<?php echo erLhcoreClassDesign::baseurl('form/embedcode')?>"><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('form/index','Page embed code');?></a></li>
    <?php endif;?>
</ul>