<?php if ((int)erLhcoreClassModelChatConfig::fetch('show_language_switcher')->current_value == 1) : ?>

<?php 
$enabledLanguages = explode(',',erLhcoreClassModelChatConfig::fetch('show_languages')->current_value);
$langArray = array(
    'zh'  => '简体中文',
    'cht' => '繁体中文',
    'en' => '英语',
    'yue' => '粤语',
    'wyw' => '文言文',
    'jp' => '日语',
    'kor' => '韩语',
    'fra' => '法语',
    'spa' => '西班牙语',
    'th' => '泰语',
    'ara' => '阿拉伯语',
    'ru' => '俄语',
    'pt' => '葡萄牙语',
    'de' => '德语',
    'it' => '意大利语',
    'el' => '希腊语',
    'nl' => '荷兰语',
    'pl' => '波兰语',
    'bul' => '保加利亚语',
    'est' => '爱沙尼亚语',
    'dan' => '丹麦语',
    'fin' => '芬兰语',
    'cs' => '捷克语',
    'rom' => '罗马尼亚语',
    'slo' => '斯洛文尼亚语',
    'swe' => '瑞典语',
    'hu' => '匈牙利语',
    'vie' => '越南语',
);
?>

<div class="btn-group pull-right" role="group">
        <button type="button" class="btn btn-default btn-xs dropdown-toggle" title="<?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('chat/startchat','Choose your language');?>" data-toggle="dropdown" aria-expanded="false">
          <i class="material-icons mr-0">language</i>
          <span class="caret"></span>
        </button>
        <ul class="dropdown-menu f-dropdown-lang" role="menu">
          
          <?php foreach ($enabledLanguages as $siteAccess) : ?>
    		<li role="menuitem"><a onclick="return lhinst.switchLang($('#form-start-chat'),'<?php echo $siteAccess?>')" href="#"><?php echo $langArray[$siteAccess]?></a>
    	<?php endforeach;?> 
          
        </ul>
      </div>
         
<?php endif;?>