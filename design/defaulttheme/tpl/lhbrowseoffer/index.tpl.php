<ul class="nav nav-tabs">
    <li role="presentation" >
        <a href="<?php echo erLhcoreClassDesign::baseurl('questionary/list')?>" >问卷</a></li>
    <li role="presentation" >
        <a href="<?php echo erLhcoreClassDesign::baseurl('faq/list')?>" >常见问答</a></li>
    <li role="presentation" >
        <a href="<?php echo erLhcoreClassDesign::baseurl('form/index')?>" >表单</a></li>
    <li role="presentation" class="active">
        <a href="<?php echo erLhcoreClassDesign::baseurl('browseoffer/index')?>" >浏览推送</a></li>
    <li role="presentation" >
        <a href="<?php echo erLhcoreClassDesign::baseurl('abstract/list/Survey')?>">调查</a></li>
</ul>

<h4><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('browseoffer/index','General');?></h4>
<ul class="circle small-list">
    <li><a href="<?php echo erLhcoreClassDesign::baseurl('abstract/list')?>/BrowseOfferInvitation"><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('browseoffer/index','Browse your offers');?></a></li>
    <li><a href="<?php echo erLhcoreClassDesign::baseurl('browseoffer/htmlcode')?>"><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('browseoffer/index','HTML Code');?></a></li>
</ul>